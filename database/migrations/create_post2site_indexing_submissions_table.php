<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post2site_indexing_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->nullable()->index();
            $table->string('url');
            $table->string('driver');
            $table->string('status')->default('queued')->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_submitted_at')->nullable();
            $table->timestamps();

            // Supports the dedupe lookup: url + driver + recency.
            $table->index(['url', 'driver', 'last_submitted_at'], 'post2site_submissions_dedupe_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post2site_indexing_submissions');
    }
};
