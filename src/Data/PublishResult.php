<?php

namespace N2ns\LaravelPost2Site\Data;

final readonly class PublishResult
{
    public function __construct(
        public string $resourceId,
        public string $status,
        public ?string $canonicalUrl = null,
        public array $localizedUrls = [],
        public array $hostMetadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'resource_id' => $this->resourceId,
            'status' => $this->status,
            'canonical_url' => $this->canonicalUrl,
            'localized_urls' => $this->localizedUrls,
            'host_metadata' => $this->hostMetadata,
        ];
    }
}
