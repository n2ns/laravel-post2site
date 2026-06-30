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

        $client = $this->clientForKey($plain);

        if ($client === null) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $request->attributes->set('post2site_client_key_id', $client['id']);
        $request->attributes->set('post2site_client_name', $client['name']);

        return $next($request);
    }

    /**
     * @return array{id: string, name: string}|null
     */
    private function clientForKey(string $plain): ?array
    {
        if (config('post2site.auth.driver') === 'static') {
            return hash_equals((string) config('post2site.auth.static_key'), $plain)
                ? ['id' => 'static', 'name' => 'static']
                : null;
        }

        $model = config('post2site.auth.model', Post2SiteApiKey::class);

        $key = $model::query()
            ->where('key_hash', hash('sha256', $plain))
            ->whereNull('revoked_at')
            ->first();

        if ($key === null) {
            return null;
        }

        if ($key->expires_at !== null && $key->expires_at->isPast()) {
            return null;
        }

        // Avoid a write on every request: only refresh last_used_at periodically.
        if ($key->last_used_at === null || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now()])->save();
        }

        return ['id' => (string) $key->getKey(), 'name' => (string) $key->name];
    }
}
