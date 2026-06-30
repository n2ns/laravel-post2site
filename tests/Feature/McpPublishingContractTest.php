<?php

namespace N2ns\LaravelPost2Site\Tests\Feature;

use N2ns\LaravelPost2Site\Contracts\Post2SiteAdapter;
use N2ns\LaravelPost2Site\Models\Post2SiteApiKey;
use N2ns\LaravelPost2Site\Tests\Fixtures\GenericMcpAdapter;
use N2ns\LaravelPost2Site\Tests\TestCase;

class McpPublishingContractTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        GenericMcpAdapter::$publishCount = 0;
        $this->app->bind(Post2SiteAdapter::class, GenericMcpAdapter::class);
    }

    public function test_capabilities_are_generic_and_old_posts_route_is_removed(): void
    {
        $this->getJson('/api/v1/mcp/capabilities')->assertUnauthorized();

        $this->api()
            ->getJson('/api/v1/mcp/capabilities')
            ->assertOk()
            ->assertJsonPath('contract', 'post2site-publishing')
            ->assertJsonPath('host_profile', 'test-host')
            ->assertJsonPath('workflow.supports_drafts', true)
            ->assertJsonMissingPath('content.content_scope');

        $this->api()
            ->getJson('/api/v1/mcp/posts')
            ->assertNotFound();
    }

    public function test_content_payload_is_preserved_and_host_adapter_extracts_asset_refs(): void
    {
        $asset = $this->api()
            ->postJson('/api/v1/mcp/assets', [
                'purpose' => 'thumbnail',
                'filename' => 'selected.webp',
                'content_type' => 'image/webp',
                'data_base64' => base64_encode('image-bytes'),
                'metadata' => ['alt_text' => 'Selected image'],
            ])
            ->assertCreated()
            ->json('asset_id');

        $this->api()
            ->postJson('/api/v1/mcp/drafts', [
                'mode' => 'create',
                'target_identifier' => 'generic-resource',
                'content_payload' => [
                    'body' => 'Host-defined body',
                    'thumbnail_asset_id' => $asset,
                    'custom_host_field' => ['kept' => true],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('target_identifier', 'generic-resource')
            ->assertJsonPath('content_payload.custom_host_field.kept', true)
            ->assertJsonPath('asset_refs.0', $asset);

        $this->assertDatabaseHas('post2site_assets', [
            'asset_id' => $asset,
            'draft_id' => $this->firstDraftId(),
        ]);
    }

    public function test_database_auth_invalid_key_returns_unauthorized(): void
    {
        config()->set('post2site.auth.driver', 'database');

        Post2SiteApiKey::query()->create([
            'name' => 'Valid Client',
            'key_hash' => hash('sha256', 'valid-key'),
        ]);

        $this->withHeader('X-API-KEY', 'wrong-key')
            ->getJson('/api/v1/mcp/capabilities')
            ->assertUnauthorized();
    }

    public function test_adapter_asset_rejection_returns_validation_error(): void
    {
        $this->api()
            ->postJson('/api/v1/mcp/assets', [
                'purpose' => 'thumbnail',
                'filename' => 'reject.webp',
                'content_type' => 'image/webp',
                'data_base64' => base64_encode('image-bytes'),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
    }

    public function test_forbidden_ownership_fields_are_not_accepted_in_payload(): void
    {
        $this->api()
            ->postJson('/api/v1/mcp/drafts', [
                'mode' => 'create',
                'content_payload' => [
                    'body' => 'Body',
                    'content_origin' => 'mcp',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content_payload.content_origin');
    }

    public function test_publish_requires_user_confirmation(): void
    {
        $draft = $this->createDraft();

        $this->api()
            ->withHeader('Idempotency-Key', 'confirm-required')
            ->withHeader('If-Match', '"draft-version-1"')
            ->postJson("/api/v1/mcp/drafts/{$draft['draft_id']}/publish", [
                'expected_version' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_confirmed_publish');
    }

    public function test_preview_delegates_to_host_adapter(): void
    {
        $draft = $this->createDraft();

        $this->api()
            ->getJson("/api/v1/mcp/drafts/{$draft['draft_id']}/preview")
            ->assertOk()
            ->assertJsonPath('draft_id', $draft['draft_id'])
            ->assertJsonPath('preview_url', "https://example.com/preview/{$draft['draft_id']}")
            ->assertJsonPath('preview_urls.en', "https://example.com/preview/{$draft['draft_id']}?locale=en")
            ->assertJsonPath('expires_at', '2026-07-01T12:00:00.000000Z');
    }

    public function test_publish_rejects_stale_if_match_even_when_expected_version_matches(): void
    {
        $draft = $this->createDraft();

        $this->api()
            ->withHeader('Idempotency-Key', 'stale-header')
            ->withHeader('If-Match', '"draft-version-2"')
            ->postJson("/api/v1/mcp/drafts/{$draft['draft_id']}/publish", [
                'user_confirmed_publish' => true,
                'expected_version' => 1,
                'acknowledged_warnings' => [],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'stale_version');
    }

    public function test_publish_idempotency_caches_same_payload_and_rejects_conflicts(): void
    {
        $draft = $this->createDraft();
        $payload = [
            'user_confirmed_publish' => true,
            'expected_version' => 1,
            'acknowledged_warnings' => [],
        ];

        $first = $this->api()
            ->withHeader('Idempotency-Key', 'publish-once')
            ->withHeader('If-Match', '"draft-version-1"')
            ->postJson("/api/v1/mcp/drafts/{$draft['draft_id']}/publish", $payload)
            ->assertOk()
            ->assertJsonPath('status', 'published')
            ->json();

        $this->assertSame(1, GenericMcpAdapter::$publishCount);

        $this->api()
            ->withHeader('Idempotency-Key', 'publish-once')
            ->withHeader('If-Match', '"draft-version-1"')
            ->postJson("/api/v1/mcp/drafts/{$draft['draft_id']}/publish", $payload)
            ->assertOk()
            ->assertExactJson($first);

        $this->assertSame(1, GenericMcpAdapter::$publishCount);

        $this->api()
            ->withHeader('Idempotency-Key', 'publish-once')
            ->withHeader('If-Match', '"draft-version-1"')
            ->postJson("/api/v1/mcp/drafts/{$draft['draft_id']}/publish", [
                'user_confirmed_publish' => true,
                'expected_version' => 1,
                'acknowledged_warnings' => ['changed'],
            ])
            ->assertStatus(412)
            ->assertJsonPath('error.code', 'idempotency_conflict');
    }

    public function test_published_draft_cannot_be_updated_or_republished_with_new_key(): void
    {
        $draft = $this->createDraft();

        $this->api()
            ->withHeader('Idempotency-Key', 'publish-before-update')
            ->withHeader('If-Match', '"draft-version-1"')
            ->postJson("/api/v1/mcp/drafts/{$draft['draft_id']}/publish", [
                'user_confirmed_publish' => true,
                'expected_version' => 1,
                'acknowledged_warnings' => [],
            ])
            ->assertOk();

        $this->api()
            ->withHeader('If-Match', '"draft-version-1"')
            ->patchJson("/api/v1/mcp/drafts/{$draft['draft_id']}", [
                'content_payload' => ['body' => 'Changed after publish'],
                'expected_version' => 1,
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'invalid_transition');

        $this->api()
            ->withHeader('Idempotency-Key', 'publish-again')
            ->withHeader('If-Match', '"draft-version-1"')
            ->postJson("/api/v1/mcp/drafts/{$draft['draft_id']}/publish", [
                'user_confirmed_publish' => true,
                'expected_version' => 1,
                'acknowledged_warnings' => [],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'invalid_transition');
    }

    private function createDraft(): array
    {
        return $this->api()
            ->postJson('/api/v1/mcp/drafts', [
                'mode' => 'create',
                'target_identifier' => 'publishable-resource',
                'content_payload' => ['body' => 'Ready'],
            ])
            ->assertCreated()
            ->json();
    }

    private function firstDraftId(): string
    {
        return (string) $this->app['db']->table('post2site_drafts')->value('draft_id');
    }

    private function api(): self
    {
        $this->withHeader('X-API-KEY', 'test-key');

        return $this;
    }
}
