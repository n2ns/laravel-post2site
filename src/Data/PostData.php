<?php

namespace N2ns\LaravelPost2Site\Data;

use Carbon\CarbonInterface;

final readonly class PostData
{
    public function __construct(
        public string|int $id,
        public string $slug,
        public string $type,
        public string $status,
        public ?string $contentScope,
        public string $locale,
        public string $title,
        public ?string $excerpt,
        public ?string $content,
        public ?string $thumbnail,
        public ?CarbonInterface $publishedAt,
        public ?CarbonInterface $updatedAt,
        public array $availableLocales,
        public ?string $link,
    ) {}
}
