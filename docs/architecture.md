# Architecture

`n2ns/laravel-post2site` exposes a generic MCP publishing backend for Laravel hosts.

The package fixes workflow, envelopes, staging persistence, asset references, validation issue shape, optimistic concurrency, and publish idempotency. It does not fix a blog schema. Host applications declare and validate their own fields through `Post2SiteAdapter`.

## Components

- `routes/api.php` registers `/api/v1/mcp/*` routes behind API-key auth and rate limiting.
- `McpPublishingController` handles package-level workflow validation, draft persistence, asset scope checks, version checks, idempotency, and response envelopes.
- `AuthenticatePost2SiteKey` validates `X-API-KEY` and attaches client attribution to the request.
- `Post2SiteAdapter` is the host boundary for content model, inventory, duplicate checks, validation, selected-asset storage, preview, and publish.
- `post2site_drafts` stores server drafts with `content_payload`, `asset_refs`, `version`, validation state, and publish result.
- `post2site_assets` stores selected asset references and client/draft ownership.
- `post2site_idempotency_records` caches successful publish results per client, route, draft, and idempotency key.

## Package Fields

The package accepts these workflow fields:

- `mode`
- `target_identifier`
- `content_payload`
- `client_metadata`
- `draft_id`
- `version`
- `expected_version`
- `Idempotency-Key`
- `If-Match`
- `user_confirmed_publish`
- `acknowledged_warnings`

Forbidden host-owned or lifecycle fields are rejected when they appear inside `content_payload`, including `status`, `published_at`, `user_id`, `author`, `content_origin`, `managed_by`, and `authoring_source`.

## Adapter Boundary

`Post2SiteAdapter` methods:

- `capabilities()`
- `siteContext()`
- `editorialPolicy()`
- `inventory(array $query)`
- `inventoryStats(array $query)`
- `findDuplicates(array $payload)`
- `validateContentPayload(DraftContext $context, array $contentPayload, string $mode)`
- `extractAssetRefs(array $contentPayload)`
- `storeSelectedAsset(AssetUpload $upload, ClientContext $clientContext, ?DraftContext $draftContext = null)`
- `previewDraft(DraftContext $context)`
- `publishDraft(DraftContext $context, PublishRequest $request)`

The package calls `extractAssetRefs()` after draft create/update and checks that referenced assets belong to the same client and draft scope. The package never parses host fields to discover assets.

## Publish Flow

1. Require `user_confirmed_publish`, `expected_version`, and `Idempotency-Key`.
2. Open a database transaction.
3. Reserve or lock the idempotency record.
4. Lock the draft row.
5. Check `expected_version` / `If-Match`.
6. Run publish-mode validation.
7. Call `Post2SiteAdapter::publishDraft()`.
8. Store draft publish state and idempotency success response.
9. Commit.

Same key and same payload returns the cached result. Same key and different payload returns `412 idempotency_conflict`.
