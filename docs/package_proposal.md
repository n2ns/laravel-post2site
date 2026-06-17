# Laravel Post2Site 扩展包方案

本文件定义 `n2ns/laravel-post2site` 的可执行实现方案。该包只实现 N2N Post2Site Content Publishing API Contract 的 HTTP 后端侧，让 Laravel 站点可以安全接入 `n2n-post2site` MCP 客户端。

## 目标边界

扩展包负责：

- 注册受保护的内容发布 API 路由。
- 验证 create/update/publish 请求。
- 统一输出符合 MCP 契约的 JSON。
- 使用扩展包自有 staging 表保存 MCP 生成的草稿、翻译和发布状态。
- 通过配置映射或发布适配器把已审核内容同步到宿主站真实文章表。
- 可选地在发布成功后触发后端 SEO/GEO 收录流水线。

扩展包不负责：

- CMS 管理后台。
- 图片上传和资源管理。
- 自动翻译。
- 删除已发布内容。
- 代替宿主站生成完整前台页面。
- 在写作草稿阶段直接修改宿主站原有文章表。
- 向 Google 执行不存在的通用“自动收录提交”。
- 数据库、Shell、部署、用户、支付或套餐管理。

## 项目结构

```text
laravel-post2site/
├── composer.json
├── README.md
├── config/
│   └── post2site.php
├── database/
│   └── migrations/
│       ├── create_post2site_api_keys_table.php
│       ├── create_post2site_indexing_submissions_table.php
│       └── create_post2site_posts_tables.php
├── routes/
│   └── api.php
├── src/
│   ├── LaravelPost2SiteServiceProvider.php
│   ├── Console/
│   │   └── Commands/
│   │       └── CreateApiKeyCommand.php
│   ├── Contracts/
│   │   ├── ContentScopeValidator.php
│   │   ├── IndexingNotifier.php
│   │   ├── PublicationTarget.php
│   │   ├── PostRepository.php
│   │   ├── PublicUrlResolver.php
│   │   └── ScopeContextProvider.php
│   ├── Data/
│   │   ├── IndexingPlan.php
│   │   ├── IndexingResult.php
│   │   ├── PublishedPostData.php
│   │   └── PostData.php
│   ├── Events/
│   │   └── Post2SitePostPublished.php
│   ├── Http/
│   │   ├── Controllers/IndexNowKeyController.php
│   │   ├── Controllers/Post2SiteController.php
│   │   ├── Middleware/AuthenticatePost2SiteKey.php
│   │   └── Requests/
│   │       ├── ListPostsRequest.php
│   │       ├── StorePostRequest.php
│   │       └── UpdatePostRequest.php
│   ├── Indexing/
│   │   ├── CompositeIndexingNotifier.php
│   │   ├── IndexNowNotifier.php
│   │   └── NullIndexingNotifier.php
│   ├── Jobs/
│   │   └── SubmitPublishedPostForIndexing.php
│   ├── Support/
│   │   ├── ConfiguredPublicUrlResolver.php
│   │   ├── ContentScopeRule.php
│   │   ├── NullContentScopeValidator.php
│   │   ├── PublicUrlPattern.php
│   │   ├── IndexNowKeyFile.php
│   │   ├── PublicPostMetadata.php
│   │   └── PostResponseFactory.php
│   ├── Models/
│   │   ├── Post2SiteApiKey.php
│   │   ├── Post2SiteIndexingSubmission.php
│   │   ├── Post2SitePost.php
│   │   └── Post2SitePostTranslation.php
│   └── Repositories/
│       ├── ConfigurablePublicationTarget.php
│       ├── EloquentPostRepository.php
│       ├── NullPublicationTarget.php
│       └── ConfigScopeContextProvider.php
└── tests/
```

## Composer 包定义

包应声明 Laravel 12/13 共同支持的 Illuminate 组件，不依赖运行时版本判断。Laravel 通过 Composer dependency resolution 选择可安装版本，通过 package discovery 自动注册 Service Provider。

```json
{
  "name": "n2ns/laravel-post2site",
  "type": "library",
  "require": {
    "php": "^8.2",
    "illuminate/support": "^12.0 || ^13.0",
    "illuminate/routing": "^12.0 || ^13.0",
    "illuminate/http": "^12.0 || ^13.0",
    "illuminate/validation": "^12.0 || ^13.0",
    "illuminate/database": "^12.0 || ^13.0",
    "illuminate/queue": "^12.0 || ^13.0",
    "illuminate/console": "^12.0 || ^13.0",
    "illuminate/bus": "^12.0 || ^13.0",
    "illuminate/hashing": "^12.0 || ^13.0"
  },
  "autoload": {
    "psr-4": {
      "N2ns\\LaravelPost2Site\\": "src/"
    }
  },
  "extra": {
    "laravel": {
      "providers": [
        "N2ns\\LaravelPost2Site\\LaravelPost2SiteServiceProvider"
      ]
    }
  }
}
```

只有未来引入 Laravel 12/13 行为差异时，才需要在代码里读取 `app()->version()` 或 `Illuminate\Foundation\Application::VERSION` 做版本分支。

## 配置文件

