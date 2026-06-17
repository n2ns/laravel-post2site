<?php

namespace N2ns\LaravelPost2Site\Indexing;

use N2ns\LaravelPost2Site\Contracts\IndexingNotifier;
use N2ns\LaravelPost2Site\Data\IndexingPlan;
use N2ns\LaravelPost2Site\Data\IndexingResult;

class NullIndexingNotifier implements IndexingNotifier
{
    public function notify(IndexingPlan $plan): IndexingResult
    {
        return new IndexingResult('null', 'skipped');
    }
}
