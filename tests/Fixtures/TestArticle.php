<?php

namespace N2ns\LaravelPost2Site\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestArticle extends Model
{
    protected $table = 'test_articles';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'excerpt' => 'array',
            'content' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function setTranslation(string $field, string $locale, mixed $value): static
    {
        $translations = $this->getAttribute($field) ?? [];
        $translations[$locale] = $value;
        $this->setAttribute($field, $translations);

        return $this;
    }
}