```php
<?php

return [
    'route_prefix' => env('POST2SITE_ROUTE_PREFIX', 'api/v1/mcp'),
    'route_middleware' => ['api'],

    // 应用到所有 Post2Site 路由的限流，"max,minutes" 形式。
    // 缓解 API key 暴力尝试与请求洪泛。
    'rate_limit' => env('POST2SITE_RATE_LIMIT', '60,1'),

    'auth' => [
        'driver' => env('POST2SITE_AUTH_DRIVER', 'database'),
        'header' => env('POST2SITE_AUTH_HEADER', 'X-API-KEY'),
        'static_key' => env('POST2SITE_API_KEY'),
        'model' => N2ns\LaravelPost2Site\Models\Post2SiteApiKey::class,
    ],

    'content' => [
        'types' => ['technical', 'announcement', 'changelog', 'guide'],
        // 需要 content_scope 的类型（其它类型禁止 content_scope）。
        'scoped_types' => ['guide'],
        'statuses' => ['draft', 'published'],
        'locales' => ['en'],
        'default_locale' => 'en',
        'per_page_max' => 100,
    ],

    // 公开 URL 形态。包对内容分类零假设，由该 pattern 决定。
    // 占位符：{slug} {locale} {content_scope} {key}；{key} 为 content_scope 冒号后那段。
    // 需要按分类区分 URL 的宿主请绑定自己的 PublicUrlResolver。
    'public_url' => [
        'pattern' => env('POST2SITE_PUBLIC_URL_PATTERN', '/{slug}'),
    ],

    'content_scope' => [
        // 可选的 kind 白名单（冒号前那段）。留空 = 接受任意合法 kind:key。逗号分隔的 env。
        'kinds' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('POST2SITE_CONTENT_SCOPE_KINDS', ''))
        ))),
        // 在 /capabilities 中回显给 AI 客户端的示例。
        'examples' => [],
    ],

    'publishing' => [
        'mode' => env('POST2SITE_PUBLISHING_MODE', 'review'),
        // review: publishes the staged row directly and keeps host model writes unchanged.
        // configurable: writes to a host Eloquent model using the mapping below.
        // adapter: writes through a custom PublicationTarget binding.

        'target' => [
            'model' => env('POST2SITE_TARGET_MODEL'),
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
                // 占位符：{slug} {locale} {content_scope} {key}；未设时回退到 public_url.pattern。
                'pattern' => env('POST2SITE_PUBLISH_URL_PATTERN', '/{slug}'),
            ],
        ],
    ],

    'bindings' => [
        'repository' => N2ns\LaravelPost2Site\Repositories\EloquentPostRepository::class,
        'publication_target' => N2ns\LaravelPost2Site\Repositories\ConfigurablePublicationTarget::class,
        'scope_context_provider' => N2ns\LaravelPost2Site\Repositories\ConfigScopeContextProvider::class,
        'indexing_notifier' => N2ns\LaravelPost2Site\Indexing\CompositeIndexingNotifier::class,
        'public_url_resolver' => N2ns\LaravelPost2Site\Support\ConfiguredPublicUrlResolver::class,
        'content_scope_validator' => N2ns\LaravelPost2Site\Support\NullContentScopeValidator::class,
    ],

    'seo' => [
        'metadata' => [
            'enabled' => true,
            'description_max_length' => 160,
            'fallback_image' => null,
        ],
        'structured_data' => [
            'enabled' => true,
            'article_type' => 'Article',
        ],
    ],

    'indexing' => [
        'enabled' => env('POST2SITE_INDEXING_ENABLED', true),
        'queue' => env('POST2SITE_INDEXING_QUEUE', 'default'),
        'dedupe_minutes' => env('POST2SITE_INDEXING_DEDUPE_MINUTES', 10),

        'sitemap' => [
            'enabled' => env('POST2SITE_SITEMAP_ENABLED', true),
        ],

        'indexnow' => [
            'enabled' => env('POST2SITE_INDEXNOW_ENABLED', false),
            'endpoint' => env('POST2SITE_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
            'key' => env('POST2SITE_INDEXNOW_KEY'),
            'key_location' => env('POST2SITE_INDEXNOW_KEY_LOCATION'),
            'auto_publish_key_file' => env('POST2SITE_INDEXNOW_AUTO_PUBLISH_KEY_FILE', false),
        ],

        'google' => [
            'auto_submit' => false,
            'recommendation' => 'Use sitemap plus Google Search Console for ordinary articles. Do not expose a generic URL submitter.',
        ],
    ],

    // 每个 content_scope 的受控上下文，按完整 kind:key 索引。
    'scopes' => [
        // 'product:evisa-helper' => [
        //     'name' => 'EVisa Helper',
        //     'canonical_url' => 'https://example.com/en/evisa-helper',
        //     'docs_url' => 'https://example.com/en/evisa-helper/guides',
        //     'summary' => 'Controlled product summary.',
        //     'key_points' => ['Controlled product fact.'],
        //     'do_not_claim' => ['Do not claim guaranteed outcomes.'],
        // ],
    ],
];
```

## 路由

`routes/api.php` 必须读取配置，不能写死 prefix 或 middleware：

