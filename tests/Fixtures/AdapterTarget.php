<?php

namespace N2ns\LaravelPost2Site\Tests\Fixtures;

use N2ns\LaravelPost2Site\Contracts\PublicationTarget;
use N2ns\LaravelPost2Site\Data\PostData;
use N2ns\LaravelPost2Site\Data\PublishedPostData;

class AdapterTarget implements PublicationTarget
{
    public function publish(PostData $post): PublishedPostData
    {
        return new PublishedPostData(
            targetId: 'adapter-'.$post->id,
            targetType: self::class,
            link: 'https://example.com/custom/'.$post->slug,
            publishedAt: now(),
        );
    }
}
