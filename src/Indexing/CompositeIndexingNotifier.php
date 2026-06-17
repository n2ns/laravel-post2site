<?php

namespace N2ns\LaravelPost2Site\Indexing;

use N2ns\LaravelPost2Site\Contracts\IndexingNotifier;
use N2ns\LaravelPost2Site\Data\IndexingPlan;
use N2ns\LaravelPost2Site\Data\IndexingResult;

class CompositeIndexingNotifier implements IndexingNotifier
{
    public function __construct(
        private readonly IndexNowNotifier $indexNow,
    ) {}

    public function notify(IndexingPlan $plan): IndexingResult
    {
        if (! config('post2site.indexing.indexnow.enabled', false)) {
            return new IndexingResult('indexnow', 'skipped');
        }

        return $this->indexNow->notify($plan);
    }
}
