<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post2site_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('type')->default('technical');
            $table->string('content_scope')->nullable()->index();
            $table->string('status')->default('draft')->index();
            $table->string('slug')->unique();
            $table->string('thumbnail')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            $table->string('target_link')->nullable();
            $table->timestamps();
        });

        Schema::create('post2site_post_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post2site_post_id')->constrained('post2site_posts')->cascadeOnDelete();
            $table->string('locale')->index();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->timestamps();

            $table->unique(['post2site_post_id', 'locale'], 'post2site_post_locale_unique');

            // FULLTEXT powers the ?q= search. Only MySQL/MariaDB support it;
            // other drivers (e.g. SQLite in tests) fall back to LIKE in the repository.
            if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->fullText(['title', 'content'], 'post2site_translations_fulltext');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post2site_post_translations');
        Schema::dropIfExists('post2site_posts');
    }
};
