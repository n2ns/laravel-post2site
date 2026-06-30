<?php

namespace N2ns\LaravelPost2Site\Models;

use Illuminate\Database\Eloquent\Model;

class Post2SiteIdempotencyRecord extends Model
{
    protected $table = 'post2site_idempotency_records';

    protected $fillable = [
        'client_key_id',
        'route',
        'resource_id',
        'idempotency_key',
        'payload_hash',
        'status',
        'response',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
        ];
    }
}