```php
<?php

use Illuminate\Support\Facades\Route;
use N2ns\LaravelPost2Site\Http\Controllers\IndexNowKeyController;
use N2ns\LaravelPost2Site\Http\Controllers\Post2SiteController;
use N2ns\LaravelPost2Site\Http\Middleware\AuthenticatePost2SiteKey;

Route::prefix(config('post2site.route_prefix', 'api/v1/mcp'))
    ->middleware(array_merge(
        config('post2site.route_middleware', ['api']),
        ['throttle:'.config('post2site.rate_limit', '60,1')],
        [AuthenticatePost2SiteKey::class],
    ))
    ->group(function (): void {
        Route::get('/capabilities', [Post2SiteController::class, 'capabilities']);
        Route::get('/scopes/{contentScope}', [Post2SiteController::class, 'scopeContext']);
        Route::get('/posts', [Post2SiteController::class, 'index']);
        Route::post('/posts', [Post2SiteController::class, 'store']);
        Route::get('/posts/{idOrSlug}', [Post2SiteController::class, 'show']);
        Route::match(['put', 'patch'], '/posts/{idOrSlug}', [Post2SiteController::class, 'update']);
        Route::post('/posts/{idOrSlug}/publish', [Post2SiteController::class, 'publish']);
    });

if (config('post2site.indexing.indexnow.auto_publish_key_file', false)) {
    Route::get('/{key}.txt', IndexNowKeyController::class)
        ->middleware('throttle:'.config('post2site.rate_limit', '60,1'))
        ->where('key', '[A-Za-z0-9-]{8,128}');
}
```

`n2n_list_drafts` 和 `n2n_update_draft` 是 MCP 客户端侧工具，不需要新增 Laravel REST 路由：

- `n2n_list_drafts` 调用 `GET /posts?status=draft`。
- `n2n_update_draft` 先调用 `GET /posts/{id_or_slug}` 确认 `status === "draft"`，再调用 `PATCH /posts/{id_or_slug}`。

`IndexNowKeyController` 是公开验证文件路由，不使用 MCP API key。默认关闭；生产环境优先把 `{key}.txt` 作为静态文件放在站点根目录，只有宿主项目无法写 public 目录时才打开 `auto_publish_key_file`。

## Service Provider

```php
<?php

namespace N2ns\LaravelPost2Site;

use Illuminate\Support\ServiceProvider;
use N2ns\LaravelPost2Site\Console\Commands\CreateApiKeyCommand;
use N2ns\LaravelPost2Site\Contracts\ContentScopeValidator;
use N2ns\LaravelPost2Site\Contracts\PublicationTarget;
use N2ns\LaravelPost2Site\Contracts\PostRepository;
use N2ns\LaravelPost2Site\Contracts\ScopeContextProvider;
use N2ns\LaravelPost2Site\Contracts\IndexingNotifier;
use N2ns\LaravelPost2Site\Contracts\PublicUrlResolver;

class LaravelPost2SiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->replaceConfigRecursivelyFrom(__DIR__.'/../config/post2site.php', 'post2site');

        $this->app->bind(PostRepository::class, config('post2site.bindings.repository'));
        $this->app->bind(PublicationTarget::class, config('post2site.bindings.publication_target'));
        $this->app->bind(ScopeContextProvider::class, config('post2site.bindings.scope_context_provider'));
        $this->app->bind(IndexingNotifier::class, config('post2site.bindings.indexing_notifier'));
        $this->app->bind(PublicUrlResolver::class, config('post2site.bindings.public_url_resolver'));
        $this->app->bind(ContentScopeValidator::class, config('post2site.bindings.content_scope_validator'));
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/post2site.php' => config_path('post2site.php'),
            ], 'post2site-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations/create_post2site_api_keys_table.php' => database_path('migrations/create_post2site_api_keys_table.php'),
            ], 'post2site-auth-migrations');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations/create_post2site_posts_tables.php' => database_path('migrations/create_post2site_posts_tables.php'),
            ], 'post2site-content-migrations');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations/create_post2site_indexing_submissions_table.php' => database_path('migrations/create_post2site_indexing_submissions_table.php'),
            ], 'post2site-indexing-migrations');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'post2site-migrations');

            $this->commands([
                CreateApiKeyCommand::class,
            ]);
        }
    }
}
```

这里使用 `replaceConfigRecursivelyFrom()`，因为 `post2site.php` 有多层嵌套配置；普通 `mergeConfigFrom()` 只适合第一层合并，宿主只覆盖局部嵌套配置时容易丢默认项。

## Staging Repository 契约

`PostRepository` 只管理扩展包自有 staging 表，不直接写宿主站原有文章表。这样 MCP 写作、草稿修改、多语言补全都隔离在 `post2site_posts` 内，只有 publish 阶段才通过 `PublicationTarget` 同步到宿主站真实内容模型。

Repository 不应把任意 Eloquent 模型原样返回给控制器。它必须返回 `PostData`，这样包可以稳定生成 `available_locales`、`missing_locales` 和分页 `data[]`。

