<?php

namespace N2ns\LaravelPost2Site\Tests\Fixtures;

use N2ns\LaravelPost2Site\Contracts\ContentScopeValidator;

/**
 * Stand-in for a host validator that resolves the key against a real entity.
 * Here, only the key "allowed" exists.
 */
class RejectingContentScopeValidator implements ContentScopeValidator
{
    public function validate(string $kind, string $key): ?string
    {
        return $key === 'allowed'
            ? null
            : 'The selected content_scope does not exist.';
    }
}
