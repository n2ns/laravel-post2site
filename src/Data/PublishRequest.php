<?php

namespace N2ns\LaravelPost2Site\Data;

final readonly class PublishRequest
{
    public function __construct(
        public bool $userConfirmedPublish,
        public int $expectedVersion,
        public array $acknowledgedWarnings,
        public string $idempotencyKey,
        public ?string $ifMatch,
    ) {}
}
