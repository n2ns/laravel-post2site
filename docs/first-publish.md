# Your first publish

This walkthrough creates a fresh local Laravel app and publishes a real page at `/posts/hello-post2site`. You will first save a package draft, open a signed preview, and confirm publication. The example adapter then inserts a host-owned `BlogPost` record that the public route reads.

You need Composer, PHP with SQLite support, curl, and jq. Use PHP 8.2+ for Laravel 12 or PHP 8.3+ for Laravel 13. The shell commands below use Bash. The optional MCP step also needs Node.js and an MCP client.

## 1. Create an isolated Laravel application

Start with a checkout of `n2ns/laravel-post2site` that contains this guide and `examples/simple-blog`. You can clone it from `https://github.com/n2ns/laravel-post2site.git`; when evaluating an unmerged change, use that change's checkout. Set its absolute path:

```bash
export POST2SITE_CHECKOUT=/absolute/path/to/laravel-post2site
test -f "$POST2SITE_CHECKOUT/examples/simple-blog/app/Post2Site/BlogPostAdapter.php"
export POST2SITE_DEMO="$(mktemp -d)"
composer create-project 'laravel/laravel:^12.0' "$POST2SITE_DEMO/site"
cd "$POST2SITE_DEMO/site"
```

To use Laravel 13 instead, change only the create-project constraint to `laravel/laravel:^13.0` and use PHP 8.3+. Work in this fresh app; the example copies host files and is not an installer for an existing production site.

Install the package from the checkout so the example and package contract are the same revision:

```bash
composer config repositories.post2site path "$POST2SITE_CHECKOUT"
composer require 'n2ns/laravel-post2site:@dev'
php artisan vendor:publish --tag=post2site-config
```

The path repository is intentional: new example files may not be in a published Packagist release yet. For a deployed application, use a released version and implement an adapter against that version's contract.

## 2. Add the example's host model and routes

```bash
mkdir -p app/Post2Site resources/views
cp "$POST2SITE_CHECKOUT/examples/simple-blog/app/Models/BlogPost.php" app/Models/
cp "$POST2SITE_CHECKOUT/examples/simple-blog/app/Post2Site/BlogPostAdapter.php" app/Post2Site/
cp "$POST2SITE_CHECKOUT/examples/simple-blog/database/migrations/2026_01_01_000000_create_blog_posts_table.php" database/migrations/
cp "$POST2SITE_CHECKOUT/examples/simple-blog/resources/views/post2site-blog.blade.php" resources/views/
cp "$POST2SITE_CHECKOUT/examples/simple-blog/routes/post2site-blog.php" routes/
```

Append this PHP statement inside `routes/web.php`:

```php
require __DIR__.'/post2site-blog.php';
```

In the published `config/post2site.php`, change these existing entries, keeping the other configuration:

```php
// In 'bindings':
'adapter' => App\Post2Site\BlogPostAdapter::class,

// In 'workflow':
'supports_assets' => false,

// In 'drafts':
'modes' => ['create'],

// In 'indexing':
'enabled' => false,
```

The adapter accepts only `title`, `slug`, and `body`. Titles and slugs have a maximum of 255 characters; the body has a maximum of 10,000. The slug uses lowercase letters/numbers separated by hyphens. The body is plain text, not HTML or Markdown. Uploads and updates to already published articles are not supported; those need your own host implementation.

In the fresh application's `.env`, set:

```dotenv
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=sqlite
```

Laravel's fresh skeleton uses `database/database.sqlite` by default. If `DB_DATABASE` is set, make it an absolute path to that file. Keep the generated `APP_KEY`; signed preview links depend on it.

```bash
touch database/database.sqlite
php artisan config:clear
php artisan migrate
```

Package migrations create `post2site_*` workflow tables; the example migration creates `blog_posts` for public content. No queue worker, image service, or indexing provider is needed for this example.

In a second terminal, change to the printed application directory and start the local server:

```bash
# Run pwd in the first terminal to find the full application path.
cd /absolute/path/to/the/temporary/site
php artisan serve --host=127.0.0.1 --port=8000
```

Keep that terminal running. Continue the remaining commands in the first terminal, in the Laravel app directory.

## 3. Create a key and discover the content rules

```bash
export CONTENT_API_BASE_URL=http://127.0.0.1:8000/api/v1/mcp
export CONTENT_API_KEY="$(php artisan post2site:key 'Local first publish' --plain)"

curl --fail-with-body -sS -H "X-API-KEY: $CONTENT_API_KEY" -H 'Accept: application/json' \
  "$CONTENT_API_BASE_URL/capabilities" | jq
curl --fail-with-body -sS -H "X-API-KEY: $CONTENT_API_KEY" -H 'Accept: application/json' \
  "$CONTENT_API_BASE_URL/editorial-policy" | jq
```

Expect `host_profile: "simple-text-blog"`, `workflow.supports_assets: false`, and required fields for title, slug, and body. A missing adapter configuration error means step 2 is incomplete. A `401` means the key is missing or invalid. Use the generated key locally; do not put it in article content or commit it.

## 4. Save a draft without publishing

