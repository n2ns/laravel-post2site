<?php

namespace N2ns\LaravelPost2Site\Models;

use Illuminate\Database\Eloquent\Model;

class Post2SiteIndexingSubmission extends Model
{
    protected $table = 'post2site_indexing_submissions';

    protected $fillable = [
        'post_id',
        'url',
        'driver',
        'status',
        'http_status',
        'response_body',
        'attempts',
        'last_submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'last_submitted_at' => 'datetime',
        ];
    }
}
