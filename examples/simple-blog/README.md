# Simple text blog

A complete host adapter example for [Laravel Post2Site](../../README.md). Follow [Your first publish](../../docs/first-publish.md) to install it in a fresh local Laravel application.

| File | Role |
| --- | --- |
| `app/Post2Site/BlogPostAdapter.php` | All 11 adapter methods; validation, inventory, slug duplicates, signed previews, and persistent publishing. |
| `app/Models/BlogPost.php` | Host-owned public article model. |
| `database/migrations/*` | `blog_posts` table, separate from package draft tables. |
| `routes/post2site-blog.php` | Public `/posts/{slug}` page and signed draft-preview route. |
| `resources/views/post2site-blog.blade.php` | Escaped, plain-text rendering for both routes. |

The example supports creating articles with `title`, `slug`, and `body`. It does not support updating published articles, uploads, Markdown/HTML rendering, author management, or indexing. It is not automatically installed or enabled by the package. Configure `drafts.modes` to `['create']` and `workflow.supports_assets` to `false` as described in the guide.

Only confirmed publication inserts a `blog_posts` record. Saving or previewing a `post2site_drafts` record does not make a public article. Inventory reports public `blog_posts` records, and duplicate detection checks their slugs. A signed preview link grants access to its current staged content for 30 minutes; keep it private.
