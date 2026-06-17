<?php

namespace N2ns\LaravelPost2Site\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class IndexNowKeyController extends Controller
{
    public function __invoke(string $key): Response
    {
        abort_unless(hash_equals((string) config('post2site.indexing.indexnow.key'), $key), 404);

        return response($key, 200, ['Content-Type' => 'text/plain']);
    }
}
