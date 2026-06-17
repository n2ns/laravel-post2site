<?php

namespace N2ns\LaravelPost2Site\Contracts;

interface ScopeContextProvider
{
    /**
     * The content_scopes that have controlled context available.
     */
    public function availableScopes(): array;

    /**
     * Controlled context for a single content_scope, or null when none exists.
     */
    public function contextForScope(string $contentScope): ?array;
}
