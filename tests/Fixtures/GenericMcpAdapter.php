<?php

namespace N2ns\LaravelPost2Site\Tests\Fixtures;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use N2ns\LaravelPost2Site\Contracts\Post2SiteAdapter;
use N2ns\LaravelPost2Site\Data\AssetResult;
use N2ns\LaravelPost2Site\Data\AssetUpload;
use N2ns\LaravelPost2Site\Data\ClientContext;
use N2ns\LaravelPost2Site\Data\DraftContext;
use N2ns\LaravelPost2Site\Data\DuplicateResult;
use N2ns\LaravelPost2Site\Data\InventoryResult;
use N2ns\LaravelPost2Site\Data\PreviewResult;
use N2ns\LaravelPost2Site\Data\PublishRequest;
use N2ns\LaravelPost2Site\Data\PublishResult;
use N2ns\LaravelPost2Site\Data\ValidationResult;

class GenericMcpAdapter implements Post2SiteAdapter
{
    public static int $publishCount = 0;

    public function capabilities(): array
    {
        return [
            'host_profile' => 'test-host',
            'host_schema' => [
                'asset_limits' => [
                    'allowed_content_types' => ['image/webp'],
                    'allowed_purposes' => ['thumbnail'],
                    'max_bytes' => 1024,
                ],
                'content_fields' => [
                    'thumbnail_asset_id' => [
                        'type' => 'asset_id|null',
                        'field_path' => 'content_payload.thumbnail_asset_id',
                    ],
                ],
                'inventory' => [
                    'filterable_fields' => ['q'],
                    'summary_fields' => ['display_label'],
                    'stats_dimensions' => ['status'],
                ],
            ],
        ];
    }

    public function siteContext(): array
    {
        return ['summary' => 'Test host'];
    }

    public function editorialPolicy(): array
    {
        return [
            'policy_version' => 'test',
            'content_model' => [
                'required_fields' => ['content_payload.body'],
                'optional_fields' => ['content_payload.thumbnail_asset_id'],
                'field_paths' => ['body' => 'content_payload.body'],
            ],
            'source_rules' => [],
            'cta_rules' => [],
            'prohibited_claims' => [],
            'publish_blockers' => [],
            'host_metadata' => [],
        ];
    }

    public function inventory(array $query): InventoryResult
    {
        if (($query['target_identifier'] ?? null) === 'existing') {
            return new InventoryResult(items: [[
                'id' => 'resource_existing',
                'target_identifier' => 'existing',
                'display_label' => 'Existing resource',
                'urls' => ['canonical' => 'https://example.com/existing', 'localized' => []],
                'summary' => [],
                'host_fields' => [],
            ]]);
        }

        if (($query['target_identifier'] ?? null) === 'guides/example-post') {
            return new InventoryResult(items: [[
                'id' => 'resource_guides_example_post',
                'target_identifier' => 'guides/example-post',
                'display_label' => 'Nested example post',
                'urls' => ['canonical' => 'https://example.com/guides/example-post', 'localized' => []],
                'summary' => [],
                'host_fields' => [],
            ]]);
        }

        return new InventoryResult;
    }

    public function inventoryStats(array $query): array
    {
        return ['dimensions' => ['status' => ['draft' => 0]], 'host_metadata' => []];
    }

    public function findDuplicates(array $payload): DuplicateResult
    {
        return new DuplicateResult;
    }

    public function validateContentPayload(DraftContext $context, array $contentPayload, string $mode): ValidationResult
    {
        if (($contentPayload['body'] ?? '') === '') {
            return new ValidationResult(false, [[
                'code' => 'body_missing',
                'field' => 'content_payload.body',
                'severity' => 'blocker',
                'source' => 'host',
                'message' => 'Body is required.',
            ]]);
        }

        return new ValidationResult(true);
    }

    public function extractAssetRefs(array $contentPayload): array
    {
        return isset($contentPayload['thumbnail_asset_id'])
            ? [$contentPayload['thumbnail_asset_id']]
            : [];
    }

    public function storeSelectedAsset(AssetUpload $upload, ClientContext $clientContext, ?DraftContext $draftContext = null): AssetResult
    {
        if ($upload->filename === 'reject.webp') {
            throw new InvalidArgumentException('Selected asset was rejected by the host adapter.');
        }

        return new AssetResult(
            assetId: 'asset_'.sha1($upload->filename),
            purpose: $upload->purpose,
            url: 'https://example.com/assets/'.rawurlencode($upload->filename),
            contentType: $upload->contentType,
            width: 1600,
            height: 900,
            metadata: $upload->metadata,
        );
    }

    public function previewDraft(DraftContext $context): PreviewResult
    {
        return new PreviewResult(
            previewUrl: 'https://example.com/preview/'.$context->draftId,
            previewUrls: ['en' => 'https://example.com/preview/'.$context->draftId.'?locale=en'],
            expiresAt: Carbon::parse('2026-07-01 12:00:00'),
        );
    }

    public function publishDraft(DraftContext $context, PublishRequest $request): PublishResult
    {
        self::$publishCount++;

        return new PublishResult(
            resourceId: 'resource_'.$context->draftId,
            status: 'published',
            canonicalUrl: 'https://example.com/resources/'.$context->targetIdentifier,
            localizedUrls: ['en' => 'https://example.com/resources/'.$context->targetIdentifier],
            hostMetadata: ['publish_count' => self::$publishCount],
        );
    }
}
