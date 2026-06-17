<?php

namespace N2ns\LaravelPost2Site\Support;

use Illuminate\Support\Str;
use N2ns\LaravelPost2Site\Data\PostData;

class PublicPostMetadata
{
    public function forPost(PostData $post): array
    {
        return [
            'canonical' => $post->link,
            'title' => $post->title,
            'description' => Str::limit(strip_tags($post->excerpt ?? $post->content ?? ''), (int) config('post2site.seo.metadata.description_max_length', 160), ''),
            'open_graph' => [
                'title' => $post->title,
                'description' => $post->excerpt,
                'image' => $post->thumbnail ?? config('post2site.seo.metadata.fallback_image'),
            ],
            'json_ld' => [
                '@context' => 'https://schema.org',
                '@type' => config('post2site.seo.structured_data.article_type', 'Article'),
                'headline' => $post->title,
                'datePublished' => $post->publishedAt?->toISOString(),
                'url' => $post->link,
            ],
        ];
    }
}
