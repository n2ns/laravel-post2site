<?php

namespace N2ns\LaravelPost2Site\Repositories;

use N2ns\LaravelPost2Site\Contracts\PublicationTarget;
use N2ns\LaravelPost2Site\Data\PostData;
use N2ns\LaravelPost2Site\Data\PublishedPostData;

class NullPublicationTarget implements PublicationTarget
{
    public function publish(PostData $post): PublishedPostData
    {
        return new PublishedPostData(
            targetId: $post->id,
            targetType: 'post2site',
            link: $post->link ?? '',
            publishedAt: now(),
        );
    }
}
