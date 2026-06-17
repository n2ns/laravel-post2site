<?php

namespace N2ns\LaravelPost2Site\Support;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use N2ns\LaravelPost2Site\Contracts\ContentScopeValidator;

class ContentScopeRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        // Contract-level format: content_scope is always `kind:key`.
        if (! is_string($value) || preg_match('/^[a-z][a-z0-9_-]*:[a-z0-9][a-z0-9_-]*$/', $value) !== 1) {
            $fail('The :attribute field must use the kind:key format.');

            return;
        }

        [$kind, $key] = explode(':', $value, 2);

        // Optional convenience whitelist. Empty config = accept any kind.
        $kinds = config('post2site.content_scope.kinds', []);
        if ($kinds !== [] && ! in_array($kind, $kinds, true)) {
            $fail('The :attribute kind is not supported.');

            return;
        }

        // Host-specific resolution (for example, key must be a real product).
        $error = app(ContentScopeValidator::class)->validate($kind, $key);
        if ($error !== null) {
            $fail($error);
        }
    }
}
