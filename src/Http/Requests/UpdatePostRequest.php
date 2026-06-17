<?php

namespace N2ns\LaravelPost2Site\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use N2ns\LaravelPost2Site\Models\Post2SitePost;
use N2ns\LaravelPost2Site\Support\ContentScopeRule;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $idOrSlug = $this->route('idOrSlug');
        $uniqueSlug = Rule::unique(Post2SitePost::class, 'slug')
            ->ignore($idOrSlug, is_numeric($idOrSlug) ? 'id' : 'slug');

        return [
            'type' => ['nullable', Rule::in(config('post2site.content.types', []))],
            'content_scope' => ['nullable', 'string', new ContentScopeRule],
            'slug' => ['nullable', 'string', 'max:255', $uniqueSlug],
            'locale' => ['nullable', Rule::in(config('post2site.content.locales', []))],
            'title' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'string'],
            'status' => ['prohibited'],
            'published_at' => ['prohibited'],
            'user_id' => ['prohibited'],
            'author' => ['prohibited'],
        ];
    }
}
