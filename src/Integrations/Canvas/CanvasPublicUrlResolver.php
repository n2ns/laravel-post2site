<?php

namespace N2ns\LaravelPost2Site\Integrations\Canvas;

use N2ns\LaravelPost2Site\Contracts\PublicUrlResolver;
use N2ns\LaravelPost2Site\Data\PostData;
use N2ns\LaravelPost2Site\Support\PublicUrlPattern;

class CanvasPublicUrlResolver implements PublicUrlResolver
{
    public function resolve(PostData $post): ?string
    {
        if ($post->status !== 'published' || ! $post->publishedAt?->lte(now())) {
            return null;
        }

        return PublicUrlPattern::build(
            (string) config('post2site.integrations.canvas.public_url_pattern', '/blog/{slug}'),
            $post->locale,
            $post->slug,
            $post->contentScope,
        );
    }
}
