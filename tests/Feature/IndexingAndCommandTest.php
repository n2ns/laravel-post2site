<?php

namespace N2ns\LaravelPost2Site\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use N2ns\LaravelPost2Site\Contracts\IndexingNotifier;
use N2ns\LaravelPost2Site\Contracts\PostRepository;
use N2ns\LaravelPost2Site\Data\IndexingPlan;
use N2ns\LaravelPost2Site\Data\PostData;
use N2ns\LaravelPost2Site\Data\PublishedPostData;
use N2ns\LaravelPost2Site\Indexing\IndexNowNotifier;
use N2ns\LaravelPost2Site\Jobs\SubmitPublishedPostForIndexing;
use N2ns\LaravelPost2Site\Models\Post2SiteApiKey;
use N2ns\LaravelPost2Site\Models\Post2SiteIndexingSubmission;
use N2ns\LaravelPost2Site\Support\IndexNowKeyFile;
use N2ns\LaravelPost2Site\Support\PublicPostMetadata;
use N2ns\LaravelPost2Site\Tests\TestCase;

class IndexingAndCommandTest extends TestCase
{
    public function test_indexnow_key_route_is_disabled_by_default_and_can_publish_current_key(): void
    {
        $this->get('/abc12345.txt')->assertNotFound();

        config()->set('post2site.indexing.indexnow.auto_publish_key_file', true);
        config()->set('post2site.indexing.indexnow.key', 'abc12345');
        $this->refreshApplicationRoutes();

        $this->get('/abc12345.txt')
            ->assertOk()
            ->assertSee('abc12345');

        $this->get('/wrong123.txt')->assertNotFound();
    }

