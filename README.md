# Laravel Post2Site

[![Version](https://img.shields.io/badge/version-0.3.0-blue.svg)](https://github.com/n2ns/laravel-post2site)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](./LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.2-8892bf.svg)](https://www.php.net)
[![Laravel Version Support](https://img.shields.io/badge/Laravel-12%20%7C%2013-red)](https://laravel.com)

Laravel backend package for a generic Post2Site MCP publishing contract.

The package handles the HTTP workflow, staging drafts, selected assets, validation envelopes, and publish confirmation. Host applications provide the content model through a `Post2SiteAdapter`; package DTOs do not define blog fields, categories, topics, locales, URL patterns, authors, or host markers.

## Requirements

- PHP 8.2+
- Laravel 12 or 13

## Installation

```bash
composer require n2ns/laravel-post2site
php artisan vendor:publish --tag=post2site-config
php artisan migrate
php artisan post2site:key "Production MCP"
```

Configure your MCP client with:

```json
{
  "CONTENT_API_BASE_URL": "https://your-host/api/v1/mcp",
  "CONTENT_API_KEY": "the-key-printed-by-the-command"
}
```

## Contract

Default route prefix: `/api/v1/mcp`.

Discovery:

- `GET /capabilities`
- `GET /site-context`
- `GET /editorial-policy`

Inventory:

- `GET /inventory/resources`
- `GET /inventory/resources/{target_identifier}`
- `GET /inventory/stats`
- `POST /inventory/duplicates`

Drafts and assets:

- `POST /working-drafts/validate`
- `GET /drafts`
- `POST /drafts`
- `GET /drafts/{draft_id}`
- `PATCH /drafts/{draft_id}`
- `POST /drafts/{draft_id}/validate`
- `GET /drafts/{draft_id}/preview`
- `POST /drafts/{draft_id}/publish`
- `POST /assets`

Core payload shape:

```json
{
  "mode": "create",
  "target_identifier": "host-resource-identifier",
  "content_payload": {},
  "client_metadata": {}
}
```

`content_payload` is a host-declared JSON object. The package stores it, passes it to the adapter, and reports validation issues using stable field paths such as `content_payload.locales.en.title`; it does not interpret host fields.

## Host Adapter

Bind `N2ns\LaravelPost2Site\Contracts\Post2SiteAdapter`:

```php
'bindings' => [
    'adapter' => App\Post2Site\HostPost2SiteAdapter::class,
],
```

The adapter provides capabilities, site context, editorial policy, inventory, duplicate checks, content validation, asset extraction, selected-asset storage, preview, and publish.

Publish requires:

- authenticated API key
- `publish_confirmed = true`

The package validates, calls the host adapter publish method, and stores the draft publish result.

## Testing

```bash
composer test
composer pint:test
```

## Documentation

- [Architecture](docs/architecture.md)
- [Changelog](CHANGELOG.md)
- [Contributing](CONTRIBUTING.md)

## License

This project is licensed under the [MIT License](./LICENSE).
