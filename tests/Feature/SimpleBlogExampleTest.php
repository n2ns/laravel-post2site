<?php

namespace N2ns\LaravelPost2Site\Tests\Feature;

use App\Post2Site\BlogPostAdapter;
use N2ns\LaravelPost2Site\Tests\TestCase;

require_once __DIR__.'/../../examples/simple-blog/app/Models/BlogPost.php';
require_once __DIR__.'/../../examples/simple-blog/app/Post2Site/BlogPostAdapter.php';

class SimpleBlogExampleTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('post2site.bindings.adapter', BlogPostAdapter::class);
        $app['config']->set('post2site.drafts.modes', ['create']);
        $app['config']->set('post2site.workflow.supports_assets', false);
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(__DIR__.'/../../examples/simple-blog/database/migrations');
    }

    protected function defineRoutes($router): void
    {
        require __DIR__.'/../../examples/simple-blog/routes/post2site-blog.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['view']->addLocation(__DIR__.'/../../examples/simple-blog/resources/views');
    }

    public function test_first_publish_creates_a_real_public_article_only_after_confirmation(): void
    {
        $article = $this->article();
        $article['content_payload']['body'] = 'Plain text <script>alert("example")</script>';

        $this->withHeader('X-API-KEY', 'test-key');
        $draft = $this->postJson('/api/v1/mcp/drafts', $article)
            ->assertCreated()
            ->assertJsonPath('validation_state.publishable', true)
            ->json('draft_id');

        $this->assertDatabaseCount('post2site_drafts', 1);
        $this->assertDatabaseCount('blog_posts', 0);
        $this->get('/posts/hello-post2site')->assertNotFound();
        $this->postJson("/api/v1/mcp/drafts/{$draft}/publish", ['publish_confirmed' => false])
            ->assertUnprocessable();
        $this->assertDatabaseCount('blog_posts', 0);

        $preview = $this->getJson("/api/v1/mcp/drafts/{$draft}/preview")
            ->assertOk()->json('preview_url');
        $this->get('/post2site-preview/'.$draft)->assertForbidden();
        $this->get($preview)->assertOk()
            ->assertSee('Draft preview')
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>', false);
        $this->assertDatabaseCount('blog_posts', 0);

        $this->postJson("/api/v1/mcp/drafts/{$draft}/validate", ['mode' => 'publish'])
            ->assertOk()->assertJsonPath('publishable', true);

        $published = $this->postJson("/api/v1/mcp/drafts/{$draft}/publish", ['publish_confirmed' => true])
            ->assertOk()->assertJsonPath('status', 'published');
        $this->assertDatabaseHas('blog_posts', $article['content_payload']);
        $this->assertDatabaseHas('post2site_drafts', ['draft_id' => $draft, 'status' => 'published']);
        $this->get($published->json('canonical_url'))->assertOk()
            ->assertSee('Hello Post2Site')
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('Draft preview');

        $this->getJson('/api/v1/mcp/inventory/resources/hello-post2site')
            ->assertOk()->assertJsonPath('item.id', $published->json('resource_id'));
        $this->getJson('/api/v1/mcp/inventory/resources?q=Hello')
            ->assertOk()->assertJsonCount(1, 'items');
        $this->getJson('/api/v1/mcp/inventory/stats')
            ->assertOk()->assertJsonPath('dimensions.status.published', 1);
        $this->postJson('/api/v1/mcp/inventory/duplicates', $article)
            ->assertOk()->assertJsonPath('issues.0.code', 'slug_taken');

        $duplicate = $this->postJson('/api/v1/mcp/drafts', $article)->assertCreated()->json('draft_id');
        $this->postJson("/api/v1/mcp/drafts/{$duplicate}/publish", ['publish_confirmed' => true])
            ->assertUnprocessable()->assertJsonPath('publishable', false);
        $this->assertDatabaseCount('blog_posts', 1);
    }

    public function test_text_only_example_exposes_its_limits_and_blocks_unsupported_content(): void
    {
        $this->withHeader('X-API-KEY', 'test-key');
        $this->getJson('/api/v1/mcp/capabilities')->assertOk()
            ->assertJsonPath('workflow.supports_assets', false)
            ->assertJsonPath('host_metadata.supported_modes', ['create']);
        $this->postJson('/api/v1/mcp/assets', [
            'purpose' => 'thumbnail',
            'filename' => 'image.png',
            'content_type' => 'image/png',
            'data_base64' => base64_encode('example'),
        ])->assertUnprocessable();

        $article = $this->article();
        $article['content_payload']['thumbnail_asset_id'] = 'unsupported';
        $draft = $this->postJson('/api/v1/mcp/drafts', $article)->assertCreated()
            ->assertJsonPath('validation_state.publishable', false)->json('draft_id');
        $this->postJson("/api/v1/mcp/drafts/{$draft}/publish", ['publish_confirmed' => true])
            ->assertUnprocessable();
        $this->postJson('/api/v1/mcp/working-drafts/validate', [
            'mode' => 'publish',
            'article' => array_replace($this->article(), ['mode' => 'update_existing']),
        ])->assertOk()->assertJsonPath('publishable', false);
        $this->assertDatabaseCount('blog_posts', 0);
    }

    private function article(): array
    {
        return [
            'mode' => 'create',
            'target_identifier' => 'hello-post2site',
            'content_payload' => [
                'title' => 'Hello Post2Site',
                'slug' => 'hello-post2site',
                'body' => 'My first published article.',
            ],
        ];
    }
}
