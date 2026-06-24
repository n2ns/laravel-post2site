# Architecture

`n2ns/laravel-post2site` is the backend side of the N2N Post2Site Content Publishing API Contract. It exposes protected publishing routes, owns staging tables for MCP-authored drafts and translations, and optionally syncs published content into a host model and an SEO indexing pipeline.

It is generic by design: `content_scope` is opaque `kind:key` metadata, and everything host-specific (URL shape, scope validation, scope context, the publish target, indexing) is a bindable contract. No host categories are baked in.

## Presets

`POST2SITE_PRESET` applies a named group of normal config values before contracts are bound. Presets are not separate execution paths; they are a safer way to select a known adapter/config combination.

Current presets:

- `laravel_saas_kit` — binds the SaaS Kit publication target, URL resolver, product scope validator, and product scope context provider.
- `austintoddj_canvas` — binds the Canvas publication target and URL resolver.
- `bjuppa_laravel_blog` — configures `ConfigurablePublicationTarget` for `Bjuppa\LaravelBlog\Eloquent\BlogEntry`.
- `stephenjude_filament_blog` — configures `ConfigurablePublicationTarget` for `Stephenjude\FilamentBlog\Models\Post`.

Keep presets thin. If a target needs relations, tenant logic, default categories, or non-trivial translation behavior, write an adapter instead of adding conditional logic to the core controller.

## Components

- **Routes** (`routes/api.php`) — registered under `route_prefix` (default `api/v1/mcp`), behind the configured middleware, `throttle`, and the API-key middleware. One unauthenticated IndexNow key route is optional.
- **`Post2SiteController`** — capabilities, list/show/create/update/publish, and scope context. Uses only validated input and returns a uniform envelope via `PostResponseFactory`.
- **`AuthenticatePost2SiteKey`** — `static` driver (`hash_equals`) or `database` driver (SHA-256 `key_hash` looked up via a unique index, O(1)).
- **Staging tables** — `post2site_posts`, `post2site_post_translations`, `post2site_api_keys`, `post2site_indexing_submissions`.
- **`SubmitPublishedPostForIndexing`** — queued, `ShouldBeUnique`, dispatched `afterCommit`; notifies IndexNow and records submissions.

## Extension contracts

All are bound from `post2site.bindings` and overridable by the host:

| Contract | Default | Responsibility |
| --- | --- | --- |
| `PostRepository` | `EloquentPostRepository` | Read/write posts and translations in the staging tables. |
| `PublicationTarget` | `ConfigurablePublicationTarget` | Write a published post into the host model (configurable/adapter modes). |
| `PublicUrlResolver` | `ConfiguredPublicUrlResolver` | Build the public URL for a published post from `public_url.pattern`. |
| `ContentScopeValidator` | `NullContentScopeValidator` | Validate that a `content_scope` key resolves to a real host entity. |
| `ScopeContextProvider` | `ConfigScopeContextProvider` | Provide controlled context for a `content_scope` (and the list of available scopes). |
| `IndexingNotifier` | `CompositeIndexingNotifier` | Submit published URLs to search engines (IndexNow). |

## First-party SaaS Kit adapter

The SaaS Kit adapter writes into the app's own public content tables:

- `App\Models\BlogPost`
- `App\Models\BlogPostTranslation`
- `App\Models\Product`
- `App\Models\User`

Drafts still live in Post2Site staging tables until publish. On publish, the adapter upserts the public `BlogPost`, upserts the submitted locale in `BlogPostTranslation`, validates product guide scopes through the product model, and returns the canonical public URL.

## Canvas adapter

The Canvas adapter writes into the Canvas package tables through the configured models:

- `Canvas\Models\Post`
- `Canvas\Models\User`

Drafts still live in Post2Site staging tables until publish. On publish, the adapter upserts a Canvas post by `slug` and Canvas `user_id`, generates a UUID for new posts, writes title/summary/body/featured image/published date, and returns the URL generated from `post2site.integrations.canvas.public_url_pattern`.

## Publishing modes (`publishing.mode`)

- **`review`** — marks the staged post `published`, does not write a host table; the returned `link` is generated from `public_url.pattern`.
- **`configurable`** — writes a host Eloquent model via the `publishing.target` mapping; `link` comes from `PublishedPostData.link`.
- **`adapter`** — calls a host-provided `PublicationTarget::publish()` for complex field mapping, routing, or external systems.

## content_scope validation

Three layers, from generic to host-specific:

1. **Format** (built in) — must be `kind:key` (`ContentScopeRule`). This is contract-level.
2. **Kind whitelist** (optional config) — `content_scope.kinds`; empty accepts any kind, otherwise the kind must be listed. Echoed in `/capabilities`.
3. **Key resolution** (host) — delegated to `ContentScopeValidator`.

Which content types require a scope is `content.scoped_types` (default `['guide']`); those types require `content_scope` and all others prohibit it. This is reported in `capabilities.content.content_scope.required_for_types`.

## Request flow (create)

1. `StorePostRequest` validates input (including `content_scope` format/kind/validator and `scoped_types` rules).
2. `Post2SiteController::store` passes validated data to `PostRepository::createPost`.
3. The repository writes the post and its translation inside a transaction.
4. The response is built by `PostResponseFactory` (envelope with `blog_post`, `available_locales`, `missing_locales`, `next_actions`).

## Public URL resolution

`toPostData` returns a stored host `link` when present; for an empty link (review mode) on a published, non-future post it falls back to `PublicUrlResolver` (default: the `public_url.pattern`). Drafts and future-dated posts resolve to `null`.

See [Host Integration Guide](./integration.md) for setup and configuration.
