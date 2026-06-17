<?php

namespace N2ns\LaravelPost2Site\Support;

use N2ns\LaravelPost2Site\Contracts\ContentScopeValidator;

/**
 * Default validator: the package is host-agnostic, so any well-formed
 * `kind:key` is accepted. Hosts bind their own implementation to check that a
 * key resolves to a real entity.
 */
class NullContentScopeValidator implements ContentScopeValidator
{
    public function validate(string $kind, string $key): ?string
    {
        return null;
    }
}
