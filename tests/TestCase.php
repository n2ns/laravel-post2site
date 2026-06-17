<?php

namespace N2ns\LaravelPost2Site\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use N2ns\LaravelPost2Site\LaravelPost2SiteServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelPost2SiteServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.url', 'https://example.com');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('post2site.auth.driver', 'static');
        $app['config']->set('post2site.auth.static_key', 'test-key');
        $app['config']->set('post2site.route_middleware', []);
        $app['config']->set('queue.default', 'sync');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('test_articles')) {
            Schema::create('test_articles', function (Blueprint $table): void {
                $table->id();
                $table->string('slug')->unique();
                $table->string('type')->nullable();
                $table->string('content_scope')->nullable();
                $table->string('status')->nullable();
                $table->string('thumbnail')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->json('title')->nullable();
                $table->json('excerpt')->nullable();
                $table->json('content')->nullable();
                $table->timestamps();
            });
        }
    }
}
