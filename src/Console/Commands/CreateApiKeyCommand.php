<?php

namespace N2ns\LaravelPost2Site\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use N2ns\LaravelPost2Site\Models\Post2SiteApiKey;

class CreateApiKeyCommand extends Command
{
    protected $signature = 'post2site:key
        {name : Human-readable key name}
        {--expires-at= : Optional expiration datetime}
        {--plain : Print only the generated plain key}';

    protected $description = 'Create a Post2Site API key.';

    public function handle(): int
    {
        $plain = 'p2s_'.Str::random(48);

        Post2SiteApiKey::query()->create([
            'name' => $this->argument('name'),
            'key_hash' => hash('sha256', $plain),
            'expires_at' => $this->option('expires-at'),
        ]);

        if ($this->option('plain')) {
            $this->line($plain);

            return self::SUCCESS;
        }

        $this->info('Post2Site API key created. Store this value now; it will not be shown again.');
        $this->line($plain);
        $this->comment('Configure this value as the MCP client N2N_API_KEY.');

        return self::SUCCESS;
    }
}
