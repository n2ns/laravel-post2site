<?php

namespace App\Post2Site;

use App\Models\BlogPost;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
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

class BlogPostAdapter implements Post2SiteAdapter
{
    public function capabilities(): array
    {
        return [
            'host_profile' => 'simple-text-blog',
            'host_schema' => [
                'content_fields' => [
                    'title' => ['type' => 'string', 'field_path' => 'content_payload.title'],
                    'slug' => ['type' => 'string', 'field_path' => 'content_payload.slug'],
                    'body' => ['type' => 'string', 'field_path' => 'content_payload.body'],
                ],
                'asset_limits' => ['allowed_purposes' => [], 'allowed_content_types' => [], 'max_bytes' => 0],
                'inventory' => [
                    'filterable_fields' => ['q'],
                    'summary_fields' => ['published_at'],
                    'stats_dimensions' => ['status'],
                ],
            ],
            'host_metadata' => ['supported_modes' => ['create'], 'body_format' => 'plain_text'],
        ];
    }

    public function siteContext(): array
    {
        return [
            'summary' => 'A small text-only blog. Public articles live at /posts/{slug}.',
            'host_metadata' => ['base_url' => url('/'), 'target_identifier' => 'The article slug.'],
        ];
    }

    public function editorialPolicy(): array
    {
        return [
            'policy_version' => 'simple-blog-1',
            'content_model' => [
                'required_fields' => ['content_payload.title', 'content_payload.slug', 'content_payload.body'],
                'optional_fields' => [],
                'field_paths' => [
                    'title' => 'content_payload.title',
                    'slug' => 'content_payload.slug',
                    'body' => 'content_payload.body',
                ],
            ],
            'source_rules' => ['Use plain text; HTML and Markdown are displayed literally.'],
            'cta_rules' => [],
            'prohibited_claims' => [],
            'publish_blockers' => ['Missing fields, duplicate slugs, uploads, and modes other than create.'],
            'host_metadata' => ['slug_format' => 'Lowercase words or numbers separated by hyphens.'],
        ];
    }

    public function inventory(array $query): InventoryResult
    {
        Validator::make($query, [
            'q' => ['sometimes', 'string'],
            'target_identifier' => ['sometimes', 'string'],
            'cursor' => ['sometimes', 'integer', 'min:0'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ])->validate();

        $posts = BlogPost::query()->orderBy('id');
        if (isset($query['target_identifier'])) {
            $posts->where('slug', $query['target_identifier']);
        }
        if (isset($query['q'])) {
            $posts->where('title', 'like', '%'.$query['q'].'%');
        }
        if (isset($query['cursor'])) {
            $posts->where('id', '>', $query['cursor']);
        }

        $limit = (int) ($query['per_page'] ?? 20);
        $page = $posts->limit($limit + 1)->get();
        $items = $page->take($limit);

        return new InventoryResult(
            items: $items->map(fn (BlogPost $post): array => $this->resource($post))->values()->all(),
            nextCursor: $page->count() > $limit ? (string) $items->last()->id : null,
        );
    }

    public function inventoryStats(array $query): array
    {
        return ['dimensions' => ['status' => ['published' => BlogPost::query()->count()]], 'host_metadata' => []];
    }

    public function findDuplicates(array $payload): DuplicateResult
    {
        $slug = $payload['content_payload']['slug'] ?? null;
        $post = is_string($slug) ? BlogPost::query()->where('slug', $slug)->first() : null;

        return new DuplicateResult(
            issues: $post ? [$this->issue('slug_taken', 'content_payload.slug', 'This slug is already published.')] : [],
            candidates: $post ? [$this->resource($post)] : [],
        );
    }

    public function validateContentPayload(DraftContext $context, array $contentPayload, string $mode): ValidationResult
    {
        $validator = Validator::make(['content_payload' => $contentPayload], [
            'content_payload' => ['required', 'array:title,slug,body'],
            'content_payload.title' => ['required', 'string', 'max:255'],
            'content_payload.slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:blog_posts,slug'],
            'content_payload.body' => ['required', 'string', 'max:10000'],
        ]);

        $blockers = [];
        foreach ($validator->errors()->messages() as $field => $messages) {
            $blockers[] = $this->issue('invalid_content', $field, implode(' ', $messages));
        }
        if ($context->mode !== 'create') {
            $blockers[] = $this->issue('unsupported_mode', 'mode', 'This example only creates new articles.');
        }
        if ($context->targetIdentifier !== null && $context->targetIdentifier !== ($contentPayload['slug'] ?? null)) {
            $blockers[] = $this->issue('slug_mismatch', 'target_identifier', 'Use the same value as content_payload.slug.');
        }

        return new ValidationResult(publishable: $blockers === [], blockers: $blockers);
    }

    public function extractAssetRefs(array $contentPayload): array
    {
        return [];
    }

    public function storeSelectedAsset(AssetUpload $upload, ClientContext $clientContext, ?DraftContext $draftContext = null): AssetResult
    {
        throw new InvalidArgumentException('This text-only example does not support asset uploads.');
    }

    public function previewDraft(DraftContext $context): PreviewResult
    {
        $expires = now()->addMinutes(30);

        return new PreviewResult(
            previewUrl: URL::temporarySignedRoute('post2site-blog.preview', $expires, ['draft' => $context->draftId]),
            expiresAt: $expires,
        );
    }

    public function publishDraft(DraftContext $context, PublishRequest $request): PublishResult
    {
        if (! $request->publishConfirmed) {
            throw new InvalidArgumentException('Publishing requires explicit confirmation.');
        }

        $post = BlogPost::query()->create([
            'title' => $context->contentPayload['title'],
            'slug' => $context->contentPayload['slug'],
            'body' => $context->contentPayload['body'],
        ]);

        return new PublishResult(
            resourceId: (string) $post->id,
            status: 'published',
            canonicalUrl: route('post2site-blog.show', ['slug' => $post->slug]),
        );
    }

    private function resource(BlogPost $post): array
    {
        return [
            'id' => (string) $post->id,
            'target_identifier' => $post->slug,
            'display_label' => $post->title,
            'urls' => ['canonical' => route('post2site-blog.show', ['slug' => $post->slug]), 'localized' => []],
            'summary' => ['published_at' => $post->created_at->toJSON()],
            'host_fields' => ['title' => $post->title, 'slug' => $post->slug, 'body' => $post->body],
        ];
    }

    private function issue(string $code, string $field, string $message): array
    {
        return ['code' => $code, 'field' => $field, 'message' => $message, 'severity' => 'blocker', 'source' => 'host'];
    }
}
