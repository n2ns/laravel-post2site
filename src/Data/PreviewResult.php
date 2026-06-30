<?php

namespace N2ns\LaravelPost2Site\Data;

use Illuminate\Support\Carbon;

final readonly class PreviewResult
{
    public function __construct(
        public ?string $previewUrl,
        public array $previewUrls = [],
        public ?Carbon $expiresAt = null,
        public array $hostMetadata = [],
    ) {}

    public function toArray(string $draftId): array
    {
        return [
            'draft_id' => $draftId,
            'preview_url' => $this->previewUrl,
            'preview_urls' => $this->previewUrls,
            'expires_at' => $this->expiresAt?->toJSON(),
            'host_metadata' => $this->hostMetadata,
        ];
    }
}
