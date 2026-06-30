<?php

namespace N2ns\LaravelPost2Site\Data;

final readonly class DuplicateResult
{
    public function __construct(
        public array $issues = [],
        public array $candidates = [],
        public array $hostMetadata = [],
    ) {}

    public function toArray(): array
    {
        return [
            'issues' => $this->issues,
            'candidates' => $this->candidates,
            'host_metadata' => $this->hostMetadata,
        ];
    }
}
