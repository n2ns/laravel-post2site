# Laravel Post2Site 宿主集成演练

本演练展示已有 Laravel 项目如何以最少代码接入 `n2ns/laravel-post2site`。默认推荐先用 `review` 模式零代码接入；需要自动发布时，优先使用 `configurable` 模式做字段映射；复杂站点才实现自定义 `PublicationTarget`。

## 1. 安装与发布配置

```bash
composer require n2ns/laravel-post2site
php artisan vendor:publish --tag=post2site-config
```

如果使用扩展包默认数据表：

```bash
php artisan vendor:publish --tag=post2site-migrations
php artisan migrate
php artisan post2site:key "Production MCP"
```

如果只需要先接入 MCP 写稿和人工审核，宿主不需要写任何 PHP 代码：

```bash
php artisan vendor:publish --tag=post2site-auth-migrations
php artisan vendor:publish --tag=post2site-content-migrations
php artisan migrate
php artisan post2site:key "Production MCP"
```

```env
POST2SITE_PUBLISHING_MODE=review
```

`post2site-auth-migrations` 只发布 API key 表。`post2site-content-migrations` 发布 post2site staging 表，不会修改宿主原文章表。`post2site-indexing-migrations` 只有启用 IndexNow 或需要记录收录状态时才需要。database driver 会把 key 的 SHA-256 `key_hash` 存入 `post2site_api_keys`，命令只显示明文 key 一次。

所有 Post2Site 路由默认带限流，可通过 `POST2SITE_RATE_LIMIT`（`max,minutes` 形式，默认 `60,1`）调整：

```env
POST2SITE_RATE_LIMIT=60,1
```

内容列表的 `?q=` 搜索在 MySQL/MariaDB 上使用 `post2site_post_translations` 的 `FULLTEXT(title, content)` 索引（boolean 模式），迁移会在这些驱动上自动创建该索引；SQLite 等不支持 FULLTEXT 的驱动会自动降级为 `LIKE`。注意 MySQL FULLTEXT 有最小词长限制（InnoDB `innodb_ft_min_token_size` 默认 3），过短的关键词可能搜不到。

## 2. 配置式自动发布

如果宿主文章表是常规 Eloquent 模型，可以只改配置，不写 PHP 类。示例假设宿主项目有 `App\Models\Article`，并使用 Spatie Translatable。

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
            // 单一 pattern，占位符：{slug} {locale} {content_scope} {key}。
            'pattern' => '/{locale}/{slug}',
        ],
    ],
],
```

这种模式下：

- MCP 草稿仍然只写 post2site staging 表。
- `PATCH /posts/{id}` 不影响宿主 `articles` 表。
- 只有 `POST /posts/{id}/publish` 才按映射写入 `Article`。
- 发布后返回的 `link` 来自配置里的 URL pattern。

> URL 形态由包零假设：默认 `/{slug}`。`configurable` 模式只支持单一 pattern；若不同分类要不同 URL（例如 `product:` guide 用 `/{locale}/{key}/guides/{slug}`、其它用 `/{locale}/{slug}`），请改用 `adapter` 模式实现自定义 `PublicationTarget`，或绑定自定义 `PublicUrlResolver`。

## 2.1 内容分类（content_scope）与 URL

`content_scope` 是可选的 `kind:key` 分类标签（如 `product:evisa-helper`）。包对它的取值零假设，宿主按需配置：

- **kind 白名单（可选）**：默认接受任意 `kind:key`。要限制就设 env：
  ```env
  POST2SITE_CONTENT_SCOPE_KINDS=product,project
  ```
  设置后会在 `/capabilities` 回显，供 MCP 客户端/AI 发现。
- **key 校验（可选）**：要求 `key` 指向真实实体（如产品必须存在），绑定 `ContentScopeValidator`：
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
  默认不绑定时一律放行。
- **公开 URL**：默认 `POST2SITE_PUBLIC_URL_PATTERN`（默认 `/{slug}`，占位符 `{slug} {locale} {content_scope} {key}`）。要按分类区分 URL，绑定自定义 `PublicUrlResolver` 即可，包内不含任何 blog/product 默认语义。

## 3. 自定义发布适配器

只有当字段映射不够用时才需要写代码，例如要写关联表、复杂 taxonomy、作者模型、旧 slug、外部 CMS。

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

然后配置：

```php
'publishing' => ['mode' => 'adapter'],
'bindings' => [
    'publication_target' => App\Post2Site\ArticlePublicationTarget::class,
],
```

## 4. 配置 SEO/GEO 与收录推送

宿主站负责前台页面渲染，因此 SEO/GEO 的最终输出也应在宿主站完成。`PublicationTarget` 返回的 `PublishedPostData.link` 会写回 staging post，成为收录流水线的唯一 URL 来源；draft 或未来发布时间文章返回 `null`，未提供公开 URL 的 staging post 不会被推送。

建议宿主站在文章公开页面复用扩展包生成的 `PublicPostMetadata`，输出：

- canonical URL
- meta title / description
- Open Graph title / description / image
- JSON-LD `Article` 或 `BlogPosting`
- sitemap 中的 published URL

扩展包发布成功后会派发 `Post2SitePostPublished` event。宿主站应监听它来更新 sitemap 或清理页面缓存：

```php
use Illuminate\Support\Facades\Event;
use N2ns\LaravelPost2Site\Events\Post2SitePostPublished;

