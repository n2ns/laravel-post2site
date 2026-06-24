# Changelog

All notable changes to Laravel Post2Site are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

## [0.2.0] - 2026-06-24

### Added

- Add `POST2SITE_PRESET` support for known host/blog package integrations.
- Add first-party `laravel_saas_kit` adapter for SaaS Kit blog posts, translations, product scope validation, product scope context, and public URLs.
- Add `austintoddj_canvas` adapter preset for Canvas posts and Canvas users.
- Add configurable presets for `bjuppa/laravel-blog` and `stephenjude/filament-blog`.
- Document the current blog target list.

## [0.1.1] - 2026-06-23

### Changed

- Load package migrations automatically so a normal `php artisan migrate` creates Post2Site tables after package installation.
- Keep migration publish tags available for hosts that need to customize migrations before running them.

## [0.1.0] - 2026-06-17

### Added

- Initial release of the Laravel backend for the N2N Post2Site Content Publishing API Contract.
- Protected publishing routes (`capabilities`, `posts` CRUD, `publish`, `scopes`) behind API-key auth and per-route rate limiting.
- API-key auth with `static` and `database` drivers; database keys stored as SHA-256 hashes with a unique-index O(1) lookup. `post2site:key` console command.
- Package-owned staging tables for posts, translations, API keys, and indexing submissions.
- Three publishing modes: `review`, `configurable` (host model field mapping), and `adapter` (custom `PublicationTarget`).
- Generic `content_scope` (`kind:key`): contract-level format check, optional `content_scope.kinds` whitelist, host-bound `ContentScopeValidator` for key resolution, and config-driven `content.scoped_types`.
- Host-bindable contracts: `PostRepository`, `PublicationTarget`, `PublicUrlResolver`, `ContentScopeValidator`, `ScopeContextProvider`, `IndexingNotifier`.
- Generic public URL generation via `public_url.pattern` (no baked-in categories).
- Optional IndexNow submission via a queued, unique, after-commit job; `Post2SitePostPublished` event for host sitemap/cache hooks.
- `?q=` search using a MySQL/MariaDB `FULLTEXT` index with a `LIKE` fallback on other drivers.
