<?php

namespace N2ns\LaravelPost2Site\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post2SitePostTranslation extends Model
{
    protected $table = 'post2site_post_translations';

    protected $fillable = [
        'locale',
        'title',
        'excerpt',
        'content',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post2SitePost::class, 'post2site_post_id');
    }
}
