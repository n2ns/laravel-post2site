<?php

namespace N2ns\LaravelPost2Site\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use N2ns\LaravelPost2Site\Contracts\PostRepository;
use N2ns\LaravelPost2Site\Contracts\PublicUrlResolver;
use N2ns\LaravelPost2Site\Data\PostData;
use N2ns\LaravelPost2Site\Data\PublishedPostData;
use N2ns\LaravelPost2Site\Models\Post2SitePost;
use N2ns\LaravelPost2Site\Models\Post2SitePostTranslation;

class EloquentPostRepository implements PostRepository
{
    public function __construct(
        private readonly PublicUrlResolver $urls,
    ) {}

    public function listPosts(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $max = (int) config('post2site.content.per_page_max', 100);
        $perPage = max(1, min($perPage, $max));

        $query = Post2SitePost::query()
            ->with('translations')
            ->latest();

        if (array_key_exists('status', $filters) && $filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        if (array_key_exists('type', $filters) && $filters['type'] !== null) {
            $query->where('type', $filters['type']);
        }

        if (array_key_exists('content_scope', $filters)) {
            $filters['content_scope'] === null || $filters['content_scope'] === ''
                ? $query->whereNull('content_scope')
                : $query->where('content_scope', $filters['content_scope']);
        }

        if (! empty($filters['q'])) {
            $search = $filters['q'];
            $useFullText = in_array($query->getConnection()->getDriverName(), ['mysql', 'mariadb'], true);

            $query->whereHas('translations', function ($query) use ($search, $useFullText): void {
                if ($useFullText) {
                    // Boolean mode avoids natural-language mode's 50%-of-rows
                    // threshold (which returns nothing on small tables) and
                    // supports operators in the query string.
                    $query->whereFullText(['title', 'content'], $search, ['mode' => 'boolean']);

                    return;
                }

                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $paginator = $query->paginate($perPage);
        $locale = $filters['locale'] ?? config('post2site.content.default_locale', 'en');
        $paginator->getCollection()->transform(fn (Post2SitePost $post): PostData => $this->toPostData($post, $locale));

        return $paginator;
    }

    public function createPost(array $data): PostData
    {
        $locale = $data['locale'] ?? config('post2site.content.default_locale', 'en');

        $post = DB::transaction(function () use ($data, $locale): Post2SitePost {
            $post = Post2SitePost::query()->create([
                'type' => $data['type'] ?? 'technical',
                'content_scope' => $data['content_scope'] ?? null,
                'status' => 'draft',
                'slug' => $data['slug'],
                'thumbnail' => $data['thumbnail'] ?? null,
            ]);

            $this->upsertTranslation($post, $locale, $data);

            return $post;
        });

        return $this->toPostData($post->refresh()->load('translations'), $locale);
    }

    public function findPostByIdOrSlug(string $idOrSlug): PostData
    {
        $post = is_numeric($idOrSlug)
            ? Post2SitePost::query()->with('translations')->findOrFail($idOrSlug)
            : Post2SitePost::query()->with('translations')->where('slug', $idOrSlug)->firstOrFail();

        return $this->toPostData($post, config('post2site.content.default_locale', 'en'));
    }

    public function updatePost(string|int $id, array $data): PostData
    {
        $post = Post2SitePost::query()->with('translations')->findOrFail($id);
        $locale = $data['locale'] ?? config('post2site.content.default_locale', 'en');

        DB::transaction(function () use ($post, $locale, $data): void {
            foreach (['type', 'content_scope', 'slug', 'thumbnail'] as $field) {
                if (array_key_exists($field, $data)) {
                    $post->{$field} = $data[$field];
                }
            }

            $post->save();

            // Only touch translations when the request actually carries translatable
            // content; otherwise a metadata-only update would create a blank row.
            if ($this->hasTranslationFields($data)) {
                $this->upsertTranslation($post, $locale, $data);
            }
        });

        return $this->toPostData($post->refresh()->load('translations'), $locale);
    }

    public function markPublished(string|int $id, PublishedPostData $published): PostData
    {
        $post = Post2SitePost::query()->with('translations')->findOrFail($id);
        $post->forceFill([
            'status' => 'published',
            'published_at' => $published->publishedAt ?? now(),
            'target_type' => $published->targetType,
            'target_id' => (string) $published->targetId,
            'target_link' => $published->link,
        ])->save();

        return $this->toPostData($post->refresh()->load('translations'), config('post2site.content.default_locale', 'en'));
    }

    private function hasTranslationFields(array $data): bool
    {
        return array_key_exists('title', $data)
            || array_key_exists('excerpt', $data)
            || array_key_exists('content', $data);
    }

    private function upsertTranslation(Post2SitePost $post, string $locale, array $data): void
    {
        $translation = $post->translations()->firstOrNew(['locale' => $locale]);

        foreach (['title', 'excerpt', 'content'] as $field) {
            if (array_key_exists($field, $data)) {
                $translation->{$field} = $data[$field] ?? '';
            }
        }

        if (! $translation->exists) {
            $translation->title ??= '';
            $translation->content ??= '';
        }

        $translation->save();
    }

    private function toPostData(Post2SitePost $post, string $locale): PostData
    {
        $translation = $this->translationFor($post, $locale);
        $availableLocales = $post->translations->pluck('locale')->values()->all();
        $base = new PostData(
            id: $post->id,
            slug: $post->slug,
            type: $post->type,
            status: $post->status,
            contentScope: $post->content_scope,
            locale: $translation?->locale ?? $locale,
            title: $translation?->title ?? '',
            excerpt: $translation?->excerpt,
            content: $translation?->content,
            thumbnail: $post->thumbnail,
            publishedAt: $post->published_at,
            updatedAt: $post->updated_at,
            availableLocales: $availableLocales,
            link: $post->target_link,
        );

        // A stored host link wins; an empty link (e.g. review mode) falls back to
        // the configured public_url pattern via the resolver.
        if (filled($base->link) && $base->status === 'published' && $base->publishedAt?->lte(now())) {
            return $base;
        }

        return new PostData(
            id: $base->id,
            slug: $base->slug,
            type: $base->type,
            status: $base->status,
            contentScope: $base->contentScope,
            locale: $base->locale,
            title: $base->title,
            excerpt: $base->excerpt,
            content: $base->content,
            thumbnail: $base->thumbnail,
            publishedAt: $base->publishedAt,
            updatedAt: $base->updatedAt,
            availableLocales: $base->availableLocales,
            link: $this->urls->resolve($base),
        );
    }

    private function translationFor(Post2SitePost $post, string $locale): ?Post2SitePostTranslation
    {
        return $post->translations->firstWhere('locale', $locale)
            ?? $post->translations->firstWhere('locale', config('post2site.content.default_locale', 'en'))
            ?? $post->translations->first();
    }
}
