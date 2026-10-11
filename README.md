# Laravel Post2Site

[![Version](https://img.shields.io/badge/version-0.4.0-blue.svg)](https://github.com/n2ns/laravel-post2site)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](./LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D%208.2-8892bf.svg)](https://www.php.net)
[![Laravel Version Support](https://img.shields.io/badge/Laravel-12%20%7C%2013-red)](https://laravel.com)

Let an AI assistant prepare, preview, and publish content into your Laravel application, with an explicit confirmation before publishing.

This is the Laravel backend for the [`n2n-post2site` MCP bridge](https://github.com/n2ns/n2n-post2site). The bridge connects your AI client to this package's authenticated HTTP API. Your application supplies a `Post2SiteAdapter` that maps drafts to its own models and public pages.

```text
AI client → n2n-post2site (MCP) → Laravel Post2Site (HTTP API)
                              → your adapter → your database and website
```

The package handles API keys, staged drafts, selected assets, validation envelopes, and publish confirmation. It is not an install-and-go CMS: installing it alone does not create an article model, a publishing adapter, or public pages. There is no default content adapter.

**Start with [Your first publish](docs/first-publish.md)** to connect a fresh Laravel app, copy a complete text-blog adapter, and publish a real local page. The [runnable example](examples/simple-blog) includes the model, migration, signed preview, and public route; it is separate from the package's default behavior.

## Requirements

- PHP 8.2+ with Laravel 12, or PHP 8.3+ with Laravel 13 (Laravel's own minimum applies).
- A Laravel application with a database and a host adapter.

## Installation

```bash
composer require n2ns/laravel-post2site
php artisan vendor:publish --tag=post2site-config
```

Implement and bind your [host adapter](#host-adapter), or follow the [first-publish guide](docs/first-publish.md) to use the included example in a fresh app. Set `post2site.bindings.adapter` before calling the API. Then run:

```bash
php artisan migrate
php artisan post2site:key "Production MCP"
```

In your MCP client's **n2n-post2site server entry**, configure these environment variables:

```json
{
  "CONTENT_API_BASE_URL": "https://your-host/api/v1/mcp",
  "CONTENT_API_KEY": "the-key-printed-by-the-command"
}
```

These variables configure the Node.js bridge, not Laravel itself. See the [bridge setup instructions](https://github.com/n2ns/n2n-post2site#connect-your-own-website) for the command and client configuration. By default, Laravel authentication uses API keys stored in `post2site_api_keys`. For local fixtures or controlled environments, `POST2SITE_AUTH_DRIVER=static` can be used with `POST2SITE_API_KEY`.

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

`target_identifier` may contain `/`; callers should percent-encode it in the URL path.

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

There is no default content adapter. The package fails fast until the host binds a concrete `Post2SiteAdapter`.

The adapter provides capabilities, site context, editorial policy, inventory, duplicate checks, content validation, asset extraction, selected-asset storage, preview, and publish. [BlogPostAdapter](examples/simple-blog/app/Post2Site/BlogPostAdapter.php) implements all 11 methods for a create-only text blog. Adapt its model, field rules, and URLs to your existing application; do not bind the test fixture as a real publisher.

Publish requires:

- authenticated API key
- `publish_confirmed = true`

The package validates, calls the host adapter publish method, and stores the draft publish result.

## Stored Package State

The package owns only workflow state:

- `post2site_api_keys`: database-backed API keys.
- `post2site_drafts`: staged server drafts, validation state, publish state, and host payload snapshots.
- `post2site_assets`: selected uploaded asset references and optional draft attribution.
- `post2site_indexing_submissions`: optional indexing submission bookkeeping for host integrations.

Host public content remains behind the `Post2SiteAdapter`.

## Optional Indexing

The package includes an `IndexingNotifier` binding and an IndexNow notifier. IndexNow is disabled unless `POST2SITE_INDEXNOW_ENABLED=true` and `POST2SITE_INDEXNOW_KEY` are configured. The package does not expose a generic URL submission endpoint through the MCP contract.

## Testing

```bash
composer test
composer pint:test
```

## Documentation

- [Your first publish: fresh Laravel app to a real public page](docs/first-publish.md)
- [Complete text-blog example](examples/simple-blog)
- [Companion MCP bridge](https://github.com/n2ns/n2n-post2site)
- [Architecture](docs/architecture.md)
- [Changelog](CHANGELOG.md)
- [Contributing](CONTRIBUTING.md)

## License

This project is licensed under the [MIT License](./LICENSE).
