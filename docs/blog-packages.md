# Blog Targets

Post2Site core allows Laravel 12 and 13 through Composer constraints. The
current locked test baseline validates Laravel 12; Laravel 13 host integrations
should be verified in the consuming project until a Laravel 13 CI matrix is
added. Supported targets are limited to packages with current Laravel support, a
stable publishing surface, and a mapping that can be verified in tests.

## Current presets

| Target | Status | Target Laravel support | Why |
| --- | --- | --- | --- |
| `laravel_saas_kit` | First-party adapter | Laravel 12 target app | Uses the SaaS Kit content model directly: `blog_posts`, `blog_post_translations`, and product guides. |
| `austintoddj_canvas` | Adapter preset | Depends on installed Canvas version | Uses Canvas `canvas_posts` and `canvas_users`. URL shape stays configurable because Canvas is frontend-agnostic. |
| `bjuppa_laravel_blog` | Configurable preset | Depends on installed package version | Current package exposes a simple Eloquent `BlogEntry` model. |
| `stephenjude_filament_blog` | Configurable preset | Depends on installed Filament Blog version | Current 5.x package targets modern PHP/Filament and has a simple post model. Host author/category requirements must be checked. |

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

Optional blog targets belong in `composer.json` `suggest`, not `require`. This
keeps Post2Site installable in any Laravel app while still making supported
integrations visible in Composer metadata. Targets that are not yet indexed on
Packagist, such as the first-party SaaS Kit package during its initial GitHub
distribution phase, may appear as plain text until published.

Current `suggest` entries should match supported presets only:

- `n2ns/laravel-saas-kit`
- `austintoddj/canvas`
- `bjuppa/laravel-blog`
- `stephenjude/filament-blog`

Do not use Composer `provide`, `replace`, or `conflict` to advertise integrations:

- `provide` is for virtual interfaces implemented by this package.
- `replace` is for forks or packages whose code this package actually ships.
- `conflict` is for verified incompatible package/version combinations.
