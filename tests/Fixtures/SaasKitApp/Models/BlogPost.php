<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogPost extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(BlogPostTranslation::class);
    }

    public function publicUrl(?string $locale = null): ?string
    {
        if ($this->status !== 'published' || ! $this->published_at?->lte(now())) {
            return null;
        }

        $prefix = $locale === 'en' || $locale === null ? '' : '/'.$locale;
        if ($this->content_scope && str_starts_with($this->content_scope, 'product:')) {
            $code = substr($this->content_scope, strlen('product:'));

            return rtrim((string) config('app.url'), '/')."{$prefix}/{$code}/guides/{$this->slug}";
        }

        return rtrim((string) config('app.url'), '/')."{$prefix}/blog/{$this->slug}";
    }
}