    public function test_indexnow_notifier_sends_documented_payload(): void
    {
        Http::fake([
            'https://api.indexnow.org/*' => Http::response('', 202),
        ]);
        config()->set('post2site.indexing.indexnow.key', 'abc12345');
        config()->set('post2site.indexing.indexnow.key_location', 'https://example.com/abc12345.txt');

        $result = app(IndexNowNotifier::class)->notify(new IndexingPlan(
            url: 'https://example.com/en/blog/post',
            host: 'example.com',
            postId: 1,
            contentScope: null,
            publishedAt: now(),
        ));

        $this->assertSame('accepted', $result->status);
        $this->assertSame(202, $result->httpStatus);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.indexnow.org/indexnow'
            && $request['host'] === 'example.com'
            && $request['key'] === 'abc12345'
            && $request['keyLocation'] === 'https://example.com/abc12345.txt'
            && $request['urlList'] === ['https://example.com/en/blog/post']);
    }

    public function test_indexnow_notifier_skips_invalid_key(): void
    {
        Http::fake();
        config()->set('post2site.indexing.indexnow.key', 'invalid_key_with_underscore');

        $result = app(IndexNowNotifier::class)->notify(new IndexingPlan(
            url: 'https://example.com/en/blog/post',
            host: 'example.com',
            postId: 1,
            contentScope: null,
            publishedAt: now(),
        ));

        $this->assertSame('skipped', $result->status);
        Http::assertNothingSent();
    }

    public function test_indexing_job_records_submission(): void
    {
        Http::fake([
            'https://api.indexnow.org/*' => Http::response('', 200),
        ]);
        config()->set('post2site.indexing.indexnow.enabled', true);
        config()->set('post2site.indexing.indexnow.key', 'abc12345');

        $post = $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'technical',
                'slug' => 'indexed',
                'locale' => 'en',
                'title' => 'Indexed',
                'content' => 'Content',
            ])
            ->assertCreated()
            ->json('blog_post');

        app(PostRepository::class)->markPublished(
            $post['id'],
            new PublishedPostData(
                targetId: 'target-1',
                targetType: 'article',
                link: 'https://example.com/en/blog/indexed',
                publishedAt: now(),
            ),
        );

        app(SubmitPublishedPostForIndexing::class, [
            'postId' => $post['id'],
            'link' => 'https://example.com/en/blog/indexed',
        ])->handle(app(IndexingNotifier::class));

        $this->assertDatabaseHas('post2site_indexing_submissions', [
            'post_id' => $post['id'],
            'driver' => 'indexnow',
            'status' => 'accepted',
            'http_status' => 200,
        ]);
    }

    public function test_indexing_job_deduplicates_recent_submission(): void
    {
        Http::fake();
        config()->set('post2site.indexing.indexnow.enabled', true);
        config()->set('post2site.indexing.indexnow.key', 'abc12345');

        $post = $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'technical',
                'slug' => 'dedupe',
                'locale' => 'en',
                'title' => 'Dedupe',
                'content' => 'Content',
            ])
            ->assertCreated()
            ->json('blog_post');

        Post2SiteIndexingSubmission::query()->create([
            'post_id' => $post['id'],
            'url' => 'https://example.com/en/blog/dedupe',
            'driver' => 'indexnow',
            'status' => 'accepted',
            'http_status' => 200,
            'attempts' => 1,
            'last_submitted_at' => now(),
        ]);

        app(SubmitPublishedPostForIndexing::class, [
            'postId' => $post['id'],
            'link' => 'https://example.com/en/blog/dedupe',
        ])->handle(app(IndexingNotifier::class));

        $this->assertSame(1, Post2SiteIndexingSubmission::query()->count());
        Http::assertNothingSent();
    }

    public function test_future_published_post_does_not_expose_link(): void
    {
        $post = $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'technical',
                'slug' => 'future',
                'locale' => 'en',
                'title' => 'Future',
                'content' => 'Content',
            ])
            ->assertCreated()
            ->json('blog_post');

        $published = app(PostRepository::class)->markPublished(
            $post['id'],
            new PublishedPostData(
                targetId: 'target-1',
                targetType: 'article',
                link: 'https://example.com/en/blog/future',
                publishedAt: now()->addDay(),
            ),
        );

        $this->assertNull($published->link);
    }

    public function test_create_api_key_command_stores_hash_and_outputs_plain_key_once(): void
    {
        Artisan::call('post2site:key', [
            'name' => 'Production MCP',
            '--plain' => true,
        ]);

        $plain = trim(Artisan::output());
        $this->assertStringStartsWith('p2s_', $plain);
        $this->assertDatabaseCount('post2site_api_keys', 1);
        $this->assertNotSame($plain, Post2SiteApiKey::query()->first()->key_hash);
    }

    public function test_metadata_and_key_validation_helpers(): void
    {
        $this->assertTrue(app(IndexNowKeyFile::class)->validKey('abc12345'));
        $this->assertFalse(app(IndexNowKeyFile::class)->validKey('short'));

        $post = $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'technical',
                'slug' => 'metadata',
                'locale' => 'en',
                'title' => 'Metadata',
                'excerpt' => 'Description',
                'content' => 'Content',
            ])
            ->assertCreated()
            ->json('blog_post');

        $envelope = $this->api()
            ->postJson("/api/v1/mcp/posts/{$post['id']}/publish")
            ->assertOk()
            ->json();

        $metadata = app(PublicPostMetadata::class)->forPost(new PostData(
            id: $envelope['blog_post']['id'],
            slug: $envelope['blog_post']['slug'],
            type: $envelope['blog_post']['type'],
            status: $envelope['blog_post']['status'],
            contentScope: $envelope['blog_post']['content_scope'],
            locale: $envelope['blog_post']['locale'],
            title: $envelope['blog_post']['title'],
            excerpt: $envelope['blog_post']['excerpt'],
            content: $envelope['blog_post']['content'],
            thumbnail: $envelope['blog_post']['thumbnail'],
            publishedAt: null,
            updatedAt: null,
            availableLocales: $envelope['available_locales'],
            link: $envelope['blog_post']['link'],
        ));

        $this->assertSame('Metadata', $metadata['title']);
        $this->assertSame('Description', $metadata['description']);
    }

    private function refreshApplicationRoutes(): void
    {
        $this->app['router']->getRoutes()->refreshNameLookups();
        require __DIR__.'/../../routes/api.php';
    }

    private function api(): self
    {
        $this->withHeader('X-API-KEY', 'test-key');

        return $this;
    }
}