```php
<?php

namespace N2ns\LaravelPost2Site\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use N2ns\LaravelPost2Site\Data\PublishedPostData;
use N2ns\LaravelPost2Site\Data\PostData;

interface PostRepository
{
    /** @return LengthAwarePaginator<int, PostData> */
    public function listPosts(array $filters, int $perPage = 20): LengthAwarePaginator;

    public function createPost(array $data): PostData;

    public function findPostByIdOrSlug(string $idOrSlug): PostData;

    public function updatePost(string|int $id, array $data): PostData;

    public function markPublished(string|int $id, PublishedPostData $published): PostData;

    public function markPendingReview(string|int $id): PostData;
}
```

`PostData` 是 MCP JSON 输出的内部标准形状：

```php
<?php

namespace N2ns\LaravelPost2Site\Data;

use Carbon\CarbonInterface;

final readonly class PostData
{
    public function __construct(
        public string|int $id,
        public string $slug,
        public string $type,
        public string $status,
        public ?string $contentScope,
        public string $locale,
        public string $title,
        public ?string $excerpt,
        public ?string $content,
        public ?string $thumbnail,
        public ?CarbonInterface $publishedAt,
        public ?CarbonInterface $updatedAt,
        public array $availableLocales,
        public ?string $link,
    ) {}
}
```

`PublishedPostData` 是宿主发布目标返回的真实 live 信息：

```php
<?php

namespace N2ns\LaravelPost2Site\Data;

use Carbon\CarbonInterface;

final readonly class PublishedPostData
{
    public function __construct(
        public string|int $targetId,
        public string $targetType,
        public string $link,
        public ?CarbonInterface $publishedAt = null,
        public array $metadata = [],
    ) {}
}
```

## 发布目标契约

`PublicationTarget` 是扩展包和宿主站真实文章表之间的唯一写入边界。宿主项目可以把 staging post 同步到 `articles`、`blog_posts`、Headless CMS 或任意自己的内容模型。

```php
<?php

namespace N2ns\LaravelPost2Site\Contracts;

use N2ns\LaravelPost2Site\Data\PublishedPostData;
use N2ns\LaravelPost2Site\Data\PostData;

interface PublicationTarget
{
    public function publish(PostData $post): PublishedPostData;
}
```

默认 `ConfigurablePublicationTarget` 读取 `post2site.publishing.target`，适合常见 Eloquent 文章模型。`NullPublicationTarget` 只用于测试或显式禁用发布目标。如果 `publishing.mode=adapter` 且仍使用 `NullPublicationTarget`，`POST /posts/{id}/publish` 必须返回 422，提示宿主需要绑定真实 `PublicationTarget`。

发布模式：

- `review`: MCP 调用 publish 后，staging post 变为 `published`，不写宿主文章表，不存 host link；返回的 `link` 由 `public_url.pattern` 经 `PublicUrlResolver` 生成。
- `configurable`: MCP 调用 publish 后，默认 target 按配置写宿主 Eloquent 模型，成功后 staging post 变为 `published`，`link` 使用 `PublishedPostData.link`。
- `adapter`: MCP 调用 publish 后，后端立即调用宿主自定义 `PublicationTarget::publish()`，适合复杂字段、复杂路由、外部 CMS 或多表发布。

发布后 `toPostData` 的取链规则：存有非空 host `link`（configurable/adapter）直接用；为空时（review）对已发布且非未来的文章回退到 `PublicUrlResolver`（默认按 `public_url.pattern` 生成）。草稿与未来发布时间返回 `null`。

`ConfigurablePublicationTarget` 限制：

- 只支持单个 Eloquent model。
- 只支持按 `lookup` 查找或新建目标记录。
- 支持普通字段赋值、固定值、`now`。
- 翻译字段优先支持 `spatie_translatable`，也可以关闭翻译映射后直接写普通字段。
- 复杂 taxonomy、作者、审批流、旧 slug、关联表写入应改用 `adapter` 模式。

## Public URL 契约

`link` 字段必须由后端统一生成，不能由 MCP 客户端传入。在 `adapter` 模式下，发布后的 link 优先来自 `PublicationTarget` 返回的 `PublishedPostData.link`。`PublicUrlResolver` 只作为默认 staging 发布规则或宿主不需要写真实文章表时的 fallback。

```php
<?php

namespace N2ns\LaravelPost2Site\Contracts;

use N2ns\LaravelPost2Site\Data\PostData;

interface PublicUrlResolver
{
    public function resolve(PostData $post): ?string;
}
```

默认 `ConfiguredPublicUrlResolver` 行为（**对内容分类零假设**）：

- `status !== published` 返回 `null`。
- `published_at` 为空或晚于当前时间时返回 `null`。
- 否则用单一 pattern `post2site.public_url.pattern`（默认 `/{slug}`）生成 absolute public URL。
- 占位符：`{slug}`、`{locale}`、`{content_scope}`、`{key}`（`{key}` 为 content_scope 冒号后那段，无 scope 时为空）。
- 包内**不再**写死 `product:` / company blog 等任何分类语义；需要按分类区分 URL 的宿主（如 datafrog 的 `/{locale}/{key}/guides/{slug}`）应绑定自己的 `PublicUrlResolver`，或仅靠该 pattern 表达。

默认 Eloquent repository 在 `markPublished()` 时优先保存 `PublishedPostData.link` 到 `target_link`；只有没有绑定真实 publication target 的 staging-only 场景才使用 resolver 生成 fallback link。

## content_scope 校验契约

