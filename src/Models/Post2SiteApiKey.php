<?php

namespace N2ns\LaravelPost2Site\Models;

use Illuminate\Database\Eloquent\Model;

class Post2SiteApiKey extends Model
{
    protected $table = 'post2site_api_keys';

    protected $fillable = [
        'name',
        'key_hash',
        'revoked_at',
        'expires_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }
}
