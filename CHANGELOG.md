# Changelog

All notable changes to Laravel Post2Site are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

## [0.5.0] - 2026-10-20

### Added

- Add a first-publish walkthrough and an opt-in text-blog adapter example with persistent public articles, signed previews, and confirmation-flow tests.

### Changed

- Explain the companion MCP bridge, required host adapter, and Laravel-specific PHP requirements before installation.
- Correct the API-key command's setup hint to use the bridge's `CONTENT_API_KEY` variable.
- `publish_confirmed` must be JSON `true`; other accepted-style values such as `"yes"`, `"on"`, `"1"`, and `1` are rejected.
- `GET /drafts` rejects non-integer or non-positive `per_page` values instead of returning every draft or an empty page.
- `capabilities.package_version` comes from the installed package; the `version` entry is removed from the publishable config, so a previously published config no longer reports a stale version.

### Fixed

- `GET /drafts` returns a real `next_cursor` and accepts it as `cursor`; previously `next_cursor` was always `null` even when more drafts existed.
- Empty `client_metadata`, `host_metadata`, `metadata`, and `details` maps in package responses are encoded as JSON objects (`{}`) instead of lists (`[]`).

## [0.4.0] - 2026-07-02

### Added

- Add contract coverage for slash-containing inventory resource target identifiers sent as percent-encoded path values.
- Add fail-fast coverage for missing host `Post2SiteAdapter` configuration.

### Removed

- Remove the null content adapter fallback and stale preset config merge hook.

## [0.3.0] - 2026-07-01

### Changed

- Redesign the package around the generic `post2site-publishing` MCP contract.
- Replace the old post CRUD API with draft, asset, validation, preview, inventory, duplicate-check, and publish endpoints.
- Replace host-specific publishing presets with a single `Post2SiteAdapter` contract.
- Move host content fields into opaque `content_payload` JSON handled by the host adapter.
- Add package-managed staging tables for drafts and selected assets.
- Require explicit publish confirmation for publish.

### Removed

- Remove the old `/posts` CRUD API.
- Remove legacy post repository/public URL/publication target abstractions.
- Remove compatibility presets from core package positioning.

## [0.2.1] - 2026-06-24

### Fixed

- Last release before the MCP contract redesign.

## [0.2.0] - 2026-06-24

### Added

- Last release with preset-based blog publishing support.

## [0.1.1] - 2026-06-23

### Changed

- Load package migrations automatically so a normal `php artisan migrate` creates Post2Site tables after package installation.
- Keep migration publish tags available for hosts that need to customize migrations before running them.

## [0.1.0] - 2026-06-17

### Added

- Initial release of the Laravel backend for the N2N Post2Site Content Publishing API Contract.
