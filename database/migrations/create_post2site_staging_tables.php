<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post2site_drafts', function (Blueprint $table): void {
            $table->string('draft_id')->primary();
            $table->string('mode')->index();
            $table->string('target_identifier')->nullable()->index();
            $table->string('status')->default('draft')->index();
            $table->json('content_payload');
            $table->json('validation_state')->nullable();
            $table->json('asset_refs')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->json('publish_confirmation_state')->nullable();
            $table->json('publish_result')->nullable();
            $table->string('client_key_id')->index();
            $table->string('client_name')->nullable();
            $table->json('client_metadata')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('post2site_assets', function (Blueprint $table): void {
            $table->string('asset_id')->primary();
            $table->string('draft_id')->nullable()->index();
            $table->string('client_key_id')->index();
            $table->string('purpose');
            $table->string('filename');
            $table->string('content_type');
            $table->unsignedInteger('byte_size')->default(0);
            $table->string('url')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('validation')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('post2site_assets');
        Schema::dropIfExists('post2site_drafts');
    }
};
