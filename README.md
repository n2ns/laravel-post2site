# Laravel Post2Site

[![Latest Stable Version](https://img.shields.io/packagist/v/n2ns/laravel-post2site)](https://packagist.org/packages/n2ns/laravel-post2site)
[![Total Downloads](https://img.shields.io/packagist/dt/n2ns/laravel-post2site)](https://packagist.org/packages/n2ns/laravel-post2site)
[![License](https://img.shields.io/github/license/n2ns/laravel-post2site)](https://github.com/n2ns/laravel-post2site/blob/main/LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.2-8892bf.svg)](https://www.php.net)
[![Laravel Version Support](https://img.shields.io/badge/Laravel-12%20%7C%2013-red)](https://laravel.com)


Laravel backend package for the N2N Post2Site Content Publishing API Contract. It lets a Laravel site securely accept content from the [`n2n-post2site`](https://github.com/n2ns/n2n-post2site) MCP client.

It provides protected publishing routes, package-owned staging tables for drafts and translations, optional configurable publishing into host Eloquent models, custom `PublicationTarget` adapters for complex sites, and optional IndexNow submission after posts are published.

The package is generic: `content_scope` is opaque `kind:key` metadata, and URL shape, scope validation, scope context, the publish target, and indexing are all host-bindable contracts. No host-specific categories are baked in.

## Requirements

- PHP 8.2+
- Laravel 12 or 13

## Installation

```bash
composer require n2ns/laravel-post2site
php artisan vendor:publish --tag=post2site-config
php artisan vendor:publish --tag=post2site-migrations
php artisan migrate
php artisan post2site:key "Production MCP"
```

The command prints the plaintext API key once — store it as the MCP client's `N2N_API_KEY`. Point the client's `CONTENT_API_BASE_URL` at `https://your-host/<route_prefix>` (default route prefix `api/v1/mcp`).

## Publishing modes

Set `POST2SITE_PUBLISHING_MODE` (or `post2site.publishing.mode`):

- **`review`** (default) — mark drafts published in the staging tables with zero host code. The public `link` is generated from `public_url.pattern`.
- **`configurable`** — map fields to a host Eloquent model via config; no PHP class needed.
- **`adapter`** — bind a custom `PublicationTarget` for relations, taxonomy, external CMS, etc.

See the [Host Integration Guide](docs/integration.md) for each mode.

## Key behavior

- **Auth** — database-driver API keys are stored as deterministic SHA-256 hashes and matched via a unique index (O(1)); the static driver compares a single configured key.
- **Rate limiting** — every route is throttled. Tune via `POST2SITE_RATE_LIMIT` (`max,minutes`, default `60,1`).
- **Search** — `?q=` uses a `FULLTEXT` index on MySQL/MariaDB (created by the migration on those drivers) and falls back to `LIKE` elsewhere (e.g. SQLite).
- **content_scope** — optional `kind:key`; required for the types in `content.scoped_types` (default `['guide']`) and prohibited for others. Kinds and key resolution are host-configurable.

## Extension contracts

Bound from `post2site.bindings` and overridable: `PostRepository`, `PublicationTarget`, `PublicUrlResolver`, `ContentScopeValidator`, `ScopeContextProvider`, `IndexingNotifier`. See [Architecture](docs/architecture.md).

## Testing

```bash
composer test       # PHPUnit
composer pint:test  # code style
```

## Documentation

- [Host Integration Guide](docs/integration.md)
- [Architecture](docs/architecture.md)
- [Changelog](CHANGELOG.md)
- [Contributing](CONTRIBUTING.md)

## 📄 License

This project is licensed under the [MIT License](./LICENSE).

---

Built by [N2NS Lab](https://n2ns.com), the open-source lab of [datafrog.io](https://datafrog.io) for practical AI developer tools.

