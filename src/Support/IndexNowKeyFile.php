<?php

namespace N2ns\LaravelPost2Site\Support;

class IndexNowKeyFile
{
    public function validKey(string $key): bool
    {
        return preg_match('/^[A-Za-z0-9-]{8,128}$/', $key) === 1;
    }
}
