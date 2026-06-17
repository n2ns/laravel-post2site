<?php

namespace N2ns\LaravelPost2Site\Indexing;

use Illuminate\Support\Facades\Http;
use N2ns\LaravelPost2Site\Contracts\IndexingNotifier;
use N2ns\LaravelPost2Site\Data\IndexingPlan;
use N2ns\LaravelPost2Site\Data\IndexingResult;

class IndexNowNotifier implements IndexingNotifier
{
    public function notify(IndexingPlan $plan): IndexingResult
    {
        $key = config('post2site.indexing.indexnow.key');

        if (! is_string($key) || preg_match('/^[A-Za-z0-9-]{8,128}$/', $key) !== 1) {
            return new IndexingResult('indexnow', 'skipped');
        }

        $payload = [
            'host' => $plan->host,
            'key' => $key,
            'urlList' => [$plan->url],
        ];

        if (filled(config('post2site.indexing.indexnow.key_location'))) {
            $payload['keyLocation'] = config('post2site.indexing.indexnow.key_location');
        }

        $response = Http::timeout(10)->connectTimeout(5)->post(config('post2site.indexing.indexnow.endpoint'), $payload);

        return new IndexingResult(
            driver: 'indexnow',
            status: in_array($response->status(), [200, 202], true) ? 'accepted' : 'failed',
            httpStatus: $response->status(),
            responseBody: $response->body(),
        );
    }
}
