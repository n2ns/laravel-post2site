<?php

namespace N2ns\LaravelPost2Site\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use N2ns\LaravelPost2Site\Models\Post2SiteApiKey;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePost2SiteKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = config('post2site.auth.header', 'X-API-KEY');
        $plain = $request->header($header);

        if (! is_string($plain) || $plain === '') {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if (! $this->validKey($plain)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }

    private function validKey(string $plain): bool
    {
        if (config('post2site.auth.driver') === 'static') {
            return hash_equals((string) config('post2site.auth.static_key'), $plain);
        }

        $model = config('post2site.auth.model', Post2SiteApiKey::class);

        $key = $model::query()
            ->where('key_hash', hash('sha256', $plain))
            ->whereNull('revoked_at')
            ->first();

        if ($key === null) {
            return false;
        }

        if ($key->expires_at !== null && $key->expires_at->isPast()) {
            return false;
        }

        // Avoid a write on every request: only refresh last_used_at periodically.
        if ($key->last_used_at === null || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->save();
        }

        return true;
    }
}
