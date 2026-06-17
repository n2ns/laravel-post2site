<?php

namespace N2ns\LaravelPost2Site\Tests\Feature;

use Carbon\CarbonInterface;
use N2ns\LaravelPost2Site\Data\PostData;
use N2ns\LaravelPost2Site\Support\ConfiguredPublicUrlResolver;
use N2ns\LaravelPost2Site\Support\PublicUrlPattern;
use N2ns\LaravelPost2Site\Tests\TestCase;

class PublicUrlResolutionTest extends TestCase
{
    public function test_pattern_substitutes_placeholders_into_absolute_url(): void
    {
        $this->assertSame(
            'https://example.com/en/evisa/guides/apply-online',
            PublicUrlPattern::build('/{locale}/{key}/guides/{slug}', 'en', 'apply-online', 'product:evisa'),
        );
    }

    public function test_pattern_handles_unscoped_and_full_scope_placeholders(): void
    {
        $this->assertSame(
            'https://example.com/en/blog/hello',
            PublicUrlPattern::build('/{locale}/blog/{slug}', 'en', 'hello', null),
        );

        $this->assertSame(
            'https://example.com/product:x/hello',
            PublicUrlPattern::build('/{content_scope}/{slug}', 'en', 'hello', 'product:x'),
        );
    }

    public function test_resolver_returns_null_for_draft_or_future_posts(): void
    {
        $resolver = new ConfiguredPublicUrlResolver;

        $this->assertNull($resolver->resolve($this->makePost('draft', null)));
        $this->assertNull($resolver->resolve($this->makePost('published', now()->addDay())));
    }

    public function test_resolver_uses_configured_pattern_for_published_posts(): void
    {
        config()->set('post2site.public_url.pattern', '/{locale}/{slug}');

        $this->assertSame(
            'https://example.com/en/hello-world',
            (new ConfiguredPublicUrlResolver)->resolve($this->makePost('published', now()->subMinute())),
        );
    }

    private function makePost(string $status, ?CarbonInterface $publishedAt): PostData
    {
        return new PostData(
            id: 1,
            slug: 'hello-world',
            type: 'technical',
            status: $status,
            contentScope: null,
            locale: 'en',
            title: 'Hello',
            excerpt: null,
            content: null,
            thumbnail: null,
            publishedAt: $publishedAt,
            updatedAt: null,
            availableLocales: ['en'],
            link: null,
        );
    }
}
