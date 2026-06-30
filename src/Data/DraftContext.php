<?php

namespace N2ns\LaravelPost2Site\Data;

final readonly class DraftContext
{
    /**
     * @param  list<string>  $assetRefs
     */
    public function __construct(
        public ?string $draftId,
        public string $mode,
        public ?string $targetIdentifier,
        public array $contentPayload,
        public array $assetRefs,
        public int $version,
        public string $clientKeyId,
        public string $status,
    ) {}
}
