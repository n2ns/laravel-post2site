<?php

namespace N2ns\LaravelPost2Site\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post2SiteDraft extends Model
{
    protected $table = 'post2site_drafts';

    protected $primaryKey = 'draft_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'draft_id',
        'mode',
        'target_identifier',
        'status',
        'content_payload',
        'validation_state',
        'asset_refs',
        'version',
        'publish_confirmation_state',
        'publish_result',
        'client_key_id',
        'client_name',
        'client_metadata',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'content_payload' => 'array',
            'validation_state' => 'array',
            'asset_refs' => 'array',
            'version' => 'integer',
            'publish_confirmation_state' => 'array',
            'publish_result' => 'array',
            'client_metadata' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Post2SiteAsset::class, 'draft_id', 'draft_id');
    }
}
