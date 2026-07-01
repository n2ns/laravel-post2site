<?php

namespace N2ns\LaravelPost2Site\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use N2ns\LaravelPost2Site\Contracts\Post2SiteAdapter;
use N2ns\LaravelPost2Site\Data\AssetUpload;
use N2ns\LaravelPost2Site\Data\ClientContext;
use N2ns\LaravelPost2Site\Data\DraftContext;
use N2ns\LaravelPost2Site\Data\PublishRequest;
use N2ns\LaravelPost2Site\Data\ValidationResult;
use N2ns\LaravelPost2Site\Models\Post2SiteAsset;
use N2ns\LaravelPost2Site\Models\Post2SiteDraft;

class McpPublishingController extends Controller
{
    public function __construct(private readonly Post2SiteAdapter $adapter) {}

    public function capabilities(Request $request): JsonResponse
    {
        $hostCapabilities = $this->adapter->capabilities();

        return response()->json(array_replace_recursive([
            'contract' => 'post2site-publishing',
            'contract_version' => '1.0',
            'package_version' => config('post2site.version', '0.4.0'),
            'base_path' => '/'.trim(config('post2site.route_prefix', 'api/v1/mcp'), '/'),
            'workflow' => config('post2site.workflow'),
            'auth' => [
                'required' => true,
                'mode' => 'api_key',
                'header' => config('post2site.auth.header', 'X-API-KEY'),
                'client_attribution' => true,
            ],
            'endpoints' => [
                'capabilities' => 'GET /capabilities',
                'site_context' => 'GET /site-context',
                'editorial_policy' => 'GET /editorial-policy',
                'inventory_resources' => 'GET /inventory/resources',
                'inventory_resource' => 'GET /inventory/resources/{target_identifier}',
                'inventory_stats' => 'GET /inventory/stats',
                'inventory_duplicates' => 'POST /inventory/duplicates',
                'working_draft_validate' => 'POST /working-drafts/validate',
                'drafts' => 'GET /drafts',
                'create_draft' => 'POST /drafts',
                'get_draft' => 'GET /drafts/{draft_id}',
                'update_draft' => 'PATCH /drafts/{draft_id}',
                'validate_draft' => 'POST /drafts/{draft_id}/validate',
                'preview_draft' => 'GET /drafts/{draft_id}/preview',
                'publish_draft' => 'POST /drafts/{draft_id}/publish',
                'upload_asset' => 'POST /assets',
            ],
            'safety' => [
                'delete_exposed' => false,
                'database_access_exposed' => false,
                'shell_access_exposed' => false,
                'server_operations_exposed' => false,
            ],
        ], array_intersect_key($hostCapabilities, array_flip([
            'host_profile',
            'host_schema',
            'host_metadata',
        ]))));
    }

    public function siteContext(): JsonResponse
    {
        return response()->json($this->adapter->siteContext());
    }

    public function editorialPolicy(): JsonResponse
    {
        return response()->json($this->adapter->editorialPolicy());
    }

    public function inventoryResources(Request $request): JsonResponse
    {
        $query = $request->query();
        $query['per_page'] = min((int) ($query['per_page'] ?? 20), (int) config('post2site.drafts.per_page_max', 100));

        return response()->json($this->adapter->inventory($query)->toArray());
    }

    public function inventoryResource(string $target_identifier): JsonResponse
    {
        $result = $this->adapter->inventory(['target_identifier' => $target_identifier, 'per_page' => 1]);
        $item = $result->items[0] ?? null;

        return $item === null
            ? $this->error('not_found', 'The requested resource was not found.', 404)
            : response()->json(['item' => $item, 'host_metadata' => $result->hostMetadata]);
    }

    public function inventoryStats(Request $request): JsonResponse
    {
        return response()->json($this->adapter->inventoryStats($request->query()));
    }

