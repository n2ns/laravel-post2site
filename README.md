# Laravel Post2Site

[![Version](https://img.shields.io/badge/version-0.2.1-blue.svg)](https://github.com/n2ns/laravel-post2site)
[![Total Downloads](https://img.shields.io/packagist/dt/n2ns/laravel-post2site)](https://packagist.org/packages/n2ns/laravel-post2site)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](./LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.2-8892bf.svg)](https://www.php.net)
[![Laravel Version Support](https://img.shields.io/badge/Laravel-12%20%7C%2013-red)](https://laravel.com)


Laravel backend package for the N2N Post2Site Content Publishing API Contract. It lets a Laravel site securely accept content from the [`n2n-post2site`](https://github.com/n2ns/n2n-post2site) MCP client.

It provides protected publishing routes, package-owned staging tables for drafts and translations, optional configurable publishing into host Eloquent models, custom `PublicationTarget` adapters for complex sites, and optional IndexNow submission after posts are published.

The package is generic: `content_scope` is opaque `kind:key` metadata, and URL shape, scope validation, scope context, the publish target, and indexing are all host-bindable contracts. No host-specific categories are baked in.

For common sites, Post2Site can also be activated through presets. The first-party preset is `laravel_saas_kit`, which publishes into SaaS Kit's `blog_posts` and `blog_post_translations` tables and supports product guides through `product:{code}` content scopes.

## 📋 Requirements

- PHP 8.2+ with Laravel 12.
- PHP 8.3+ with Laravel 13.

The Composer constraints allow Laravel 12 and 13. The current locked test
baseline validates Laravel 12; keep Laravel 13 host integrations under explicit
project verification until a Laravel 13 CI matrix is added.

## 🚀 Installation

```bash
composer require n2ns/laravel-post2site
php artisan vendor:publish --tag=post2site-config
php artisan migrate
php artisan post2site:key "Production MCP"
```

The command prints the plaintext API key once — store it as the MCP client's `CONTENT_API_KEY`. Point the client's `CONTENT_API_BASE_URL` at `https://your-host/<route_prefix>` (default route prefix `api/v1/mcp`).

Configure the [`n2n-post2site`](https://github.com/n2ns/n2n-post2site) MCP client to point at this host:

```json
{
  "mcpServers": {
    "n2n-post2site": {
      "command": "npx",
      "args": ["-y", "n2n-post2site"],
      "env": {
        "CONTENT_API_BASE_URL": "https://your-host/api/v1/mcp",
        "CONTENT_API_KEY": "the-key-printed-above"
      }
    }
  }
}
```

## 📰 Publishing modes

Set `POST2SITE_PUBLISHING_MODE` (or `post2site.publishing.mode`):

- **`review`** (default) — mark drafts published in the staging tables with zero host code. The public `link` is generated from `public_url.pattern`.
- **`configurable`** — map fields to a host Eloquent model via config; no PHP class needed.
- **`adapter`** — bind a custom `PublicationTarget` for relations, taxonomy, external CMS, etc.

See the [Host Integration Guide](docs/integration.md) for each mode.

## 🧩 Blog targets

Current presets:

- `laravel_saas_kit` — first-party adapter. Supports blog posts and product guides. Target app currently uses Laravel 12.
- `austintoddj_canvas` — adapter preset for `austintoddj/canvas`.
- `bjuppa_laravel_blog` — configurable preset for `bjuppa/laravel-blog`.
- `stephenjude_filament_blog` — configurable preset for `stephenjude/filament-blog` 5.x. Hosts still need to ensure author/category requirements fit their schema.

## 🔑 Key behavior

- **Auth** — database-driver API keys are stored as deterministic SHA-256 hashes and matched via a unique index (O(1)); the static driver compares a single configured key.
- **Rate limiting** — every route is throttled. Tune via `POST2SITE_RATE_LIMIT` (`max,minutes`, default `60,1`).
- **Search** — `?q=` uses a `FULLTEXT` index on MySQL/MariaDB (created by the migration on those drivers) and falls back to `LIKE` elsewhere (e.g. SQLite).
- **content_scope** — optional `kind:key`; required for the types in `content.scoped_types` (default `['guide']`) and prohibited for others. Kinds and key resolution are host-configurable.

## 🧩 Extension contracts

Bound from `post2site.bindings` and overridable: `PostRepository`, `PublicationTarget`, `PublicUrlResolver`, `ContentScopeValidator`, `ScopeContextProvider`, `IndexingNotifier`. See [Architecture](docs/architecture.md).

## 🧪 Testing

```bash
composer test       # PHPUnit
composer pint:test  # code style
```

## 📦 Composer Metadata

Packagist shows Composer link fields from `composer.json`:

- **suggests** — optional packages that work with this package but are not required. Post2Site uses this for supported optional targets such as SaaS Kit, Canvas, and known blog packages.
- **provides** — virtual packages implemented by this package. Post2Site does not currently implement a shared virtual package, so this stays empty.
- **conflicts** — packages or versions that cannot be installed together with this package. Leave empty unless a real incompatibility is verified.
- **replaces** — packages whose code is shipped/replaced by this package. Post2Site does not replace another package, so this stays empty.

## 📖 Documentation

- [Host Integration Guide](docs/integration.md)
- [Blog Targets](docs/blog-packages.md)
- [Architecture](docs/architecture.md)
- [Changelog](CHANGELOG.md)
- [Contributing](CONTRIBUTING.md)

## 📄 License

This project is licensed under the [MIT License](./LICENSE).

---

Built by [N2NS Lab](https://n2ns.com), an open-source lab for practical AI developer tools.
