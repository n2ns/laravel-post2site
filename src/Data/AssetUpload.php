<?php

namespace N2ns\LaravelPost2Site\Data;

final readonly class AssetUpload
{
    public function __construct(
        public ?string $draftId,
        public string $purpose,
        public string $filename,
        public string $contentType,
        public string $dataBase64,
        public array $metadata = [],
    ) {}
}
