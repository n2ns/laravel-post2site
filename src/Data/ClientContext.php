<?php

namespace N2ns\LaravelPost2Site\Data;

use Illuminate\Support\Carbon;

final readonly class ClientContext
{
    public function __construct(
        public string $clientKeyId,
        public string $clientName,
        public string $requestId,
        public Carbon $authenticatedAt,
    ) {}
}
