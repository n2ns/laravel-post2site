# Architecture

`n2ns/laravel-post2site` exposes a generic MCP publishing backend for Laravel hosts.

The package fixes workflow, envelopes, staging persistence, asset references, validation issue shape, and publish confirmation. It does not fix a blog schema. Host applications declare and validate content fields through `Post2SiteAdapter`.

## Components

- `routes/api.php` registers `/api/v1/mcp/*` routes behind API-key auth and rate limiting.
- `McpPublishingController` handles package-level workflow validation, draft persistence, asset existence checks, and response envelopes.
- `AuthenticatePost2SiteKey` validates `X-API-KEY` and attaches client attribution to the request.
- `Post2SiteAdapter` is the host boundary for content model, inventory, duplicate checks, validation, selected-asset storage, preview, and publish.
- `post2site_drafts` stores server drafts with `content_payload`, `asset_refs`, `version`, validation state, and publish result.
- `post2site_assets` stores selected asset references and optional draft attribution.

## Package Request Fields

The package accepts these workflow request fields:

- `mode`
- `target_identifier`
- `content_payload`
- `client_metadata`
- `draft_id`
- `publish_confirmed`
- `acknowledged_warnings`

`version` is returned as draft state metadata. It is not accepted in requests.

Reserved host lifecycle fields are rejected when they appear inside `content_payload`, including `status`, `published_at`, `author`, `content_origin`, `managed_by`, and `authoring_source`.

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

The package calls `extractAssetRefs()` after draft create/update and checks that referenced assets exist. The package never parses host fields to discover assets.

## Publish Flow

1. Require `publish_confirmed`.
2. Run publish-mode validation.
3. Call `Post2SiteAdapter::publishDraft()`.
4. Store draft publish state and result.
