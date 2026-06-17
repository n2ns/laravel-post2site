<?php

namespace N2ns\LaravelPost2Site\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use N2ns\LaravelPost2Site\Models\Post2SitePost;
use N2ns\LaravelPost2Site\Support\ContentScopeRule;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $scopedTypes = config('post2site.content.scoped_types', []);
        $isScopedType = in_array($this->input('type'), $scopedTypes, true);

        return [
            'type' => ['nullable', Rule::in(config('post2site.content.types', []))],
            'content_scope' => [
                Rule::requiredIf($isScopedType),
                Rule::prohibitedIf(! $isScopedType),
                'string',
                new ContentScopeRule,
            ],
            'slug' => ['required', 'string', 'max:255', Rule::unique(Post2SitePost::class, 'slug')],
            'locale' => ['nullable', Rule::in(config('post2site.content.locales', []))],
            'title' => ['required', 'string'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['required', 'string'],
            'thumbnail' => ['nullable', 'string'],
            'status' => ['prohibited'],
            'published_at' => ['prohibited'],
            'user_id' => ['prohibited'],
            'author' => ['prohibited'],
        ];
    }
}
