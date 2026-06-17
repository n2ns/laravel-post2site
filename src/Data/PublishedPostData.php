<?php

namespace N2ns\LaravelPost2Site\Data;

use Carbon\CarbonInterface;

final readonly class PublishedPostData
{
    public function __construct(
        public string|int $targetId,
        public string $targetType,
        public string $link,
        public ?CarbonInterface $publishedAt = null,
        public array $metadata = [],
    ) {}
}
