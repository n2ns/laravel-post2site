# Laravel Post2Site 扩展包设计评审与规范检查报告

本报告对 `laravel-post2site` 扩展包的设计与代码实现进行系统性检查，主要评估其是否符合 Laravel 官方设计规范、安全性以及在高并发场景下的性能表现。

---

## 1. 总体评估
`laravel-post2site` 扩展包整体设计结构清晰，符合现代化 Laravel 11/12+ 扩展包的最佳开发实践：
- **包自动发现（Package Discovery）**：完美支持宿主通过 Composer 自动加载 Service Provider。
- **现代化架构**：引入了强类型 DTO（`PostData`）、接口隔离（Contracts）以及清晰的 Repository 与适配器边界，宿主接入非常友好。
- **框架新特性适配**：使用了 Laravel 11+ 新引入的 `publishesMigrations` 与 `replaceConfigRecursivelyFrom` 等方法，配置与迁移加载逻辑正确。

但是，代码实现中存在 **2 处违背 Laravel 官方最佳实践、或引起性能与安全风险的严重设计隐患**，建议在发布前完成修复。

> **修复状态（2026-06-17）**：本报告原列的 2 处隐患及第 3 节优化建议均已修复，复审中额外发现的问题（空白翻译行、收录竞态、限流缺失、搜索全表扫等）也一并修复。详见文末「修复记录」。`composer test`（22 项）与 `pint` 均通过。

---

## 2. 详细审计与隐患分析

