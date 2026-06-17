<?php

namespace N2ns\LaravelPost2Site\Repositories;

use N2ns\LaravelPost2Site\Contracts\ScopeContextProvider;

class ConfigScopeContextProvider implements ScopeContextProvider
{
    public function availableScopes(): array
    {
        return collect(config('post2site.scopes', []))
            ->map(fn (array $context, string $scope): array => [
                'content_scope' => $scope,
                'name' => $context['name'] ?? $scope,
            ])
            ->values()
            ->all();
    }

    public function contextForScope(string $contentScope): ?array
    {
        $context = config("post2site.scopes.{$contentScope}");

        return is_array($context)
            ? array_merge(['content_scope' => $contentScope], $context)
            : null;
    }
}
