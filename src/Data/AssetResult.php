<?php

namespace N2ns\LaravelPost2Site\Data;

final readonly class AssetResult
{
    public function __construct(
        public string $assetId,
        public string $purpose,
        public ?string $url,
        public string $contentType,
        public ?int $width = null,
        public ?int $height = null,
        public array $validation = ['accepted' => true, 'warnings' => []],
        public array $metadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'asset_id' => $this->assetId,
            'purpose' => $this->purpose,
            'url' => $this->url,
            'content_type' => $this->contentType,
            'width' => $this->width,
            'height' => $this->height,
            'validation' => $this->validation,
            'metadata' => $this->metadata,
        ];
    }
}