### ⚠️ 隐患一：API Key 校验存在严重的性能瓶颈与拒绝服务（DoS）隐患（高风险）✅ 已修复
* **涉及文件**：[`AuthenticatePost2SiteKey.php`](file:///home/deploy/projects/datafrog.io-dev/laravel-post2site/src/Http/Middleware/AuthenticatePost2SiteKey.php)
* **当前实现**：
  ```php
  // 数据库驱动校验逻辑
  foreach ($model::query()->whereNull('revoked_at')->get() as $key) {
      if ($key->expires_at !== null && $key->expires_at->isPast()) {
          continue;
      }

      if (Hash::check($plain, $key->key_hash)) {
          $key->forceFill(['last_used_at' => now()])->save();
          return true;
      }
  }
  ```
* **风险点分析**：
  1. **CPU 性能耗尽**：`Hash::check()` 底层使用 Bcrypt/Argon2 等慢哈希算法（单次调用需耗时 100ms 左右的 CPU 时间）。在 PHP 中循环遍历所有活跃 key 并进行 `Hash::check()`，时间复杂度是 $O(N)$。如果数据库中有 100 个 Key，收到一条非法请求时，系统需要执行 100 次慢哈希计算，导致单次接口验证阻塞将近 **10 秒**，服务器 CPU 瞬间暴涨。
  2. **漏洞利用**：攻击者极易通过伪造随机的 X-API-KEY 进行高频调用，导致应用服务器陷入 CPU 瘫痪状态，造成严重的拒绝服务攻击（DoS）。
* **Laravel 官方及行业实践（如 Sanctum）建议**：
  - 随机生成的 API Key 本身自带极高的随机熵，**不需要**使用为了防弱密码猜测而设计的慢哈希算法，应改为**快速且确定性（Deterministic）的哈希算法**（如 SHA-256）。
  - Sanctum 架构中，客户端传入的 key 进行 SHA-256 哈希后，直接使用主键或唯一索引去数据库查询（例如：`where('key_hash', hash('sha256', $plain))`）。这样校验复杂度为 $O(1)$ 且走索引，防止了 CPU 被暴力拖慢。

---

### ⚠️ 隐患二：验证层（FormRequest）缺失 Slug 唯一性校验（中风险）✅ 已修复
* **涉及文件**：
  - [`StorePostRequest.php`](file:///home/deploy/projects/datafrog.io-dev/laravel-post2site/src/Http/Requests/StorePostRequest.php)
  - [`UpdatePostRequest.php`](file:///home/deploy/projects/datafrog.io-dev/laravel-post2site/src/Http/Requests/UpdatePostRequest.php)
* **当前实现**：
  ```php
  'slug' => ['required', 'string', 'max:255'] // Store
  'slug' => ['nullable', 'string', 'max:255'] // Update
  ```
* **风险点分析**：
  - 在 `create_post2site_posts_tables.php` 迁移文件中，`slug` 字段在 `post2site_posts` 表上具有 **`unique`** 约束。
  - 由于请求验证层（FormRequest）中没有对 slug 进行 `unique:post2site_posts,slug` 验证，若客户端提交了一个重复已有的 slug，请求将穿透验证层，在数据库写入时抛出 `Illuminate\Database\QueryException`（Unique 索引冲突）。
  - 这会导致客户端收到 Laravel 统一包装的 **500 Internal Server Error**，而不是符合契约的 **422 Unprocessable Entity** 伴随详细错误消息。
* **Laravel 官方建议**：
  - 应使用 Laravel 内置的唯一性验证规则，将错误拦截在验证层，优雅地给客户端返回 `422` 校验响应。

---

### 3. 其他优化建议（低风险）✅ 已处理

* **数据库事务一致性（Transaction Safety）**：
  在 [`Post2SiteController::publish`](file:///home/deploy/projects/datafrog.io-dev/laravel-post2site/src/Http/Controllers/Post2SiteController.php) 中，触发了 `PublicationTarget::publish($post)` 并派发了收录队列 Job `SubmitPublishedPostForIndexing`。
  由于该方法可能同时涉及宿主文章表写入、Staging 状态更新等多步写库操作，目前控制器并**没有**包裹在事务 `DB::transaction()` 内运行。如果发生写冲突或网络抖动，可能导致主表和 Staging 表的状态不同步，建议至少将发布方法内的多步数据库操作包裹在事务中。

  > **复审修正**：事务真正落在更确定的位置——仓库的 `createPost`/`updatePost`（主表 + 翻译表两步写入）已包裹 `DB::transaction()`。`publish` 路径未强行包事务：`PublicationTarget` 可能是含外部 HTTP 调用的宿主适配器，把外部调用放进 DB 事务会造成长事务持锁，属反模式；`review` 模式的 `markPublished` 本身是单条写入，无需事务。

---

## 4. 重构推荐方案

### 4.1 重构 API Key 哈希匹配机制

1. **修改 API Key 生成命令**：将原先存入数据库的慢哈希改为 SHA-256 快速哈希。
   - 文件：`src/Console/Commands/CreateApiKeyCommand.php`
   ```php
   // 修改前
   'key_hash' => Hash::make($plain),

   // 修改后
   'key_hash' => hash('sha256', $plain),
   ```

2. **修改中间件验证逻辑**：实现单条 SQL 精确查找，时间复杂度变为 $O(1)$。
   - 文件：`src/Http/Middleware/AuthenticatePost2SiteKey.php`
   ```php
   private function validKey(string $plain): bool
   {
       if (config('post2site.auth.driver') === 'static') {
           return hash_equals((string) config('post2site.auth.static_key'), $plain);
       }

       $model = config('post2site.auth.model', Post2SiteApiKey::class);
       $hashed = hash('sha256', $plain);

       // 数据库快速匹配
       $key = $model::query()
           ->where('key_hash', $hashed)
           ->whereNull('revoked_at')
           ->first();

       if ($key) {
           if ($key->expires_at !== null && $key->expires_at->isPast()) {
               return false;
           }

           $key->forceFill(['last_used_at' => now()])->save();
           return true;
       }

       return false;
   }
   ```

### 4.2 完善 FormRequest 唯一性验证

1. **修改创建请求**：
   - 文件：`src/Http/Requests/StorePostRequest.php`
   ```php
   'slug' => ['required', 'string', 'max:255', 'unique:post2site_posts,slug'],
   ```

2. **修改更新请求**（需要排除当前正在更新的记录本身）：
   - 文件：`src/Http/Requests/UpdatePostRequest.php`
   ```php
   use Illuminate\Validation\Rule;

   public function rules(): array
   {
       $idOrSlug = $this->route('idOrSlug');
       
       $uniqueRule = Rule::unique('post2site_posts', 'slug');
       if (is_numeric($idOrSlug)) {
           $uniqueRule->ignore($idOrSlug, 'id');
       } else {
           $uniqueRule->ignore($idOrSlug, 'slug');
       }

       return [
           // ...
           'slug' => ['nullable', 'string', 'max:255', $uniqueRule],
           // ...
       ];
   }
   ```

---

## 5. 修复记录（2026-06-17）

### 5.1 本报告原列问题
| 问题 | 处理 |
|---|---|
| 隐患一 API Key 慢哈希 DoS | 命令改 `hash('sha256')`；中间件改 `key_hash` 唯一索引 O(1) 查找；迁移补 `key_hash` 唯一索引；`last_used_at` 改为超过 1 分钟才写一次 |
| 隐患二 slug 唯一性 | `StorePostRequest` 加 `unique`，`UpdatePostRequest` 加 `unique(...)->ignore(id\|slug)` |
| 优化 3 事务 | 仓库 `createPost`/`updatePost` 包 `DB::transaction()`（见上方复审修正） |

### 5.2 复审额外发现并修复
| 问题 | 处理 |
|---|---|
| 部分更新生成空白翻译行 | `updatePost` 仅在请求含 `title/excerpt/content` 时才 upsert 翻译 |
| 收录去重存在竞态 | `SubmitPublishedPostForIndexing` 实现 `ShouldBeUnique`（`uniqueId=link`、`uniqueFor=去重窗口`），并加 `$tries`/`backoff` |
| IndexNow 外呼无超时 | `Http::timeout(10)->connectTimeout(5)` |
| 全部路由无限流 | 新增 `post2site.rate_limit` 配置，API 组与 `/{key}.txt` 均挂 `throttle` |
| 搜索前导 `%like%` 全表扫 longText | MySQL/MariaDB 走 `FULLTEXT(title, content)`（boolean 模式）+ 迁移按驱动创建索引；其它驱动降级 `LIKE` |
| 收录去重查询缺复合索引 | submissions 表加 `(url, driver, last_submitted_at)` 复合索引 |

### 5.3 评估后未改（有取舍）
- **`PostResponseFactory` 改 `JsonResource`**：属风格重构、非 bug，按最小改动原则保留。
- **控制器对同一记录二次查询 / PUT 与 PATCH 同义**：需改 Repository 契约签名，波及面大、收益低，暂留。

### 5.4 通用化重构（让原型 datafrog.io-web 能反向迁移到本包）
目标：宿主纯靠「配置 + 绑定契约」复刻行为，不 fork 包源码。
- **content_scope 去语义化**：core 当不透明分类元数据。校验分三层——格式（`kind:key`，契约级）、kind 白名单（`POST2SITE_CONTENT_SCOPE_KINDS`，可选、留空接受任意、capabilities 回显）、key 解析（新增 `ContentScopeValidator` 契约委托宿主，默认放行）。
- **URL 去硬编码**：删除写死的 `product:` / `company_blog` 分支与 `public_urls`/`product_guide_pattern` 等私货；统一为单一 `public_url.pattern`（默认 `/{slug}`，占位符 `{slug}{locale}{content_scope}{key}`），复杂场景绑定 `PublicUrlResolver`。
- **capabilities** 暴露 content_scope 契约块（format/kinds/examples）供客户端发现。

> 文档同步：`docs/package_proposal.md`、`docs/host_integration_walkthrough.md` 已同步以上全部变更。