    public function inventoryDuplicates(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'mode' => ['nullable', 'string'],
            'target_identifier' => ['nullable', 'string'],
            'content_payload' => ['required', 'array'],
            'client_metadata' => ['nullable', 'array'],
        ]);

        return response()->json($this->adapter->findDuplicates($data)->toArray());
    }

    public function validateWorkingDraft(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'mode' => ['nullable', 'in:draft,publish'],
            'article' => ['required', 'array'],
            'article.mode' => ['required', 'string'],
            'article.target_identifier' => ['nullable', 'string'],
            'article.content_payload' => ['required', 'array'],
            'article.client_metadata' => ['nullable', 'array'],
        ]);

        $article = $data['article'];
        $this->rejectForbiddenPayloadFields($article['content_payload']);
        $assetRefs = $this->adapter->extractAssetRefs($article['content_payload']);
        $context = new DraftContext(
            draftId: null,
            mode: $article['mode'],
            targetIdentifier: $article['target_identifier'] ?? null,
            contentPayload: $article['content_payload'],
            assetRefs: $assetRefs,
            version: 0,
            clientKeyId: $this->clientContext($request)->clientKeyId,
            status: 'working',
        );

        return response()->json($this->validateContext($context, $data['mode'] ?? 'draft')->toArray($data['mode'] ?? 'draft'));
    }

    public function drafts(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 20), (int) config('post2site.drafts.per_page_max', 100));
        $query = Post2SiteDraft::query()->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json([
            'data' => $query->limit($perPage)->get()->map(fn (Post2SiteDraft $draft): array => $this->draftEnvelope($draft))->values(),
            'next_cursor' => null,
        ]);
    }

    public function storeDraft(Request $request): JsonResponse
    {
        $data = $this->validateDraftPayload($request, creating: true);
        $client = $this->clientContext($request);
        $assetRefs = $this->adapter->extractAssetRefs($data['content_payload']);
        $assetBlockers = $this->assetRefBlockers($assetRefs);

        if ($assetBlockers !== []) {
            return $this->validationResult('draft', false, $assetBlockers);
        }

        $draft = Post2SiteDraft::query()->create([
            'draft_id' => 'draft_'.Str::ulid()->toString(),
            'mode' => $data['mode'],
            'target_identifier' => $data['target_identifier'] ?? null,
            'status' => 'draft',
            'content_payload' => $data['content_payload'],
            'validation_state' => null,
            'asset_refs' => $assetRefs,
            'version' => 1,
            'client_key_id' => $client->clientKeyId,
            'client_name' => $client->clientName,
            'client_metadata' => $data['client_metadata'] ?? [],
        ]);

        $this->bindAssetsToDraft($assetRefs, $draft->draft_id);

        $validation = $this->validateContext($this->draftContext($draft), 'draft');
        $draft->forceFill(['validation_state' => $validation->toArray('draft')])->save();

        return response()->json($this->draftEnvelope($draft->refresh()), 201);
    }

    public function showDraft(string $draft_id): JsonResponse
    {
        $draft = Post2SiteDraft::query()->find($draft_id);

        return $draft === null
            ? $this->error('not_found', 'The requested draft was not found.', 404)
            : response()->json($this->draftEnvelope($draft));
    }

    public function updateDraft(Request $request, string $draft_id): JsonResponse
    {
        $draft = Post2SiteDraft::query()->find($draft_id);
        if ($draft === null) {
            return $this->error('not_found', 'The requested draft was not found.', 404);
        }

        if ($draft->status === 'published') {
            return $this->error('invalid_transition', 'Published drafts cannot be updated.', 409);
        }

        $data = $this->validateDraftPayload($request, creating: false);
        $nextPayload = $data['content_payload'] ?? $draft->content_payload;
        $this->rejectForbiddenPayloadFields($nextPayload);
        $assetRefs = $this->adapter->extractAssetRefs($nextPayload);
        $assetBlockers = $this->assetRefBlockers($assetRefs);

        if ($assetBlockers !== []) {
            return $this->validationResult('draft', false, $assetBlockers);
        }

        $draft->forceFill([
            'mode' => $data['mode'] ?? $draft->mode,
            'target_identifier' => array_key_exists('target_identifier', $data) ? $data['target_identifier'] : $draft->target_identifier,
            'content_payload' => $nextPayload,
            'asset_refs' => $assetRefs,
            'client_metadata' => $data['client_metadata'] ?? $draft->client_metadata,
            'version' => $draft->version + 1,
        ])->save();

        $this->bindAssetsToDraft($assetRefs, $draft->draft_id);

        $validation = $this->validateContext($this->draftContext($draft->refresh()), 'draft');
        $draft->forceFill(['validation_state' => $validation->toArray('draft')])->save();

        return response()->json($this->draftEnvelope($draft->refresh()));
    }

    public function validateDraft(Request $request, string $draft_id): JsonResponse
    {
        $draft = Post2SiteDraft::query()->find($draft_id);
        if ($draft === null) {
            return $this->error('not_found', 'The requested draft was not found.', 404);
        }

        $mode = $request->input('mode', 'draft');
        if (! in_array($mode, ['draft', 'publish'], true)) {
            return $this->error('validation_failed', 'The selected validation mode is invalid.', 422, 'mode');
        }

        $validation = $this->validateContext($this->draftContext($draft), $mode);
        $draft->forceFill(['validation_state' => $validation->toArray($mode)])->save();

        return response()->json($validation->toArray($mode));
    }

    public function previewDraft(string $draft_id): JsonResponse
    {
        $draft = Post2SiteDraft::query()->find($draft_id);
        if ($draft === null) {
            return $this->error('not_found', 'The requested draft was not found.', 404);
        }

        return response()->json($this->adapter->previewDraft($this->draftContext($draft))->toArray($draft->draft_id));
    }

    public function storeAsset(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'draft_id' => ['nullable', 'string'],
            'purpose' => ['required', 'string'],
            'filename' => ['required', 'string'],
            'content_type' => ['required', 'string'],
            'data_base64' => ['required', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $limits = $this->adapter->capabilities()['host_schema']['asset_limits'] ?? [];
        if (isset($limits['allowed_purposes']) && is_array($limits['allowed_purposes']) && ! in_array($data['purpose'], $limits['allowed_purposes'], true)) {
            return $this->error('validation_failed', 'The selected asset purpose is not allowed.', 422, 'purpose');
        }

        if (isset($limits['allowed_content_types']) && is_array($limits['allowed_content_types']) && ! in_array($data['content_type'], $limits['allowed_content_types'], true)) {
            return $this->error('validation_failed', 'The selected content type is not allowed.', 422, 'content_type');
        }

        $bytes = base64_decode($data['data_base64'], true);
        if ($bytes === false) {
            return $this->error('validation_failed', 'The selected asset data is not valid base64.', 422, 'data_base64');
        }

        if (isset($limits['max_bytes']) && strlen($bytes) > (int) $limits['max_bytes']) {
            return $this->error('validation_failed', 'The selected asset is too large.', 422, 'data_base64');
        }

        $client = $this->clientContext($request);
        $draft = isset($data['draft_id']) ? Post2SiteDraft::query()->find($data['draft_id']) : null;
        if (isset($data['draft_id']) && $draft === null) {
            return $this->error('not_found', 'The requested draft was not found.', 404, 'draft_id');
        }

        try {
            $result = $this->adapter->storeSelectedAsset(
                new AssetUpload(
                    draftId: $data['draft_id'] ?? null,
                    purpose: $data['purpose'],
                    filename: $data['filename'],
                    contentType: $data['content_type'],
                    dataBase64: $data['data_base64'],
                    metadata: $data['metadata'] ?? [],
                ),
                $client,
                $draft ? $this->draftContext($draft) : null,
            );
        } catch (InvalidArgumentException $exception) {
            return $this->error('validation_failed', $exception->getMessage(), 422, 'data_base64');
        }

        Post2SiteAsset::query()->updateOrCreate(
            ['asset_id' => $result->assetId],
            [
                'draft_id' => $data['draft_id'] ?? null,
                'client_key_id' => $client->clientKeyId,
                'purpose' => $result->purpose,
                'filename' => $data['filename'],
                'content_type' => $result->contentType,
                'byte_size' => strlen($bytes),
                'url' => $result->url,
                'width' => $result->width,
                'height' => $result->height,
                'validation' => $result->validation,
                'metadata' => $result->metadata,
            ],
        );

        return response()->json($result->toArray(), 201);
    }

    public function publishDraft(Request $request, string $draft_id): JsonResponse
    {
        $data = $this->validate($request, [
            'publish_confirmed' => ['required', 'accepted'],
            'acknowledged_warnings' => ['nullable', 'array'],
            'acknowledged_warnings.*' => ['string'],
        ]);

        $draft = Post2SiteDraft::query()->find($draft_id);
        if ($draft === null) {
            return $this->error('not_found', 'The requested draft was not found.', 404);
        }

        if ($draft->status === 'published') {
            return $this->error('invalid_transition', 'Published drafts cannot be published again.', 409);
        }

        $assetBlockers = $this->assetRefBlockers($draft->asset_refs ?? []);
        if ($assetBlockers !== []) {
            return $this->validationResult('publish', false, $assetBlockers);
        }

        $context = $this->draftContext($draft);
        $validation = $this->validateContext($context, 'publish');
        if ($validation->blockers !== []) {
            $draft->forceFill(['validation_state' => $validation->toArray('publish')])->save();

            return response()->json($validation->toArray('publish'), 422);
        }

        $result = $this->adapter->publishDraft($context, new PublishRequest(
            publishConfirmed: true,
            acknowledgedWarnings: $data['acknowledged_warnings'] ?? [],
        ));

        $response = $result->toArray();
        $draft->forceFill([
            'status' => 'published',
            'published_at' => now(),
            'publish_confirmation_state' => [
                'publish_confirmed' => true,
                'acknowledged_warnings' => $data['acknowledged_warnings'] ?? [],
                'confirmed_at' => now()->toJSON(),
            ],
            'publish_result' => $response,
            'validation_state' => $validation->toArray('publish'),
        ])->save();

        return response()->json($response);
    }

    private function validateDraftPayload(Request $request, bool $creating): array
    {
        $rules = [
            'mode' => [$creating ? 'required' : 'nullable', 'in:'.implode(',', config('post2site.drafts.modes'))],
            'target_identifier' => ['nullable', 'string'],
            'content_payload' => [$creating ? 'required' : 'nullable', 'array'],
            'client_metadata' => ['nullable', 'array'],
        ];

        $data = $this->validate($request, $rules);

        if (isset($data['content_payload'])) {
            $this->rejectForbiddenPayloadFields($data['content_payload']);
        }

        return $data;
    }

    private function validate(Request $request, array $rules): array
    {
        return Validator::make($request->all(), $rules)->validate();
    }

    private function rejectForbiddenPayloadFields(array $contentPayload): void
    {
        foreach (config('post2site.drafts.forbidden_payload_fields', []) as $field) {
            if (array_key_exists($field, $contentPayload)) {
                throw ValidationException::withMessages([
                    "content_payload.{$field}" => 'This field is reserved for the host adapter and is not accepted by the MCP contract.',
                ]);
            }
        }
    }

    private function clientContext(Request $request): ClientContext
    {
        return new ClientContext(
            clientKeyId: (string) $request->attributes->get('post2site_client_key_id', 'unknown'),
            clientName: (string) $request->attributes->get('post2site_client_name', 'unknown'),
            requestId: (string) Str::uuid(),
            authenticatedAt: now(),
        );
    }

    private function draftContext(Post2SiteDraft $draft): DraftContext
    {
        return new DraftContext(
            draftId: $draft->draft_id,
            mode: $draft->mode,
            targetIdentifier: $draft->target_identifier,
            contentPayload: $draft->content_payload ?? [],
            assetRefs: $draft->asset_refs ?? [],
            version: $draft->version,
            clientKeyId: $draft->client_key_id,
            status: $draft->status,
        );
    }

    private function draftEnvelope(Post2SiteDraft $draft): array
    {
        return [
            'draft_id' => $draft->draft_id,
            'mode' => $draft->mode,
            'target_identifier' => $draft->target_identifier,
            'status' => $draft->status,
            'version' => $draft->version,
            'content_payload' => $draft->content_payload,
            'asset_refs' => $draft->asset_refs ?? [],
            'validation_state' => $draft->validation_state,
            'client_metadata' => $draft->client_metadata ?? [],
            'created_at' => $draft->created_at?->toJSON(),
            'updated_at' => $draft->updated_at?->toJSON(),
            'published_at' => $draft->published_at?->toJSON(),
            'publish_result' => $draft->publish_result,
        ];
    }

    private function validateContext(DraftContext $context, string $mode)
    {
        $assetBlockers = $this->assetRefBlockers($context->assetRefs);
        $host = $this->adapter->validateContentPayload($context, $context->contentPayload, $mode);

        if ($assetBlockers === []) {
            return $host;
        }

        return new ValidationResult(
            publishable: false,
            blockers: array_merge($assetBlockers, $host->blockers),
            warnings: $host->warnings,
            normalizedPayload: $host->normalizedPayload,
            hostMetadata: $host->hostMetadata,
        );
    }

    private function assetRefBlockers(array $assetRefs): array
    {
        $blockers = [];

        foreach ($assetRefs as $assetId) {
            $asset = Post2SiteAsset::query()->find($assetId);
            if ($asset === null) {
                $blockers[] = $this->issue('asset_missing', 'content_payload', 'Referenced asset does not exist.', ['asset_id' => $assetId]);

                continue;
            }

        }

        return $blockers;
    }

    private function bindAssetsToDraft(array $assetRefs, string $draftId): void
    {
        Post2SiteAsset::query()
            ->whereIn('asset_id', $assetRefs)
            ->whereNull('draft_id')
            ->update(['draft_id' => $draftId]);
    }

    private function validationResult(string $mode, bool $publishable, array $blockers): JsonResponse
    {
        return response()->json([
            'mode' => $mode,
            'publishable' => $publishable,
            'blockers' => $blockers,
            'warnings' => [],
            'normalized_payload' => null,
            'host_metadata' => [],
        ], 422);
    }

    private function issue(string $code, string $field, string $message, array $details = []): array
    {
        return [
            'code' => $code,
            'field' => $field,
            'severity' => 'blocker',
            'source' => 'package',
            'message' => $message,
            'details' => $details,
        ];
    }

    private function error(string $code, string $message, int $status, ?string $field = null, array $details = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'field' => $field,
                'retryable' => false,
                'details' => $details,
            ],
        ], $status);
    }
}
