<?php

namespace N2ns\LaravelPost2Site\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use N2ns\LaravelPost2Site\Events\Post2SitePostPublished;
use N2ns\LaravelPost2Site\Jobs\SubmitPublishedPostForIndexing;
use N2ns\LaravelPost2Site\Tests\Fixtures\TestArticle;
use N2ns\LaravelPost2Site\Tests\TestCase;

class Post2SiteApiTest extends TestCase
{
    public function test_capabilities_are_protected_and_report_review_mode(): void
    {
        $this->getJson('/api/v1/mcp/capabilities')->assertUnauthorized();

        $this->withHeader('X-API-KEY', 'test-key')
            ->getJson('/api/v1/mcp/capabilities')
            ->assertOk()
            ->assertJsonPath('publishing.mode', 'review')
            ->assertJsonPath('publishing.manual_review_required', false)
            ->assertJsonPath('safety.delete_exposed', false);
    }

    public function test_create_list_update_and_review_publish_use_staging_table(): void
    {
        Queue::fake();
        $created = $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'technical',
                'slug' => 'hello-world',
                'locale' => 'en',
                'title' => 'Hello World',
                'content' => 'Draft content',
            ])
            ->assertCreated()
            ->assertJsonPath('blog_post.status', 'draft')
            ->assertJsonPath('blog_post.link', null)
            ->json('blog_post');

        $this->api()
            ->getJson('/api/v1/mcp/posts?status=draft')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'hello-world')
            ->assertJsonPath('data.0.link', null);

        $this->api()
            ->patchJson("/api/v1/mcp/posts/{$created['id']}", [
                'locale' => 'en',
                'title' => 'Updated',
            ])
            ->assertOk()
            ->assertJsonPath('blog_post.title', 'Updated');

        $this->api()
            ->postJson("/api/v1/mcp/posts/{$created['id']}/publish")
            ->assertOk()
            ->assertJsonPath('blog_post.status', 'published')
            ->assertJsonPath('blog_post.link', 'https://example.com/hello-world')
            ->assertJsonPath('meta.google_auto_submit', false);

        $this->assertDatabaseCount('test_articles', 0);
        Queue::assertNothingPushed();
    }

    public function test_configurable_publish_writes_host_model_and_returns_live_link(): void
    {
        config()->set('post2site.publishing.mode', 'configurable');
        config()->set('post2site.publishing.target.model', TestArticle::class);
        config()->set('post2site.publishing.target.url.pattern', '/{locale}/{key}/guides/{slug}');
        config()->set('post2site.indexing.enabled', true);
        config()->set('post2site.indexing.indexnow.enabled', true);
        Queue::fake();
        Event::fake();

        $created = $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'guide',
                'content_scope' => 'product:example-app',
                'slug' => 'apply-online',
                'locale' => 'en',
                'title' => 'Apply Online',
                'excerpt' => 'Short',
                'content' => 'Guide content',
            ])
            ->assertCreated()
            ->json('blog_post');

        $this->api()
            ->postJson("/api/v1/mcp/posts/{$created['id']}/publish")
            ->assertOk()
            ->assertJsonPath('blog_post.status', 'published')
            ->assertJsonPath('blog_post.link', 'https://example.com/en/example-app/guides/apply-online')
            ->assertJsonPath('meta.indexnow_queued', true);

        $article = TestArticle::query()->firstOrFail();
        $this->assertSame('apply-online', $article->slug);
        $this->assertSame('published', $article->status);
        $this->assertSame('Apply Online', $article->title['en']);

        Event::assertDispatched(Post2SitePostPublished::class);
        Queue::assertPushed(SubmitPublishedPostForIndexing::class);
    }

    public function test_list_filters_company_blog_and_product_guides(): void
    {
        $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'technical',
                'slug' => 'company-news',
                'locale' => 'en',
                'title' => 'Company',
                'content' => 'Content',
            ])
            ->assertCreated();

        $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'guide',
                'content_scope' => 'product:test',
                'slug' => 'product-guide',
                'locale' => 'en',
                'title' => 'Guide',
                'content' => 'Content',
            ])
            ->assertCreated();

        $this->api()
            ->getJson('/api/v1/mcp/posts?content_scope=')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'company-news');

        $this->api()
            ->getJson('/api/v1/mcp/posts?content_scope=product:test')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'product-guide');
    }

    public function test_configurable_publish_requires_target_model(): void
    {
        config()->set('post2site.publishing.mode', 'configurable');
        config()->set('post2site.publishing.target.model', null);

        $created = $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'technical',
                'slug' => 'missing-model',
                'locale' => 'en',
                'title' => 'Missing Model',
                'content' => 'Content',
            ])
            ->assertCreated()
            ->json('blog_post');

        $this->api()
            ->postJson("/api/v1/mcp/posts/{$created['id']}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('publishing.target.model');
    }

    public function test_configurable_publish_rejects_invalid_field_mapping(): void
    {
        config()->set('post2site.publishing.mode', 'configurable');
        config()->set('post2site.publishing.target.model', TestArticle::class);
        config()->set('post2site.publishing.target.fields.slug', ['value' => 'missing-column']);

        $created = $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'technical',
                'slug' => 'invalid-field-mapping',
                'locale' => 'en',
                'title' => 'Invalid Field Mapping',
                'content' => 'Content',
            ])
            ->assertCreated()
            ->json('blog_post');

        $this->api()
            ->postJson("/api/v1/mcp/posts/{$created['id']}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('publishing.target.fields.slug');

        $this->assertDatabaseCount('test_articles', 0);
    }

    public function test_prohibited_fields_are_rejected(): void
    {
        $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'slug' => 'bad',
                'locale' => 'en',
                'title' => 'Bad',
                'content' => 'Bad',
                'status' => 'published',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    private function api(): self
    {
        $this->withHeader('X-API-KEY', 'test-key');

        return $this;
    }
}
