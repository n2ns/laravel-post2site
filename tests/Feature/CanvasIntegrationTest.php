<?php

namespace N2ns\LaravelPost2Site\Tests\Feature;

use Canvas\Models\Post;
use Canvas\Models\User;
use Illuminate\Support\Str;
use N2ns\LaravelPost2Site\Contracts\PublicationTarget;
use N2ns\LaravelPost2Site\Contracts\PublicUrlResolver;
use N2ns\LaravelPost2Site\Integrations\Canvas\CanvasPublicationTarget;
use N2ns\LaravelPost2Site\Integrations\Canvas\CanvasPublicUrlResolver;
use N2ns\LaravelPost2Site\Models\Post2SitePost;
use N2ns\LaravelPost2Site\Tests\TestCase;

class CanvasIntegrationTest extends TestCase
{
    public function test_canvas_adapter_publishes_post(): void
    {
        $this->useCanvasIntegration();
        User::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Canvas Author',
            'email' => 'author@example.com',
        ]);

        $created = $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'technical',
                'slug' => 'canvas-post',
                'locale' => 'en',
                'title' => 'Canvas Post',
                'excerpt' => 'Canvas summary',
                'content' => 'Canvas body',
                'thumbnail' => 'https://example.com/cover.jpg',
            ])
            ->assertCreated()
            ->json('blog_post');

        $this->api()
            ->postJson("/api/v1/mcp/posts/{$created['id']}/publish")
            ->assertOk()
            ->assertJsonPath('blog_post.link', 'https://example.com/articles/canvas-post');

        $post = Post::query()->where('slug', 'canvas-post')->firstOrFail();
        $this->assertTrue(Str::isUuid($post->id));
        $this->assertSame('Canvas Post', $post->title);
        $this->assertSame('Canvas summary', $post->summary);
        $this->assertSame('Canvas body', $post->body);
        $this->assertSame('https://example.com/cover.jpg', $post->featured_image);
        $this->assertNotNull($post->published_at);
        $this->assertSame('https://example.com/articles/canvas-post', Post2SitePost::query()->where('slug', 'canvas-post')->value('target_link'));
    }

    public function test_canvas_adapter_requires_canvas_user(): void
    {
        $this->useCanvasIntegration();

        $created = $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'technical',
                'slug' => 'missing-user',
                'locale' => 'en',
                'title' => 'Missing User',
                'content' => 'Content',
            ])
            ->assertCreated()
            ->json('blog_post');

        $this->api()
            ->postJson("/api/v1/mcp/posts/{$created['id']}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('post2site.integrations.canvas.author_id');
    }

    private function useCanvasIntegration(): void
    {
        config()->set('post2site.publishing.mode', 'adapter');
        config()->set('post2site.content.scoped_types', []);
        config()->set('post2site.integrations.canvas.public_url_pattern', '/articles/{slug}');
        config()->set('post2site.bindings.publication_target', CanvasPublicationTarget::class);
        config()->set('post2site.bindings.public_url_resolver', CanvasPublicUrlResolver::class);

        $this->app->bind(PublicationTarget::class, CanvasPublicationTarget::class);
        $this->app->bind(PublicUrlResolver::class, CanvasPublicUrlResolver::class);
    }

    private function api(): self
    {
        $this->withHeader('X-API-KEY', 'test-key');

        return $this;
    }
}
