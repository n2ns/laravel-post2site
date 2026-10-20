<?php

namespace N2ns\LaravelPost2Site\Tests\Feature;

use N2ns\LaravelPost2Site\Contracts\Post2SiteAdapter;
use N2ns\LaravelPost2Site\LaravelPost2SiteServiceProvider;
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

    public function test_inventory_resource_target_identifier_may_contain_encoded_slashes(): void
    {
        $this->api()
            ->getJson('/api/v1/mcp/inventory/resources/guides%2Fexample-post')
            ->assertOk()
            ->assertJsonPath('item.target_identifier', 'guides/example-post')
            ->assertJsonPath('item.id', 'resource_guides_example_post');
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

    public function test_reserved_lifecycle_fields_are_not_accepted_in_payload(): void
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

    public function test_publish_requires_confirmation(): void
    {
        $draft = $this->createDraft();

        $this->api()
            ->postJson("/api/v1/mcp/drafts/{$draft['draft_id']}/publish", [
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('publish_confirmed');
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

    public function test_draft_can_be_updated_without_version_headers(): void
    {
        $draft = $this->createDraft();

        $this->api()
            ->patchJson("/api/v1/mcp/drafts/{$draft['draft_id']}", [
                'content_payload' => ['body' => 'Updated'],
            ])
            ->assertOk()
            ->assertJsonPath('version', 2)
            ->assertJsonPath('content_payload.body', 'Updated');
    }

    public function test_publish_only_requires_confirmation(): void
    {
        $draft = $this->createDraft();

        $this->api()
            ->postJson("/api/v1/mcp/drafts/{$draft['draft_id']}/publish", [
                'publish_confirmed' => true,
                'acknowledged_warnings' => [],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'published');

        $this->assertSame(1, GenericMcpAdapter::$publishCount);
    }

    public function test_published_draft_cannot_be_updated_or_republished(): void
    {
        $draft = $this->createDraft();

        $this->api()
            ->postJson("/api/v1/mcp/drafts/{$draft['draft_id']}/publish", [
                'publish_confirmed' => true,
                'acknowledged_warnings' => [],
            ])
            ->assertOk();

        $this->api()
            ->patchJson("/api/v1/mcp/drafts/{$draft['draft_id']}", [
                'content_payload' => ['body' => 'Changed after publish'],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'invalid_transition');

        $this->api()
            ->postJson("/api/v1/mcp/drafts/{$draft['draft_id']}/publish", [
                'publish_confirmed' => true,
                'acknowledged_warnings' => [],
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'invalid_transition');
    }

    public function test_publish_confirmation_must_be_boolean_true(): void
    {
        $draft = $this->createDraft();

        foreach (['yes', 'on', '1', 1] as $confirmation) {
            $this->api()
                ->postJson("/api/v1/mcp/drafts/{$draft['draft_id']}/publish", [
                    'publish_confirmed' => $confirmation,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('publish_confirmed');
        }

        $this->assertSame(0, GenericMcpAdapter::$publishCount);
    }

    public function test_draft_list_per_page_is_bounded(): void
    {
        $this->createDraft();
        $this->createDraft();

        $this->api()->getJson('/api/v1/mcp/drafts?per_page=-1')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->api()->getJson('/api/v1/mcp/drafts?per_page=abc')->assertUnprocessable()->assertJsonValidationErrors('per_page');

        config()->set('post2site.drafts.per_page_max', 1);
        $this->api()->getJson('/api/v1/mcp/drafts?per_page=50')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_draft_list_returns_a_cursor_for_the_next_page(): void
    {
        $first = $this->createDraft()['draft_id'];
        $second = $this->createDraft()['draft_id'];
        $third = $this->createDraft()['draft_id'];

        $page = $this->api()->getJson('/api/v1/mcp/drafts?per_page=2')
            ->assertOk()
            ->assertJsonPath('data.0.draft_id', $third)
            ->assertJsonPath('data.1.draft_id', $second);

        $cursor = $page->json('next_cursor');
        $this->assertIsString($cursor);

        $this->api()->getJson('/api/v1/mcp/drafts?per_page=2&cursor='.urlencode($cursor))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.draft_id', $first)
            ->assertJsonPath('next_cursor', null);
    }

    public function test_empty_metadata_maps_are_json_objects(): void
    {
        $content = $this->api()
            ->postJson('/api/v1/mcp/drafts', [
                'mode' => 'create',
                'content_payload' => ['body' => 'Ready'],
            ])
            ->assertCreated()
            ->getContent();

        $this->assertStringContainsString('"client_metadata":{}', $content);
        $this->assertStringContainsString('"host_metadata":{}', $content);
        $this->assertStringNotContainsString('_metadata":[]', $content);
    }

    public function test_capabilities_report_installed_package_version_not_published_config(): void
    {
        config()->set('post2site.version', '0.0.1');

        $this->api()
            ->getJson('/api/v1/mcp/capabilities')
            ->assertOk()
            ->assertJsonPath('package_version', LaravelPost2SiteServiceProvider::VERSION);
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
