<?php

namespace N2ns\LaravelPost2Site\Data;

final readonly class ValidationResult
{
    public function __construct(
        public bool $publishable,
        public array $blockers = [],
        public array $warnings = [],
        public ?array $normalizedPayload = null,
        public array $hostMetadata = [],
    ) {}

    public function toArray(string $mode): array
    {
        return [
            'mode' => $mode,
            'publishable' => $this->publishable,
            'blockers' => $this->blockers,
            'warnings' => $this->warnings,
            'normalized_payload' => $this->normalizedPayload,
            'host_metadata' => $this->hostMetadata,
        ];
    }
}
