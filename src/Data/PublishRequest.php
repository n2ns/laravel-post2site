<?php

namespace N2ns\LaravelPost2Site\Data;

final readonly class PublishRequest
{
    public function __construct(
        public bool $publishConfirmed,
        public array $acknowledgedWarnings,
    ) {}
}