`content_scope` 在 core 里被当作**不透明分类元数据**：存、过滤、原样返回，包不绑定任何具体取值的语义。校验分三层：

1. **格式（契约级，包内置）**：必须是 `kind:key` 形状（`ContentScopeRule`）。这是发布契约本身定义的，不属于宿主私货。
2. **kind 白名单（可选便利配置）**：`post2site.content_scope.kinds`。留空 = 接受任意 kind；非空则 `kind` 必须在其中。由 env `POST2SITE_CONTENT_SCOPE_KINDS` 配置，并在 `/capabilities` 回显。
3. **key 解析（宿主委托）**：通过 `ContentScopeValidator` 契约交给宿主——例如 `kind=product` 时校验 `key` 是否为真实存在的产品。

```php
<?php

namespace N2ns\LaravelPost2Site\Contracts;

interface ContentScopeValidator
{
    // 合法返回 null；非法返回错误消息。
    public function validate(string $kind, string $key): ?string;
}
```

默认绑定 `NullContentScopeValidator`（永远通过，保持包通用、零宿主依赖）。宿主（如 datafrog）绑定自己的实现复刻「product key 必须存在于 Product 表」这类校验，无需 fork 包。

## Scope context 契约

```php
<?php

namespace N2ns\LaravelPost2Site\Contracts;

interface ScopeContextProvider
{
    /** @return array<int, array{content_scope:string,name:string}> */
    public function availableScopes(): array;

    /** @return array<string, mixed>|null */
    public function contextForScope(string $contentScope): ?array;
}
```

`contextForScope()` 返回 `content_scope` 加上宿主自定义的受控字段（包不限定具体字段）。常见示例：

- `content_scope`
- `name`
- `canonical_url`
- `docs_url`
- `summary`
- `key_points`
- `do_not_claim`

## SEO/GEO 与收录流水线

MCP 客户端不需要增加工具或参数。最优边界是：MCP 负责创建、更新、发布文章；Laravel 后端在发布成功后根据真实 public URL 完成站点侧 SEO/GEO 和收录通知。

后端流水线包含：

- `PostData.link`: 作为唯一可提交的 live public URL。没有 link 时不得提交搜索引擎。
- `PublicPostMetadata`: 从 `PostData` 生成 canonical、title、description、Open Graph、JSON-LD Article/BlogPosting 基础数据，供宿主站前台页面复用。
- sitemap: 发布后派发 `Post2SitePostPublished` event，宿主站监听事件更新 sitemap、清理页面缓存或触发自己的搜索任务。扩展包不能假设所有站点使用同一个 sitemap 生成器。
- IndexNow: 可选异步推送新增或更新 URL。
- Google: 普通文章默认不做“自动提交”。Google 侧应依赖 sitemap 和 Search Console；如果宿主项目将来接 URL Inspection API，也只应作为可选状态检查或人工运维能力，不放进 MCP 发布契约。

`Post2SitePostPublished` event：

```php
<?php

namespace N2ns\LaravelPost2Site\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use N2ns\LaravelPost2Site\Data\PostData;

final class Post2SitePostPublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly PostData $post,
    ) {}
}
```

宿主站可以监听该事件更新 sitemap：

```php
use N2ns\LaravelPost2Site\Events\Post2SitePostPublished;

Event::listen(Post2SitePostPublished::class, function (Post2SitePostPublished $event): void {
    // Refresh sitemap cache or notify the host application's sitemap generator.
});
```

`IndexingNotifier` 契约：

```php
<?php

namespace N2ns\LaravelPost2Site\Contracts;

use N2ns\LaravelPost2Site\Data\IndexingPlan;
use N2ns\LaravelPost2Site\Data\IndexingResult;

interface IndexingNotifier
{
    public function notify(IndexingPlan $plan): IndexingResult;
}
```

`IndexingPlan` 至少包含：

- `url`: absolute public URL，来自 `PostData.link`
- `host`: URL host，不含 scheme
- `post_id`
- `content_scope`
- `published_at`

`IndexNowNotifier` 行为：

- 只有 `post2site.indexing.enabled=true`、`indexnow.enabled=true`、`indexnow.key` 非空且 `PostData.link` 非空时运行。
- 默认 POST 到 `https://api.indexnow.org/indexnow`。
- JSON payload 使用 `host`、`key`、`urlList`，配置了 `key_location` 时追加 `keyLocation`。
- key 必须是 8 到 128 位的字母、数字或短横线。
- key 文件必须在同一 host 可公开访问；推荐根目录 `https://example.com/{key}.txt`，文件内容就是 key。
- 外呼设置超时（`timeout(10)`、`connectTimeout(5)`），避免对端慢响应长时间占用队列 worker。
- 接收 200 或 202 视为 accepted，记录响应码；400、403、422、429 记录失败并允许队列重试或后台人工处理。

`SubmitPublishedPostForIndexing` Job：

- 在数据库事务提交后排队（`afterCommit()`）。
- 实现 `ShouldBeUnique`，`uniqueId` 为 live `link`，`uniqueFor` 为去重窗口秒数，避免并发/重试重复推送同一 URL。
- 失败可重试：`$tries=3`，`backoff` 为 `[60, 300]` 秒。
- 只接收 `post_id` 和 `link` 等最小数据，执行时重新构造 `IndexingPlan`。
- 记录每次提交到 `post2site_indexing_submissions`，并按 `dedupe_minutes` 跳过近期重复提交。
- 不阻塞 `POST /posts/{id}/publish` 的响应。

