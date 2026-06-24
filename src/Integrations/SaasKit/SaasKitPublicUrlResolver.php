<?php

namespace N2ns\LaravelPost2Site\Integrations\SaasKit;

use N2ns\LaravelPost2Site\Contracts\PublicUrlResolver;
use N2ns\LaravelPost2Site\Data\PostData;

class SaasKitPublicUrlResolver implements PublicUrlResolver
{
    public function resolve(PostData $post): ?string
    {
        if ($post->status !== 'published' || ! $post->publishedAt?->lte(now())) {
            return null;
        }

        $productCode = $this->productCode($post->contentScope);
        if ($post->contentScope !== null && ($post->type !== 'guide' || $productCode === null)) {
            return null;
        }

        $localePrefix = $this->localePrefix($post->locale);
        $path = $productCode !== null
            ? "{$localePrefix}/{$productCode}/guides/{$post->slug}"
            : "{$localePrefix}/blog/{$post->slug}";

        return rtrim((string) config('app.url'), '/').$path;
    }

    private function productCode(?string $contentScope): ?string
    {
        if (! is_string($contentScope) || ! str_starts_with($contentScope, 'product:')) {
            return null;
        }

        return substr($contentScope, strlen('product:')) ?: null;
    }

    private function localePrefix(string $locale): string
    {
        $defaultLocale = (string) config('post2site.integrations.saas_kit.default_locale', 'en');

        return $locale === $defaultLocale ? '' : '/'.$locale;
    }
}
