<?php

namespace N2ns\LaravelPost2Site\Data;

use Carbon\CarbonInterface;

final readonly class IndexingPlan
{
    public function __construct(
        public string $url,
        public string $host,
        public string|int|null $postId,
        public ?string $contentScope,
        public ?CarbonInterface $publishedAt,
    ) {}
}
