<?php

namespace N2ns\LaravelPost2Site\Data;

final readonly class IndexingResult
{
    public function __construct(
        public string $driver,
        public string $status,
        public ?int $httpStatus = null,
        public ?string $responseBody = null,
    ) {}
}
