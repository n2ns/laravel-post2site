<?php

namespace N2ns\LaravelPost2Site;

use Illuminate\Support\ServiceProvider;
use N2ns\LaravelPost2Site\Console\Commands\CreateApiKeyCommand;
use N2ns\LaravelPost2Site\Contracts\ContentScopeValidator;
use N2ns\LaravelPost2Site\Contracts\IndexingNotifier;
use N2ns\LaravelPost2Site\Contracts\PostRepository;
use N2ns\LaravelPost2Site\Contracts\PublicationTarget;
use N2ns\LaravelPost2Site\Contracts\PublicUrlResolver;
use N2ns\LaravelPost2Site\Contracts\ScopeContextProvider;

class LaravelPost2SiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->replaceConfigRecursivelyFrom(__DIR__.'/../config/post2site.php', 'post2site');

        $this->app->bind(PostRepository::class, config('post2site.bindings.repository'));
        $this->app->bind(PublicationTarget::class, config('post2site.bindings.publication_target'));
        $this->app->bind(ScopeContextProvider::class, config('post2site.bindings.scope_context_provider'));
        $this->app->bind(IndexingNotifier::class, config('post2site.bindings.indexing_notifier'));
        $this->app->bind(PublicUrlResolver::class, config('post2site.bindings.public_url_resolver'));
        $this->app->bind(ContentScopeValidator::class, config('post2site.bindings.content_scope_validator'));
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/post2site.php' => config_path('post2site.php'),
            ], 'post2site-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations/create_post2site_api_keys_table.php' => database_path('migrations/create_post2site_api_keys_table.php'),
            ], 'post2site-auth-migrations');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations/create_post2site_posts_tables.php' => database_path('migrations/create_post2site_posts_tables.php'),
            ], 'post2site-content-migrations');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations/create_post2site_indexing_submissions_table.php' => database_path('migrations/create_post2site_indexing_submissions_table.php'),
            ], 'post2site-indexing-migrations');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'post2site-migrations');

            $this->commands([
                CreateApiKeyCommand::class,
            ]);
        }
    }
}
