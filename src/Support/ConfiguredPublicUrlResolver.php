<?php

namespace N2ns\LaravelPost2Site\Support;

use N2ns\LaravelPost2Site\Contracts\PublicUrlResolver;
use N2ns\LaravelPost2Site\Data\PostData;

class ConfiguredPublicUrlResolver implements PublicUrlResolver
{
    public function resolve(PostData $post): ?string
    {
        if ($post->status !== 'published' || ! $post->publishedAt?->lte(now())) {
            return null;
        }

        $locale = $post->locale ?: config('post2site.content.default_locale', 'en');
        $pattern = config('post2site.public_url.pattern', '/{slug}');

        return PublicUrlPattern::build($pattern, $locale, $post->slug, $post->contentScope);
    }
}