## API key 命令

默认 database auth driver 必须提供 key 创建命令，避免宿主项目手工写 hash。

```bash
php artisan post2site:key "Production MCP"
```

`CreateApiKeyCommand` 行为：

- 生成 32 bytes random key，输出为 `p2s_` 前缀加安全随机字符串。
- 明文 key 只在命令输出中显示一次。
- 数据库只保存 `key_hash`，使用 `hash('sha256', $plain)`。随机 key 自带高熵，不需要 bcrypt/argon 等慢哈希；确定性哈希才能让中间件走唯一索引做 O(1) 校验（与 Sanctum 一致）。
- 可选参数：
  - `--user-id=`: 关联宿主用户。
  - `--expires-at=`: 设置过期时间。
  - `--plain`: 只输出明文 key，便于部署脚本读取；仍然不能输出 hash。
- 命令必须提示用户把明文 key 配置到 MCP 客户端的 `N2N_API_KEY`。

## 请求验证

控制器必须只使用 validated data。

所有 FormRequest 的 `authorize()` 默认返回 `true`。API key 鉴权由 `AuthenticatePost2SiteKey` 中间件统一完成，FormRequest 只负责字段验证和字段组合验证。

`ListPostsRequest`：

- `status`: nullable, in configured statuses
- `type`: nullable, in configured types
- `content_scope`: nullable string. 空字符串筛选未分类（unscoped）文章。
- `q`: nullable string。MySQL/MariaDB 上对翻译表 `title`/`content` 走 `FULLTEXT`（boolean 模式）；其它驱动（如测试用 SQLite）降级为 `LIKE`。
- `per_page`: nullable integer min 1 max `content.per_page_max`

`StorePostRequest`：

- `type`: nullable, in configured types
- `content_scope`: required for types in `content.scoped_types`, prohibited for others, kind:key format（key 解析委托 `ContentScopeValidator`）
- `slug`: required string, `unique:post2site_posts,slug`
- `locale`: nullable, in configured locales
- `title`: required string
- `excerpt`: nullable string
- `content`: required string
- `thumbnail`: nullable string
- `status`, `published_at`, `user_id`, `author`: prohibited

`UpdatePostRequest`：

- `type`: nullable, in configured types
- `content_scope`: nullable string, validated against next type
- `slug`: nullable string, `unique:post2site_posts,slug`（用 `ignore` 排除当前记录，支持按 id 或 slug 路由）
- `locale`: nullable, in configured locales
- `title`, `excerpt`, `content`, `thumbnail`: nullable string
- `status`, `published_at`, `user_id`, `author`: prohibited

## 响应生成

控制器通过 `PostResponseFactory` 统一生成响应。

列表响应：

```php
return response()->json(
    $this->responses->paginated($paginator)
);
```

单篇响应必须统一使用 envelope。`show`、`create`、`update`、`publish` 都返回同一结构，避免 MCP 客户端为不同动作维护多套解析逻辑。

```php
return response()->json(
    $this->responses->envelope($post, meta: $meta),
    $status,
);
```

Envelope 输出：

- `blog_post`: `post()` 的标准文章对象。
- `available_locales`: 当前文章已有语言。
- `missing_locales`: 站点推荐语言中尚未完成的语言。
- `next_actions`: MCP 客户端可继续执行的建议动作。
- `meta`: 可选对象，只放动作级附加信息，例如 `indexnow_queued`。

`post()` 输出至少包含：

- `id`
- `slug`
- `type`
- `status`
- `content_scope`
- `locale`
- `title`
- `excerpt`
- `content`
- `thumbnail`
- `published_at`
- `updated_at`
- `link`

`link` 对 draft 或未来发布时间内容应为 `null`；已发布内容应为 live 网站绝对 public URL。

## 控制器骨架

