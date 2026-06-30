<?php

use Illuminate\Support\Facades\Route;
use N2ns\LaravelPost2Site\Http\Controllers\IndexNowKeyController;
use N2ns\LaravelPost2Site\Http\Controllers\McpPublishingController;
use N2ns\LaravelPost2Site\Http\Middleware\AuthenticatePost2SiteKey;

Route::prefix(config('post2site.route_prefix', 'api/v1/mcp'))
    ->middleware(array_merge(
        config('post2site.route_middleware', ['api']),
        ['throttle:'.config('post2site.rate_limit', '60,1')],
        [AuthenticatePost2SiteKey::class],
    ))
    ->group(function (): void {
        Route::get('/capabilities', [McpPublishingController::class, 'capabilities']);
        Route::get('/site-context', [McpPublishingController::class, 'siteContext']);
        Route::get('/editorial-policy', [McpPublishingController::class, 'editorialPolicy']);

        Route::get('/inventory/resources', [McpPublishingController::class, 'inventoryResources']);
        Route::get('/inventory/resources/{target_identifier}', [McpPublishingController::class, 'inventoryResource'])
            ->where('target_identifier', '.+');
        Route::get('/inventory/stats', [McpPublishingController::class, 'inventoryStats']);
        Route::post('/inventory/duplicates', [McpPublishingController::class, 'inventoryDuplicates']);

        Route::post('/working-drafts/validate', [McpPublishingController::class, 'validateWorkingDraft']);

        Route::get('/drafts', [McpPublishingController::class, 'drafts']);
        Route::post('/drafts', [McpPublishingController::class, 'storeDraft']);
        Route::get('/drafts/{draft_id}', [McpPublishingController::class, 'showDraft']);
        Route::patch('/drafts/{draft_id}', [McpPublishingController::class, 'updateDraft']);
        Route::post('/drafts/{draft_id}/validate', [McpPublishingController::class, 'validateDraft']);
        Route::get('/drafts/{draft_id}/preview', [McpPublishingController::class, 'previewDraft']);
        Route::post('/drafts/{draft_id}/publish', [McpPublishingController::class, 'publishDraft']);

        Route::post('/assets', [McpPublishingController::class, 'storeAsset']);
    });

if (config('post2site.indexing.indexnow.auto_publish_key_file', false)) {
    Route::get('/{key}.txt', IndexNowKeyController::class)
        ->middleware('throttle:'.config('post2site.rate_limit', '60,1'))
        ->where('key', '[A-Za-z0-9-]{8,128}');
}
