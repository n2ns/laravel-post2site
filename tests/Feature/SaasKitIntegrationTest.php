<?php

namespace N2ns\LaravelPost2Site\Tests\Feature;

use App\Models\BlogPost;
use App\Models\BlogPostTranslation;
use App\Models\Product;
use App\Models\User;
use N2ns\LaravelPost2Site\Contracts\ContentScopeValidator;
use N2ns\LaravelPost2Site\Contracts\PublicationTarget;
use N2ns\LaravelPost2Site\Contracts\PublicUrlResolver;
use N2ns\LaravelPost2Site\Contracts\ScopeContextProvider;
use N2ns\LaravelPost2Site\Integrations\SaasKit\SaasKitContentScopeValidator;
use N2ns\LaravelPost2Site\Integrations\SaasKit\SaasKitPublicationTarget;
use N2ns\LaravelPost2Site\Integrations\SaasKit\SaasKitPublicUrlResolver;
use N2ns\LaravelPost2Site\Integrations\SaasKit\SaasKitScopeContextProvider;
use N2ns\LaravelPost2Site\Tests\TestCase;

class SaasKitIntegrationTest extends TestCase
{
    public function test_saas_kit_adapter_publishes_company_blog_post(): void
    {
        $this->useSaasKitIntegration();

        User::query()->create(['name' => 'Author', 'email' => 'author@example.com']);

        $created = $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'technical',
                'slug' => 'company-update',
                'locale' => 'en',
                'title' => 'Company Update',
                'excerpt' => 'Short',
                'content' => 'Content',
            ])
            ->assertCreated()
            ->json('blog_post');

        $this->api()
            ->postJson("/api/v1/mcp/posts/{$created['id']}/publish")
            ->assertOk()
            ->assertJsonPath('blog_post.link', 'https://example.com/blog/company-update');

        $post = BlogPost::query()->where('slug', 'company-update')->firstOrFail();
        $this->assertSame('published', $post->status);
        $this->assertSame('Company Update', $post->title);
        $this->assertSame('Company Update', BlogPostTranslation::query()->where('blog_post_id', $post->id)->value('title'));
    }

    public function test_saas_kit_adapter_publishes_product_guide_and_validates_scope(): void
    {
        $this->useSaasKitIntegration();

        User::query()->create(['name' => 'Author', 'email' => 'author@example.com']);
        Product::query()->create(['code' => 'starter', 'name' => 'Starter', 'is_active' => true]);
        Product::query()->create(['code' => 'disabled', 'name' => 'Disabled', 'is_active' => false]);

        $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'guide',
                'content_scope' => 'product:disabled',
                'slug' => 'disabled-guide',
                'locale' => 'en',
                'title' => 'Disabled',
                'content' => 'Content',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content_scope');

        $created = $this->api()
            ->postJson('/api/v1/mcp/posts', [
                'type' => 'guide',
                'content_scope' => 'product:starter',
                'slug' => 'starter-guide',
                'locale' => 'en',
                'title' => 'Starter Guide',
                'content' => 'Guide content',
            ])
            ->assertCreated()
            ->json('blog_post');

        $this->api()
            ->postJson("/api/v1/mcp/posts/{$created['id']}/publish")
            ->assertOk()
            ->assertJsonPath('blog_post.link', 'https://example.com/starter/guides/starter-guide');

        $this->api()
            ->getJson('/api/v1/mcp/capabilities')
            ->assertOk()
            ->assertJsonPath('content.content_scope.kinds', ['product'])
            ->assertJsonPath('scopes.0.content_scope', 'product:starter');
    }

    private function useSaasKitIntegration(): void
    {
        config()->set('post2site.publishing.mode', 'adapter');
        config()->set('post2site.content_scope.kinds', ['product']);
        config()->set('post2site.integrations.saas_kit.default_locale', 'en');
        config()->set('post2site.bindings.publication_target', SaasKitPublicationTarget::class);
        config()->set('post2site.bindings.content_scope_validator', SaasKitContentScopeValidator::class);
        config()->set('post2site.bindings.scope_context_provider', SaasKitScopeContextProvider::class);
        config()->set('post2site.bindings.public_url_resolver', SaasKitPublicUrlResolver::class);

        $this->app->bind(PublicationTarget::class, SaasKitPublicationTarget::class);
        $this->app->bind(ContentScopeValidator::class, SaasKitContentScopeValidator::class);
        $this->app->bind(ScopeContextProvider::class, SaasKitScopeContextProvider::class);
        $this->app->bind(PublicUrlResolver::class, SaasKitPublicUrlResolver::class);
    }

    private function api(): self
    {
        $this->withHeader('X-API-KEY', 'test-key');

        return $this;
    }
}
