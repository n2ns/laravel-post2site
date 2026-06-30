<?php

namespace N2ns\LaravelPost2Site\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post2SiteAsset extends Model
{
    protected $table = 'post2site_assets';

    protected $primaryKey = 'asset_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'asset_id',
        'draft_id',
        'client_key_id',
        'purpose',
        'filename',
        'content_type',
        'byte_size',
        'url',
        'width',
        'height',
        'validation',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'validation' => 'array',
            'metadata' => 'array',
        ];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(Post2SiteDraft::class, 'draft_id', 'draft_id');
    }
}
