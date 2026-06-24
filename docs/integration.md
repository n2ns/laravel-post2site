# Host Integration Guide

This guide shows how an existing Laravel application integrates `n2ns/laravel-post2site` with the least code. Start with `review` mode (zero host code). When you want automatic publishing, prefer `configurable` mode (field mapping). Only complex sites need a custom `PublicationTarget`.

## 1. Install and publishing setup

```bash
composer require n2ns/laravel-post2site
php artisan vendor:publish --tag=post2site-config
```

Using the package's own tables:

```bash
php artisan migrate
php artisan post2site:key "Production MCP"
```

If you only need MCP drafting plus manual review, no host PHP is required:

```bash
php artisan migrate
php artisan post2site:key "Production MCP"
```

```env
POST2SITE_PUBLISHING_MODE=review
```

Package migrations are loaded automatically. If you need to customize them before running `migrate`, publish tags are still available: `post2site-auth-migrations` publishes only the API key table, `post2site-content-migrations` publishes the post2site staging tables and never touches your existing article tables, and `post2site-indexing-migrations` publishes the indexing submission table. The database driver stores the key's SHA-256 `key_hash` in `post2site_api_keys`; the command prints the plaintext key once.

All Post2Site routes are rate limited. Tune via `POST2SITE_RATE_LIMIT` (`max,minutes`, default `60,1`):

```env
POST2SITE_RATE_LIMIT=60,1
```

The `?q=` search uses a `FULLTEXT(title, content)` index on `post2site_post_translations` (boolean mode) on MySQL/MariaDB; the migration creates the index on those drivers, and other drivers (e.g. SQLite) fall back to `LIKE`. Note MySQL's minimum token length (InnoDB `innodb_ft_min_token_size`, default 3) — very short terms may not match.

## 2. Presets for known site/blog packages

Use a preset when your host matches a known target. Presets only fill ordinary config values; you can still publish and override `config/post2site.php`.

### SaaS Kit

For a site installed from `n2ns/laravel-saas-kit`:

```env
POST2SITE_PRESET=laravel_saas_kit
POST2SITE_SAAS_KIT_AUTHOR_ID=1
POST2SITE_PUBLISHING_MODE=adapter
```

The preset:

- writes published posts into `App\Models\BlogPost`
- writes locale content into `App\Models\BlogPostTranslation`
- validates `product:{code}` against active `App\Models\Product` records
- publishes unscoped posts at `/blog/{slug}`
- publishes product guides at `/{productCode}/guides/{slug}`

If `POST2SITE_SAAS_KIT_AUTHOR_ID` is omitted, the adapter uses `POST2SITE_SAAS_KIT_AUTHOR_EMAIL`, then the first `App\Models\User`. Publishing fails clearly if no author exists.

SaaS Kit's current generated application targets Laravel 12. Post2Site core
allows Laravel 12 and 13, but this preset should be treated as Laravel 12 until
the generated app itself adds Laravel 13 support.

### bjuppa/laravel-blog

For `bjuppa/laravel-blog`:

```env
POST2SITE_PRESET=bjuppa_laravel_blog
POST2SITE_PUBLISHING_MODE=configurable
```

This maps `slug`, `title`, `summary`, `content`, `image`, and `publish_after` into `Bjuppa\LaravelBlog\Eloquent\BlogEntry`. It is a good Laravel 12 target because the package currently supports Illuminate 12.

### austintoddj/canvas

For `austintoddj/canvas`:

```env
POST2SITE_PRESET=austintoddj_canvas
POST2SITE_PUBLISHING_MODE=adapter
POST2SITE_CANVAS_USER_ID=uuid-of-canvas-user
POST2SITE_CANVAS_PUBLIC_URL_PATTERN=/blog/{slug}
```

The preset:

- writes published posts into `Canvas\Models\Post`
- requires a Canvas author from `POST2SITE_CANVAS_USER_ID`, `POST2SITE_CANVAS_USER_EMAIL`, or the first `Canvas\Models\User`
- publishes links through `POST2SITE_CANVAS_PUBLIC_URL_PATTERN`

Canvas does not define one required frontend URL for posts. Set `POST2SITE_CANVAS_PUBLIC_URL_PATTERN` to the route your site actually renders.

### stephenjude/filament-blog

For `stephenjude/filament-blog` 5.x:

```env
POST2SITE_PRESET=stephenjude_filament_blog
POST2SITE_PUBLISHING_MODE=configurable
```

This maps the common post fields into `Stephenjude\FilamentBlog\Models\Post`. Check your installed package schema before enabling automatic publishing: some installations may require a default author/category, which should be handled by a small host adapter instead of forcing those assumptions into Post2Site.

## 3. Configurable publishing

If your article table is a regular Eloquent model, you can publish with configuration only — no PHP class. This example assumes `App\Models\Article` using Spatie Translatable.

```php
'publishing' => [
    'mode' => 'configurable',
    'target' => [
        'model' => App\Models\Article::class,
        'lookup' => ['slug' => 'slug'],
        'fields' => [
            'slug' => 'slug',
            'type' => 'type',
            'content_scope' => 'content_scope',
            'thumbnail' => 'thumbnail',
            'status' => ['column' => 'status', 'value' => 'published'],
            'published_at' => ['column' => 'published_at', 'value' => 'now'],
        ],
        'translations' => [
            'driver' => 'spatie_translatable',
            'fields' => [
                'title' => 'title',
                'excerpt' => 'excerpt',
                'content' => 'content',
            ],
        ],
        'url' => [
            // Single pattern. Placeholders: {slug} {locale} {content_scope} {key}.
            'pattern' => '/{locale}/{slug}',
        ],
    ],
],
```

In this mode:

- MCP drafts are still written only to the post2site staging tables.
- `PATCH /posts/{id}` does not touch your `articles` table.
- Only `POST /posts/{id}/publish` writes to `Article` per the mapping.
- The returned `link` comes from the configured URL pattern.

> The package makes no assumptions about URL shape (default `/{slug}`). `configurable` mode supports a single pattern; if different categories need different URLs (e.g. `product:` guides at `/{locale}/{key}/guides/{slug}` and others at `/{locale}/{slug}`), use `adapter` mode with a custom `PublicationTarget`, or bind a custom `PublicUrlResolver`.

## 4. Content scope and URLs

`content_scope` is an optional `kind:key` classification tag (e.g. `product:example-app`). The package makes no assumptions about its values; configure as needed:

- **Kind whitelist (optional)** — any `kind:key` is accepted by default. To restrict, set:
  ```env
  POST2SITE_CONTENT_SCOPE_KINDS=product,project
  ```
  Configured kinds are echoed in `/capabilities` for MCP clients/AI to discover.
- **Key validation (optional)** — to require that `key` points to a real entity (e.g. an existing product), bind `ContentScopeValidator`:
  ```php
  use N2ns\LaravelPost2Site\Contracts\ContentScopeValidator;

  $this->app->bind(ContentScopeValidator::class, function (): ContentScopeValidator {
      return new class implements ContentScopeValidator {
          public function validate(string $kind, string $key): ?string
          {
              if ($kind === 'product' && ! \App\Models\Product::query()->where('code', $key)->exists()) {
                  return 'The selected content_scope product does not exist.';
              }
              return null;
          }
      };
  });
  ```
  The default binding accepts everything.
- **Which types require a scope** — set `content.scoped_types` (default `['guide']`). Those types require a `content_scope`; all others prohibit it.
- **Public URL** — `POST2SITE_PUBLIC_URL_PATTERN` (default `/{slug}`; placeholders `{slug} {locale} {content_scope} {key}`). For per-category URLs, bind a custom `PublicUrlResolver`; the package ships no blog/product defaults.

## 5. Custom publication adapter

Write code only when field mapping is not enough — for example relation tables, complex taxonomy, author models, legacy slugs, or an external CMS.

```php
namespace App\Post2Site;

use App\Models\Article;
use N2ns\LaravelPost2Site\Contracts\PublicationTarget;
use N2ns\LaravelPost2Site\Data\PostData;
use N2ns\LaravelPost2Site\Data\PublishedPostData;

class ArticlePublicationTarget implements PublicationTarget
{
    public function publish(PostData $post): PublishedPostData
    {
        $article = Article::firstOrNew(['slug' => $post->slug]);
        // Map fields, relations, author, taxonomy, translations, then save.

        return new PublishedPostData(
            targetId: $article->id,
            targetType: Article::class,
            link: url("/en/blog/{$article->slug}"),
            publishedAt: $article->published_at,
        );
    }
}
```

Then configure:

```php
'publishing' => ['mode' => 'adapter'],
'bindings' => [
    'publication_target' => App\Post2Site\ArticlePublicationTarget::class,
],
```

## 6. SEO/GEO and indexing

The host renders public pages, so final SEO/GEO output also belongs on the host. The `PublishedPostData.link` returned by `PublicationTarget` is written back to the staging post and is the single URL source for the indexing pipeline; drafts and future-dated posts return `null`, and a staging post without a public URL is never submitted.

Reuse the package's `PublicPostMetadata` on public article pages to output:

- canonical URL
- meta title / description
- Open Graph title / description / image
- JSON-LD `Article` or `BlogPosting`
- the published URL in your sitemap

On successful publish the package dispatches a `Post2SitePostPublished` event. Listen for it to refresh sitemaps or clear page caches:

```php
use Illuminate\Support\Facades\Event;
use N2ns\LaravelPost2Site\Events\Post2SitePostPublished;

Event::listen(Post2SitePostPublished::class, function (Post2SitePostPublished $event): void {
    // Refresh sitemap cache or call the host application's sitemap generator.
});
```

IndexNow is an optional backend feature and needs no extra MCP tool. In production, serve the key file as a static file at the site root:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Write the value to:

```text
public/{POST2SITE_INDEXNOW_KEY}.txt
```

The file contains only the key. Then configure:

```env
POST2SITE_INDEXING_ENABLED=true
POST2SITE_INDEXING_DEDUPE_MINUTES=10
POST2SITE_SITEMAP_ENABLED=true
POST2SITE_INDEXNOW_ENABLED=true
POST2SITE_INDEXNOW_ENDPOINT=https://api.indexnow.org/indexnow
POST2SITE_INDEXNOW_KEY=your-generated-key
POST2SITE_INDEXNOW_KEY_LOCATION=https://example.com/your-generated-key.txt
POST2SITE_INDEXNOW_AUTO_PUBLISH_KEY_FILE=false
```

Only enable the dynamic key route if the deployment cannot write to `public/`:

```env
POST2SITE_INDEXNOW_AUTO_PUBLISH_KEY_FILE=true
```

In `configurable` or `adapter` mode, a successful `POST /posts/{id}/publish` that produces a live `link` queues `SubmitPublishedPostForIndexing`. `review` mode also exposes a `link` via `public_url.pattern`, but it does not write to the host and, by design, does not submit to IndexNow. When IndexNow is enabled, run a queue worker:

```bash
php artisan queue:work --queue="${POST2SITE_INDEXING_QUEUE:-default}"
```

Google has no generic URL submission endpoint for ordinary articles. This package defaults `google_auto_submit=false`; rely on your sitemap and Google Search Console.

## 7. Draft tools

The host does not implement `list_drafts` or `update_draft` routes:

- `n2n_list_drafts` calls `/posts?status=draft`, served by the staging repository.
- `n2n_update_draft` calls `/posts/{id_or_slug}` first, confirms `status=draft`, then calls `PATCH /posts/{id_or_slug}`. This only changes the staging tables.

## 8. Post-integration smoke test

```bash
curl -H "X-API-KEY: $POST2SITE_API_KEY" https://example.com/api/v1/mcp/capabilities
curl -H "X-API-KEY: $POST2SITE_API_KEY" "https://example.com/api/v1/mcp/posts?status=draft"
curl https://example.com/${POST2SITE_INDEXNOW_KEY}.txt
```

Verify:

- New posts default to `status=draft`.
- `GET /posts?status=draft` returns no published posts.
- Every list item has a `link` field; drafts have `link: null`.
- `PATCH /posts/{id}` rejects `status` and `published_at`.
- A duplicate `slug` on `POST`/`PATCH` returns 422 (validation error), not 500.
- `POST /posts/{id}/publish` is the only publish entry point.
- In `configurable` mode, publishing writes to the host model per the mapping and returns an absolute live public URL.
- In `adapter` mode, publishing calls the host adapter and returns an absolute live public URL.
- In `review` mode, publishing marks the staging post `published`, leaves the host table unchanged, and returns a `link` generated from `public_url.pattern`.
- The IndexNow key file is publicly readable and equals `POST2SITE_INDEXNOW_KEY`.
- With a live `link` and IndexNow enabled, the indexing job is queued; an IndexNow failure does not affect the published state.