Event::listen(Post2SitePostPublished::class, function (Post2SitePostPublished $event): void {
    // Refresh sitemap cache or call the host application's sitemap generator.
});
```

IndexNow 是后端可选功能，不需要 MCP 增加任何工具。生产环境推荐把 key 文件作为静态文件放到网站根目录：

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

把输出值写入：

```text
public/{POST2SITE_INDEXNOW_KEY}.txt
```

文件内容只包含 key 本身。然后配置：

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

如果部署环境不能写 `public/`，才启用动态 key route：

```env
POST2SITE_INDEXNOW_AUTO_PUBLISH_KEY_FILE=true
```

在 `configurable` 或 `adapter` 模式下，`POST /posts/{id}/publish` 成功生成 live `link` 后会排队 `SubmitPublishedPostForIndexing`。`review` 模式虽然也会经 `public_url.pattern` 暴露一个 `link`，但不写宿主、按设计不推送 IndexNow。启用 IndexNow 时需要确保队列 worker 正常运行：

```bash
php artisan queue:work --queue="${POST2SITE_INDEXING_QUEUE:-default}"
```

Google 对普通文章没有通用 URL 自动提交入口。本方案默认 `google_auto_submit=false`，站点应通过 sitemap 和 Google Search Console 处理 Google 收录。

## 5. 草稿工具如何工作

宿主项目不需要额外实现 `list_drafts` 或 `update_draft` 路由。

- `n2n_list_drafts` 会调用 `/posts?status=draft`，由 post2site staging repository 返回草稿。
- `n2n_update_draft` 会先调用 `/posts/{id_or_slug}`，确认返回 `status=draft` 后再调用 `PATCH /posts/{id_or_slug}`。这只修改 staging 表，不影响宿主 `Article` 表。

## 6. 接入后最低验证

```bash
curl -H "X-API-KEY: $POST2SITE_API_KEY" https://example.com/api/v1/mcp/capabilities
curl -H "X-API-KEY: $POST2SITE_API_KEY" "https://example.com/api/v1/mcp/posts?status=draft"
curl https://example.com/${POST2SITE_INDEXNOW_KEY}.txt
```

需要确认：

- 新建文章默认 `status=draft`。
- `GET /posts?status=draft` 不返回 published。
- 每个列表项都有 `link` 字段，draft 的 `link` 为 `null`。
- `PATCH /posts/{id}` 不能接受 `status` 或 `published_at`。
- 重复 `slug` 的 `POST`/`PATCH` 返回 422（验证错误），不是 500。
- `POST /posts/{id}/publish` 是唯一发布入口。
- configurable 模式发布后，宿主 `Article` 表按配置映射写入，返回的 `link` 是 live 网站绝对 public URL。
- adapter 模式发布后，宿主发布适配器被调用，返回的 `link` 是 live 网站绝对 public URL。
- review 模式发布后，staging post 进入 `published`，宿主 `Article` 表不变，`link` 由 `public_url.pattern` 经 `PublicUrlResolver` 生成。
- IndexNow key 文件公开可访问，内容等于 `POST2SITE_INDEXNOW_KEY`。
- 有 live `link` 且启用 IndexNow 时 indexing job 被排队；IndexNow 失败不影响文章已发布状态。
