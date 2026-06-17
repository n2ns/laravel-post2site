<?php

namespace N2ns\LaravelPost2Site\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use N2ns\LaravelPost2Site\Data\PostData;

final class Post2SitePostPublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly PostData $post,
    ) {}
}
