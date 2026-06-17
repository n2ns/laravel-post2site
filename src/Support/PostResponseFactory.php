<?php

namespace N2ns\LaravelPost2Site\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use N2ns\LaravelPost2Site\Data\PostData;

class PostResponseFactory
{
    public function paginated(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->getCollection()->map(fn (PostData $post): array => $this->post($post))->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function envelope(PostData $post, array $meta = []): array
    {
        return [
            'blog_post' => $this->post($post),
            'available_locales' => $this->availableLocales($post),
            'missing_locales' => $this->missingLocales($post),
            'next_actions' => $this->nextActions($post),
            'meta' => (object) $meta,
        ];
    }

    public function post(PostData $post): array
    {
        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'type' => $post->type,
            'status' => $post->status,
            'content_scope' => $post->contentScope,
            'locale' => $post->locale,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'thumbnail' => $post->thumbnail,
            'published_at' => $post->publishedAt?->toISOString(),
            'updated_at' => $post->updatedAt?->toISOString(),
            'link' => $post->link,
        ];
    }

    public function availableLocales(PostData $post): array
    {
        return $post->availableLocales;
    }

    public function missingLocales(PostData $post): array
    {
        return array_values(array_diff(config('post2site.content.locales', []), $post->availableLocales));
    }

    public function nextActions(PostData $post): array
    {
        if ($post->status === 'draft') {
            return ['update_draft', 'publish'];
        }

        return [];
    }
}
