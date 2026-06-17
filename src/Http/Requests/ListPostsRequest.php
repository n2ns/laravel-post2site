<?php

namespace N2ns\LaravelPost2Site\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPostsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(config('post2site.content.statuses', []))],
            'type' => ['nullable', Rule::in(config('post2site.content.types', []))],
            'content_scope' => ['nullable', 'string'],
            'q' => ['nullable', 'string'],
            'locale' => ['nullable', Rule::in(config('post2site.content.locales', []))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('post2site.content.per_page_max', 100)],
        ];
    }
}
