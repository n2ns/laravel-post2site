# Blog Targets

Post2Site targets Laravel 12 and 13. Supported targets are limited to packages with current Laravel support, a stable publishing surface, and a mapping that can be verified in tests.

## Current presets

| Target | Status | Why |
| --- | --- | --- |
| `laravel_saas_kit` | First-party adapter | Uses the SaaS Kit content model directly: `blog_posts`, `blog_post_translations`, and product guides. |
| `austintoddj_canvas` | Adapter preset | Uses Canvas `canvas_posts` and `canvas_users`. URL shape stays configurable because Canvas is frontend-agnostic. |
| `bjuppa_laravel_blog` | Configurable preset | Current package supports Illuminate 12 and exposes a simple Eloquent `BlogEntry` model. |
| `stephenjude_filament_blog` | Configurable preset | Current 5.x package targets modern PHP/Filament and has a simple post model. Host author/category requirements must be checked. |

## Compatibility rule

Post2Site should not depend on optional blog packages directly. Adapters and presets must:

- use string class names and fail with clear validation errors when the target package is absent
- keep staging drafts in Post2Site tables
- write host tables only on `POST /posts/{id}/publish`
- avoid guessing required author/category/tag records
- return the final public URL used for sitemap/IndexNow

## SaaS Kit publishing contract

The `laravel_saas_kit` preset is the canonical first-party adapter:

```env
POST2SITE_PRESET=laravel_saas_kit
POST2SITE_PUBLISHING_MODE=adapter
POST2SITE_SAAS_KIT_AUTHOR_ID=1
```

Supported output:

- `technical`, `announcement`, and `changelog` posts with no `content_scope` publish to `/blog/{slug}`.
- `guide` posts require `content_scope=product:{code}` and publish to `/{code}/guides/{slug}`.
- The product code must exist as an active SaaS Kit product.

If SaaS Kit changes its content tables, update the adapter and its tests before changing generated site templates.

## Canvas publishing contract

The `austintoddj_canvas` preset publishes into Canvas's own post table:

```env
POST2SITE_PRESET=austintoddj_canvas
POST2SITE_PUBLISHING_MODE=adapter
POST2SITE_CANVAS_USER_ID=uuid-of-canvas-user
POST2SITE_CANVAS_PUBLIC_URL_PATTERN=/blog/{slug}
```

If `POST2SITE_CANVAS_USER_ID` is omitted, the adapter uses `POST2SITE_CANVAS_USER_EMAIL`, then the first `Canvas\Models\User`. Publishing fails clearly if no Canvas user exists.

Supported output:

- posts are upserted by `slug` and Canvas `user_id`
- `title`, `summary`, `body`, `featured_image`, `published_at`, and `user_id` are written to `canvas_posts`
- the returned link is built from `POST2SITE_CANVAS_PUBLIC_URL_PATTERN`

The first adapter version does not add Post2Site API fields for Canvas tags or topics. Add those only when the publishing contract explicitly includes taxonomy input.

## Composer metadata

Optional blog targets belong in `composer.json` `suggest`, not `require`. This keeps Post2Site installable in any Laravel app while still making supported integrations visible on Packagist.

Current `suggest` entries should match supported presets only:

- `n2ns/laravel-saas-kit`
- `austintoddj/canvas`
- `bjuppa/laravel-blog`
- `stephenjude/filament-blog`

Do not use Composer `provide`, `replace`, or `conflict` to advertise integrations:

- `provide` is for virtual interfaces implemented by this package.
- `replace` is for forks or packages whose code this package actually ships.
- `conflict` is for verified incompatible package/version combinations.
