<?php

use Illuminate\Support\Facades\Route;
use N2ns\LaravelPost2Site\Http\Controllers\IndexNowKeyController;
use N2ns\LaravelPost2Site\Http\Controllers\Post2SiteController;
use N2ns\LaravelPost2Site\Http\Middleware\AuthenticatePost2SiteKey;

Route::prefix(config('post2site.route_prefix', 'api/v1/mcp'))
    ->middleware(array_merge(
        config('post2site.route_middleware', ['api']),
        ['throttle:'.config('post2site.rate_limit', '60,1')],
        [AuthenticatePost2SiteKey::class],
    ))
    ->group(function (): void {
        Route::get('/capabilities', [Post2SiteController::class, 'capabilities']);
        Route::get('/scopes/{contentScope}', [Post2SiteController::class, 'scopeContext'])
            ->where('contentScope', '.+');
        Route::get('/posts', [Post2SiteController::class, 'index']);
        Route::post('/posts', [Post2SiteController::class, 'store']);
        Route::get('/posts/{idOrSlug}', [Post2SiteController::class, 'show']);
        Route::match(['put', 'patch'], '/posts/{idOrSlug}', [Post2SiteController::class, 'update']);
        Route::post('/posts/{idOrSlug}/publish', [Post2SiteController::class, 'publish']);
    });

if (config('post2site.indexing.indexnow.auto_publish_key_file', false)) {
    Route::get('/{key}.txt', IndexNowKeyController::class)
        ->middleware('throttle:'.config('post2site.rate_limit', '60,1'))
        ->where('key', '[A-Za-z0-9-]{8,128}');
}
