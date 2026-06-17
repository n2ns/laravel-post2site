<?php

namespace N2ns\LaravelPost2Site\Contracts;

use N2ns\LaravelPost2Site\Data\PostData;

interface PublicUrlResolver
{
    public function resolve(PostData $post): ?string;
}
