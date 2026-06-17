<?php

namespace N2ns\LaravelPost2Site\Contracts;

interface ContentScopeValidator
{
    /**
     * Validate a content_scope value already known to be in `kind:key` shape.
     *
     * The host decides what each kind means and whether the key resolves to a
     * real entity (for example, that a `product` key matches an existing product).
     *
     * @return string|null Null when valid, or a validation error message when not.
     */
    public function validate(string $kind, string $key): ?string;
}
