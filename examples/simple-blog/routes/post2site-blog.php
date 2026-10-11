<?php

use App\Models\BlogPost;
use Illuminate\Support\Facades\Route;
use N2ns\LaravelPost2Site\Models\Post2SiteDraft;

Route::get('/posts/{slug}', function (string $slug) {
    $post = BlogPost::query()->where('slug', $slug)->firstOrFail();

    return view('post2site-blog', ['content' => $post->toArray(), 'preview' => false]);
})->name('post2site-blog.show');

Route::get('/post2site-preview/{draft}', function (string $draft) {
    $staged = Post2SiteDraft::query()->findOrFail($draft);

    return response()->view('post2site-blog', [
        'content' => $staged->content_payload,
        'preview' => true,
    ])->header('Cache-Control', 'private, no-store');
})->middleware('signed')->name('post2site-blog.preview');
