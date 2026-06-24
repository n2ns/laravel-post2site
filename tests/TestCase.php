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

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table): void {
                $table->id();
                $table->string('code')->unique();
                $table->string('name')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('blog_posts')) {
            Schema::create('blog_posts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('slug')->unique();
                $table->string('title')->nullable();
                $table->text('content')->nullable();
                $table->text('excerpt')->nullable();
                $table->string('status')->default('draft');
                $table->string('type')->default('technical');
                $table->string('content_scope')->nullable();
                $table->string('thumbnail')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('blog_post_translations')) {
            Schema::create('blog_post_translations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('blog_post_id');
                $table->string('locale', 10)->index();
                $table->string('title')->nullable();
                $table->text('content')->nullable();
                $table->text('excerpt')->nullable();
                $table->timestamps();

                $table->unique(['blog_post_id', 'locale']);
            });
        }

        if (! Schema::hasTable('canvas_users')) {
            Schema::create('canvas_users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->string('email')->unique();
                $table->string('username')->nullable();
                $table->string('password')->nullable();
                $table->text('summary')->nullable();
                $table->string('avatar')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('canvas_posts')) {
            Schema::create('canvas_posts', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('slug');
                $table->string('title');
                $table->string('summary')->nullable();
                $table->longText('body')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->string('featured_image')->nullable();
                $table->string('featured_image_caption')->nullable();
                $table->uuid('user_id')->index();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['slug', 'user_id']);
            });
        }
    }
}
