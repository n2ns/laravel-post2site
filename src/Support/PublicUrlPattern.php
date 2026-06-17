<?php

namespace N2ns\LaravelPost2Site\Support;

/**
 * Builds an absolute public URL from a host-defined pattern. The package makes
 * no assumptions about content categories; the pattern decides everything.
 *
 * Placeholders: {slug} {locale} {content_scope} {key}
 * {key} is the part after ":" in content_scope (empty when unscoped).
 */
class PublicUrlPattern
{
    public static function build(string $pattern, string $locale, string $slug, ?string $contentScope): string
    {
        $key = ($contentScope !== null && str_contains($contentScope, ':'))
            ? explode(':', $contentScope, 2)[1]
            : '';

        $path = strtr($pattern, [
            '{locale}' => $locale,
            '{slug}' => $slug,
            '{content_scope}' => $contentScope ?? '',
            '{key}' => $key,
        ]);

        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }
}
