# Changelog

All notable changes to Laravel Post2Site are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Changed

- Redesign the package around the generic `post2site-publishing` MCP contract.
- Replace the old post CRUD API with draft, asset, validation, preview, inventory, duplicate-check, and publish endpoints.
- Replace host-specific publishing presets with a single `Post2SiteAdapter` contract.
- Move host content fields into opaque `content_payload` JSON owned by the host adapter.
- Add package-owned staging tables for drafts, selected assets, and idempotency records.
- Require explicit publish confirmation, optimistic version checks, and `Idempotency-Key` for publish.

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
