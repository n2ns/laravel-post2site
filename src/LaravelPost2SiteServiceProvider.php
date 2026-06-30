<?php

namespace N2ns\LaravelPost2Site;

use Illuminate\Support\ServiceProvider;
use N2ns\LaravelPost2Site\Console\Commands\CreateApiKeyCommand;
use N2ns\LaravelPost2Site\Contracts\IndexingNotifier;
use N2ns\LaravelPost2Site\Contracts\Post2SiteAdapter;

class LaravelPost2SiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->replaceConfigRecursivelyFrom(__DIR__.'/../config/post2site.php', 'post2site');
        $this->applyPresetConfig();

        $this->app->bind(Post2SiteAdapter::class, config('post2site.bindings.adapter'));
        $this->app->bind(IndexingNotifier::class, config('post2site.bindings.indexing_notifier'));
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
                __DIR__.'/../database/migrations/create_post2site_staging_tables.php' => database_path('migrations/create_post2site_staging_tables.php'),
            ], 'post2site-staging-migrations');

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

    private function applyPresetConfig(): void
    {
        $preset = config('post2site.preset');
        if (! is_string($preset) || $preset === '') {
            return;
        }

        $config = config("post2site.presets.{$preset}");
        if (! is_array($config)) {
            return;
        }

        config(['post2site' => array_replace_recursive(config('post2site', []), $config)]);
    }
}
