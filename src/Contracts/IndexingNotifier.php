<?php

namespace N2ns\LaravelPost2Site\Contracts;

use N2ns\LaravelPost2Site\Data\IndexingPlan;
use N2ns\LaravelPost2Site\Data\IndexingResult;

interface IndexingNotifier
{
    public function notify(IndexingPlan $plan): IndexingResult;
}
