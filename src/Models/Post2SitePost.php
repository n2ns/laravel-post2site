<?php

namespace N2ns\LaravelPost2Site\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post2SitePost extends Model
{
    protected $table = 'post2site_posts';

    protected $fillable = [
        'type',
        'content_scope',
        'status',
        'slug',
        'thumbnail',
        'published_at',
        'target_type',
        'target_id',
        'target_link',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(Post2SitePostTranslation::class, 'post2site_post_id');
    }
}
