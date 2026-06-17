<?php

namespace N2ns\LaravelPost2Site\Tests\Feature;

use N2ns\LaravelPost2Site\Contracts\ContentScopeValidator;
use N2ns\LaravelPost2Site\Contracts\PublicationTarget;
use N2ns\LaravelPost2Site\Models\Post2SiteApiKey;
use N2ns\LaravelPost2Site\Repositories\NullPublicationTarget;
use N2ns\LaravelPost2Site\Tests\Fixtures\AdapterTarget;
use N2ns\LaravelPost2Site\Tests\Fixtures\RejectingContentScopeValidator;
use N2ns\LaravelPost2Site\Tests\TestCase;

class Post2SiteValidationAndAuthTest extends TestCase
{
    public function test_guide_requires_content_scope_and_non_guide_rejects_content_scope(): void
    {
        $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'guide',
                'slug' => 'missing-scope',
                'locale' => 'en',
                'title' => 'Missing',
                'content' => 'Content',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content_scope');

        $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'technical',
                'content_scope' => 'product:test',
                'slug' => 'bad-scope',
                'locale' => 'en',
                'title' => 'Bad',
                'content' => 'Content',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content_scope');
    }

    public function test_update_validates_next_type_and_content_scope_combination(): void
    {
        $created = $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'technical',
                'slug' => 'update-scope',
                'locale' => 'en',
                'title' => 'Update',
                'content' => 'Content',
            ])
            ->assertCreated()
            ->json('blog_post');

        $this->api()
            ->patchJson("/api/v1/mcp/posts/{$created['id']}", ['type' => 'guide'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content_scope');

        $this->api()
            ->patchJson("/api/v1/mcp/posts/{$created['id']}", [
                'type' => 'guide',
                'content_scope' => 'product:test',
            ])
            ->assertOk()
            ->assertJsonPath('blog_post.type', 'guide')
            ->assertJsonPath('blog_post.content_scope', 'product:test');

        $this->api()
            ->patchJson("/api/v1/mcp/posts/{$created['id']}", ['type' => 'technical'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content_scope');
    }

    public function test_content_scope_kind_whitelist_is_enforced_when_configured(): void
    {
        config()->set('post2site.content_scope.kinds', ['product']);

        $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'guide',
                'content_scope' => 'project:something',
                'slug' => 'bad-kind',
                'locale' => 'en',
                'title' => 'Bad Kind',
                'content' => 'Content',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content_scope');
    }

    public function test_host_content_scope_validator_can_reject_unknown_key(): void
    {
        $this->app->bind(ContentScopeValidator::class, RejectingContentScopeValidator::class);

        $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'guide',
                'content_scope' => 'product:denied',
                'slug' => 'denied-key',
                'locale' => 'en',
                'title' => 'Denied',
                'content' => 'Content',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content_scope');

        $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'guide',
                'content_scope' => 'product:allowed',
                'slug' => 'allowed-key',
                'locale' => 'en',
                'title' => 'Allowed',
                'content' => 'Content',
            ])
            ->assertCreated();
    }

    public function test_capabilities_expose_content_scope_contract(): void
    {
        config()->set('post2site.content_scope.kinds', ['product', 'project']);
        config()->set('post2site.content_scope.examples', ['product:evisa-helper']);
        config()->set('post2site.content.scoped_types', ['guide']);

        $this->api()
            ->getJson('/api/v1/mcp/capabilities')
            ->assertOk()
            ->assertJsonPath('content.content_scope.format', 'kind:key')
            ->assertJsonPath('content.content_scope.kinds', ['product', 'project'])
            ->assertJsonPath('content.content_scope.examples', ['product:evisa-helper'])
            ->assertJsonPath('content.content_scope.required_for_types', ['guide']);
    }

    public function test_scoped_types_are_configurable_not_hardcoded_to_guide(): void
    {
        // Make 'announcement' require a scope and 'guide' no longer allow one.
        config()->set('post2site.content.scoped_types', ['announcement']);

        // announcement now requires content_scope
        $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'announcement',
                'slug' => 'ann-no-scope',
                'locale' => 'en',
                'title' => 'Ann',
                'content' => 'Content',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content_scope');

        // guide no longer allows content_scope
        $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'guide',
                'content_scope' => 'product:test',
                'slug' => 'guide-with-scope',
                'locale' => 'en',
                'title' => 'Guide',
                'content' => 'Content',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content_scope');

        // announcement with a valid scope succeeds
        $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'announcement',
                'content_scope' => 'product:test',
                'slug' => 'ann-with-scope',
                'locale' => 'en',
                'title' => 'Ann',
                'content' => 'Content',
            ])
            ->assertCreated();
    }

    public function test_scope_context_comes_from_config(): void
    {
        config()->set('post2site.scopes.product:test', [
            'name' => 'Test Product',
            'canonical_url' => 'https://example.com/test',
            'docs_url' => 'https://example.com/test/guides',
            'summary' => 'Summary',
            'key_points' => ['Point'],
            'do_not_claim' => ['No guarantee'],
        ]);

        $this->api()
            ->getJson('/api/v1/mcp/capabilities')
            ->assertOk()
            ->assertJsonPath('scopes.0.content_scope', 'product:test');

        $this->api()
            ->getJson('/api/v1/mcp/scopes/product:test')
            ->assertOk()
            ->assertJsonPath('content_scope', 'product:test')
            ->assertJsonPath('name', 'Test Product');
    }

    public function test_database_auth_driver_checks_hash_and_updates_last_used_at(): void
    {
        config()->set('post2site.auth.driver', 'database');
        Post2SiteApiKey::query()->create([
            'name' => 'Test',
            'key_hash' => hash('sha256', 'database-key'),
        ]);

        $this->withHeader('X-API-KEY', 'bad-key')
            ->getJson('/api/v1/mcp/capabilities')
            ->assertUnauthorized();

        $this->withHeader('X-API-KEY', 'database-key')
            ->getJson('/api/v1/mcp/capabilities')
            ->assertOk();

        $this->assertNotNull(Post2SiteApiKey::query()->first()->last_used_at);
    }

    public function test_adapter_mode_requires_real_target_when_null_target_is_bound(): void
    {
        config()->set('post2site.publishing.mode', 'adapter');
        $this->app->bind(PublicationTarget::class, NullPublicationTarget::class);

        $created = $this->createDraft('adapter-null');

        $this->api()
            ->postJson("/api/v1/mcp/posts/{$created['id']}/publish")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('publication_target');
    }

    public function test_adapter_mode_can_publish_through_custom_target(): void
    {
        config()->set('post2site.publishing.mode', 'adapter');
        $this->app->bind(PublicationTarget::class, AdapterTarget::class);

        $created = $this->createDraft('adapter-ok');

        $this->api()
            ->postJson("/api/v1/mcp/posts/{$created['id']}/publish")
            ->assertOk()
            ->assertJsonPath('blog_post.status', 'published')
            ->assertJsonPath('blog_post.link', 'https://example.com/custom/adapter-ok');
    }

    public function test_delete_route_is_not_exposed(): void
    {
        $created = $this->createDraft('no-delete');

        $this->api()
            ->deleteJson("/api/v1/mcp/posts/{$created['id']}")
            ->assertMethodNotAllowed();
    }

    private function createDraft(string $slug): array
    {
        return $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'technical',
                'slug' => $slug,
                'locale' => 'en',
                'title' => $slug,
                'content' => 'Content',
            ])
            ->assertCreated()
            ->json('blog_post');
    }

    private function api(): self
    {
        $this->withHeader('X-API-KEY', 'test-key');

        return $this;
    }
}
