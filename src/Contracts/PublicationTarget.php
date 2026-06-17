<?php

namespace N2ns\LaravelPost2Site\Contracts;

use N2ns\LaravelPost2Site\Data\PostData;
use N2ns\LaravelPost2Site\Data\PublishedPostData;

interface PublicationTarget
{
    public function publish(PostData $post): PublishedPostData;
}
