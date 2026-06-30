<?php

namespace N2ns\LaravelPost2Site\Data;

final readonly class InventoryResult
{
    public function __construct(
        public array $items = [],
        public ?string $nextCursor = null,
        public array $summary = [],
        public array $hostMetadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'next_cursor' => $this->nextCursor,
            'summary' => $this->summary,
            'host_metadata' => $this->hostMetadata,
        ];
    }
}