```php
<?php

namespace N2ns\LaravelPost2Site\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use N2ns\LaravelPost2Site\Contracts\PublicationTarget;
use N2ns\LaravelPost2Site\Contracts\PostRepository;
use N2ns\LaravelPost2Site\Contracts\ScopeContextProvider;
use N2ns\LaravelPost2Site\Events\Post2SitePostPublished;
use N2ns\LaravelPost2Site\Http\Requests\ListPostsRequest;
use N2ns\LaravelPost2Site\Http\Requests\StorePostRequest;
use N2ns\LaravelPost2Site\Http\Requests\UpdatePostRequest;
use N2ns\LaravelPost2Site\Jobs\SubmitPublishedPostForIndexing;
use N2ns\LaravelPost2Site\Repositories\NullPublicationTarget;
use N2ns\LaravelPost2Site\Support\PostResponseFactory;

class Post2SiteController extends Controller
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly PublicationTarget $publicationTarget,
        private readonly ScopeContextProvider $scopes,
        private readonly PostResponseFactory $responses,
    ) {}

    public function capabilities(): JsonResponse
    {
        return response()->json([
            'contract' => 'Content Publishing API Contract',
            'contract_version' => '1.0',
            'base_path' => '/'.trim(config('post2site.route_prefix'), '/'),
            'auth' => ['required_headers' => [config('post2site.auth.header', 'X-API-KEY')]],
            'endpoints' => [
                'capabilities' => 'GET /capabilities',
                'scope_context' => 'GET /scopes/{content_scope}',
                'list_posts' => 'GET /posts',
                'create_post' => 'POST /posts',
                'get_post' => 'GET /posts/{id_or_slug}',
                'update_post' => 'PATCH /posts/{id_or_slug}',
                'publish_post' => 'POST /posts/{id_or_slug}/publish',
            ],
            'content' => [
                'input_model' => 'single_locale',
                'locale_field' => 'locale',
                'types' => config('post2site.content.types'),
                'statuses' => config('post2site.content.statuses'),
                'locales' => config('post2site.content.locales'),
                'default_locale' => config('post2site.content.default_locale'),
                'recommended_locales' => config('post2site.content.locales'),
                'create_update_prohibited_fields' => ['status', 'published_at', 'user_id', 'author'],
            ],
            'translation' => [
                'backend_auto_translate' => false,
                'client_should_complete_missing_locales' => true,
                'missing_locales_returned' => true,
            ],
            'scopes' => $this->scopes->availableScopes(),
            'publishing' => [
                'mode' => config('post2site.publishing.mode'),
                'manual_review_required' => false,
            ],
            'indexing' => [
                'sitemap' => config('post2site.indexing.sitemap.enabled'),
                'indexnow' => config('post2site.indexing.indexnow.enabled') && filled(config('post2site.indexing.indexnow.key')),
                'google_auto_submit' => false,
            ],
            'limits' => [
                'per_page_max' => config('post2site.content.per_page_max'),
                'default_status_on_create' => 'draft',
            ],
            'safety' => [
                'delete_exposed' => false,
                'database_access_exposed' => false,
                'shell_access_exposed' => false,
                'server_operations_exposed' => false,
            ],
        ]);
    }

    public function index(ListPostsRequest $request): JsonResponse
    {
        $paginator = $this->posts->listPosts(
            $request->validated(),
            $request->integer('per_page', 20),
        );

        return response()->json($this->responses->paginated($paginator));
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->posts->createPost($request->validated());

        return response()->json($this->responses->envelope($post), 201);
    }

    public function show(string $idOrSlug): JsonResponse
    {
        return response()->json($this->responses->envelope(
            $this->posts->findPostByIdOrSlug($idOrSlug),
        ));
    }

    public function update(UpdatePostRequest $request, string $idOrSlug): JsonResponse
    {
        $post = $this->posts->findPostByIdOrSlug($idOrSlug);
        $updated = $this->posts->updatePost($post->id, $request->validated());

        return response()->json($this->responses->envelope($updated));
    }

    public function publish(string $idOrSlug): JsonResponse
    {
        $post = $this->posts->findPostByIdOrSlug($idOrSlug);

        if (config('post2site.publishing.mode') === 'review') {
            $published = $this->posts->markPublished($post->id, new PublishedPostData(
                targetId: $post->id,
                targetType: 'post2site',
                link: '',
                publishedAt: now(),
            ));

            return response()->json($this->responses->envelope($published, [
                'sitemap_update_expected' => config('post2site.indexing.sitemap.enabled'),
                'indexnow_queued' => false,
                'google_auto_submit' => false,
            ]));
        }

        if (config('post2site.publishing.mode') === 'adapter' && $this->publicationTarget instanceof NullPublicationTarget) {
            throw ValidationException::withMessages([
                'publication_target' => 'A real PublicationTarget binding is required when post2site.publishing.mode is adapter.',
            ]);
        }

        $target = $this->publicationTarget->publish($post);
        $published = $this->posts->markPublished($post->id, $target);
        Post2SitePostPublished::dispatch($published);

        if (config('post2site.indexing.enabled', true) && filled($published->link)) {
            SubmitPublishedPostForIndexing::dispatch($published->id, $published->link)
                ->afterCommit()
                ->onQueue(config('post2site.indexing.queue', 'default'));
        }

        return response()->json($this->responses->envelope($published, [
            'sitemap_update_expected' => config('post2site.indexing.sitemap.enabled'),
            'indexnow_queued' => config('post2site.indexing.indexnow.enabled') && filled($published->link),
            'google_auto_submit' => false,
        ]));
    }

    public function scopeContext(string $contentScope): JsonResponse
    {
        $context = $this->scopes->contextForScope($contentScope);

        return $context
            ? response()->json($context)
            : response()->json(['message' => 'The selected content_scope does not exist.'], 404);
    }
}
```

## 认证中间件

`AuthenticatePost2SiteKey` 必须读取 `post2site.auth.header`，默认 header 是 `X-API-KEY`。数据库驱动对请求明文 key 计算 `hash('sha256', $plain)` 后，用 `key_hash` 唯一索引单条精确查找（O(1)，不遍历全部 key、不逐个慢哈希），再校验未撤销与未过期；静态驱动用 `hash_equals` 比较 `post2site.auth.static_key`。