```bash
cat > article.json <<'JSON'
{
  "mode": "create",
  "target_identifier": "hello-post2site",
  "content_payload": {
    "title": "Hello Post2Site",
    "slug": "hello-post2site",
    "body": "My first published article.\n\nThis page was reviewed before publishing."
  }
}
JSON

curl --fail-with-body -sS -X POST \
  -H "X-API-KEY: $CONTENT_API_KEY" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  --data-binary @article.json "$CONTENT_API_BASE_URL/drafts" > draft.json
jq '{draft_id, status, validation_state}' draft.json
export POST2SITE_DRAFT_ID="$(jq -er '.draft_id' draft.json)"
```

Expect `status: "draft"` and `validation_state.publishable: true`. The database contains a `post2site_drafts` record; there is still no `blog_posts` record. This URL should return **404**:

```bash
curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8000/posts/hello-post2site
```

A duplicate slug blocks another publication. To repeat the walkthrough, use a new slug in both `target_identifier` and `content_payload.slug`.

## 5. Validate and open the real preview

```bash
curl --fail-with-body -sS -X POST \
  -H "X-API-KEY: $CONTENT_API_KEY" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"mode":"publish"}' "$CONTENT_API_BASE_URL/drafts/$POST2SITE_DRAFT_ID/validate" | jq

curl --fail-with-body -sS -H "X-API-KEY: $CONTENT_API_KEY" -H 'Accept: application/json' \
  "$CONTENT_API_BASE_URL/drafts/$POST2SITE_DRAFT_ID/preview" > preview.json
jq -r '.preview_url' preview.json
```

Open the returned URL in your browser. It renders the staged title and body with a draft label. HTML is escaped, and line breaks are preserved. This is a real host preview, not a placeholder URL. It does not publish anything.

The signed link expires after 30 minutes and grants access without an API-key header, so treat it as a private review link. A bare `/post2site-preview/{draft_id}` URL returns `403`. Request a new link if it expires, and keep the same host/port shown in the signed URL. If you change the draft, request and review a new preview before confirming.

## 6. Confirm publication and open the public page

First demonstrate that an unconfirmed request is rejected. This command intentionally returns **422**:

```bash
curl -sS -o unconfirmed.json -w '%{http_code}\n' -X POST \
  -H "X-API-KEY: $CONTENT_API_KEY" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"publish_confirmed":false}' "$CONTENT_API_BASE_URL/drafts/$POST2SITE_DRAFT_ID/publish"
```

The public URL still returns `404`. After reviewing the preview and deciding to publish, send explicit confirmation:

```bash
curl --fail-with-body -sS -X POST \
  -H "X-API-KEY: $CONTENT_API_KEY" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"publish_confirmed":true}' "$CONTENT_API_BASE_URL/drafts/$POST2SITE_DRAFT_ID/publish" > published.json
jq '{resource_id, status, canonical_url}' published.json
curl --fail-with-body -sS "$(jq -er '.canonical_url' published.json)"
```

Expect `status: "published"` and `canonical_url: "http://127.0.0.1:8000/posts/hello-post2site"`. Open that URL without an API key. The adapter has now inserted a real `blog_posts` row, and the package has separately marked the draft as published. Both survive a server restart because they are in SQLite, not in-memory fixture data.

Check that the public article appears in inventory:

```bash
curl --fail-with-body -sS -H "X-API-KEY: $CONTENT_API_KEY" -H 'Accept: application/json' \
  "$CONTENT_API_BASE_URL/inventory/resources/hello-post2site" | jq
```

## 7. Connect the MCP bridge to this app

The HTTP walkthrough proves that your Laravel host can publish. To use an AI client, follow the [`n2n-post2site` setup](https://github.com/n2ns/n2n-post2site#quick-start-try-a-local-draft), then point its server entry at this backend instead of the mock:

```json
{
  "mcpServers": {
    "n2n-post2site": {
      "command": "node",
      "args": ["/absolute/path/to/n2n-post2site/dist/index.js"],
      "env": {
        "CONTENT_API_BASE_URL": "http://127.0.0.1:8000/api/v1/mcp",
        "CONTENT_API_KEY": "your-generated-local-key"
      }
    }
  }
}
```

The MCP process must be able to reach this host and port; `127.0.0.1` refers to the environment where that process runs. Transfer the command, arguments, and environment to your client's own configuration format if it does not use `mcpServers`.

Ask the assistant to read `post2site://capabilities`, `post2site://site-context`, and `post2site://editorial-policy`, prepare a **new slug**, validate the working draft, and wait before saving it. Once you approve saving, use `n2n_create_draft`, `n2n_validate_draft`, and `n2n_preview_draft`. Review the returned real page, then explicitly approve `n2n_publish_draft`. Skip `n2n_upload_asset`: this adapter advertises text-only content.

Use this guide's `title`/`slug`/`body` payload, not the mock backend's payload in the [bridge's first-publish guide](https://github.com/n2ns/n2n-post2site/blob/main/docs/first-publish.md). For `n2n_validate_working_draft`, pass `{ "mode": "publish", "article": <the article object from step 4> }`, replacing the placeholder with the JSON object and choosing a new slug in both `target_identifier` and `content_payload.slug`. The outer `mode` selects validation; `article.mode` stays `"create"`.

## Where to go from here

For your existing site, replace the example model and routes with your real content model, validation, preview access rules, and publication logic. Implement only the capabilities you advertise. The package owns staging and confirmation; your adapter owns which records become public and the URLs that serve them.

Stop the local server with Ctrl+C when finished. Keep or remove only the temporary application you created for this walkthrough; nothing here deploys to a live site.
