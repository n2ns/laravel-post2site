<?php

namespace N2ns\LaravelPost2Site;

use Illuminate\Support\ServiceProvider;
use LogicException;
use N2ns\LaravelPost2Site\Console\Commands\CreateApiKeyCommand;
use N2ns\LaravelPost2Site\Contracts\IndexingNotifier;
use N2ns\LaravelPost2Site\Contracts\Post2SiteAdapter;

class LaravelPost2SiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->replaceConfigRecursivelyFrom(__DIR__.'/../config/post2site.php', 'post2site');

        $this->app->bind(Post2SiteAdapter::class, function (): Post2SiteAdapter {
            $adapter = config('post2site.bindings.adapter');

            if (! is_string($adapter) || ! is_subclass_of($adapter, Post2SiteAdapter::class)) {
                throw new LogicException('Configure post2site.bindings.adapter with a host Post2SiteAdapter implementation.');
            }

            return $this->app->make($adapter);
        });
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
}