认证失败返回 401，错误信息应保持简短，不能泄露 key 是否存在、hash 算法、用户信息或内部异常。数据库驱动校验成功后可以在宿主应用中登录关联用户。`last_used_at` 不在每个请求都写库，只有为空或距上次更新超过 1 分钟才刷新，避免在读热点路径产生写放大。

## 默认数据模型

默认迁移需要包含：

`post2site_posts`

- `id`
- `type`
- `content_scope` nullable, indexed
- `status` default `draft`, indexed
- `slug` unique
- `thumbnail` nullable
- `published_at` nullable
- `target_type` nullable
- `target_id` nullable
- `target_link` nullable
- timestamps

`post2site_post_translations`

- `id`
- `post2site_post_id`
- `locale`, indexed
- `title`
- `excerpt` nullable
- `content`
- timestamps
- unique `post2site_post_id + locale`
- `FULLTEXT(title, content)`（仅 MySQL/MariaDB 创建，供 `?q=` 搜索；其它驱动跳过并由仓库降级为 `LIKE`）

`post2site_api_keys`

- `id`
- `name`
- `key_hash` unique（确定性 SHA-256，支撑中间件 O(1) 查找）
- `user_id` nullable
- `revoked_at` nullable
- `expires_at` nullable
- `last_used_at` nullable
- timestamps

API key 默认实现应存 hash。静态 key driver 只用于低风险自托管或开发环境。

`post2site_indexing_submissions`

- `id`
- `post_id` nullable, indexed
- `url`
- `driver`, for example `indexnow`
- `status` indexed: `queued`, `accepted`, `failed`
- `http_status` nullable
- `response_body` nullable text
- `attempts` unsigned integer
- `last_submitted_at` nullable
- timestamps
- 复合索引 `(url, driver, last_submitted_at)`，支撑去重查找

该表只记录收录通知状态，不决定文章是否发布。`publish` API 返回成功后，即使 IndexNow 队列失败，文章仍然保持 published，后台可以单独重试。

## 测试要求

当前实现应把以下测试作为最低验收：

- `GET /capabilities` 返回契约、端点、内容规则、scopes、limits、safety。
- `GET /capabilities` 返回 `publishing.mode` 和 `manual_review_required`。
- `GET /posts?status=draft` 只返回 draft，分页 `data[]` 每项包含 `link` 字段。
- `GET /posts?content_scope=` 只返回 company blog。
- `GET /posts?content_scope=product:example` 只返回对应 guide。
- `POST /posts` 默认创建 draft。
- `POST /posts` / `PATCH /posts/{id}` 拒绝 `status`、`published_at`、`user_id`、`author`。
- `POST /posts` / `PATCH /posts/{id}` 提交重复 `slug` 时在验证层返回 422，而不是 DB 冲突的 500。
- `PATCH /posts/{id}` 只更新元数据（如 `thumbnail`）时不创建空白翻译行。
- `POST /posts` 对 `content.scoped_types` 中的类型要求合法 `content_scope`。
- `PATCH /posts/{id}` 校验下一状态下 `type` 与 `content_scope` 的组合。
- create/update/publish 响应返回 `available_locales`、`missing_locales`、`next_actions`。
- `GET /posts/{id}`、create、update、publish 都返回统一 envelope。
- `POST /posts/{id}/publish` 在 adapter 模式调用 `PublicationTarget::publish()`，记录 `target_id`、`target_type`、`target_link`，并设置 `status=published`。
- `POST /posts/{id}/publish` 在 configurable 模式按 `publishing.target` 写入宿主 Eloquent model，记录 `target_id`、`target_type`、`target_link`。
- `POST /posts/{id}/publish` 在 configurable 模式缺少 `target.model` 或字段映射非法时返回 422。
- `POST /posts/{id}/publish` 在 review 模式设置 `status=published`，不写宿主文章表，`link` 由 `public_url.pattern` 生成，`manual_review_required=false`。
- `POST /posts/{id}/publish` 在 adapter 模式且未绑定真实 `PublicationTarget` 时返回 422。
- `POST /posts/{id}/publish` 在成功 live 后派发 `Post2SitePostPublished`，宿主监听器可更新 sitemap。
- `POST /posts/{id}/publish` 在 `link` 非空且 indexing 启用时派发 `SubmitPublishedPostForIndexing`。
- `POST /posts/{id}/publish` 在 `link` 为空时不推送 IndexNow。
- 默认 `ConfiguredPublicUrlResolver` 对 draft、未来发布时间返回 `null`，对 published 返回 absolute public URL。
- `php artisan post2site:key` 只显示明文 key 一次，数据库只保存 SHA-256 `key_hash`。
- 数据库驱动鉴权用 `key_hash` 单条索引查找校验明文 key，并刷新 `last_used_at`。
- `createPost`/`updatePost` 的多表写入包裹在 `DB::transaction()` 内，失败不留孤立主记录。
- IndexNow key route 默认关闭；开启后只对当前 key 返回 `text/plain` 内容。
- `IndexNowNotifier` 发送 `host`、`key`、`keyLocation`、`urlList`，并记录 200/202/4xx/429 结果。
- capabilities 暴露 `indexing.sitemap`、`indexing.indexnow` 和 `google_auto_submit=false`。
- 没有 delete route。
