# Laravel Post2Site

Laravel backend package for the N2N Post2Site Content Publishing API Contract.

The package provides protected MCP publishing routes, package-owned staging tables for drafts and translations, optional configurable publishing into host Eloquent models, custom `PublicationTarget` adapters for complex sites, and optional IndexNow submission after posts are published.

Hosts can start with zero custom code in review mode, publish common Eloquent models through configuration, or implement a `PublicationTarget` adapter only for complex publishing workflows.

## Notes

- **Auth**: database-driver API keys are stored as deterministic SHA-256 hashes and matched via a unique index (O(1)); `post2site:key` prints the plaintext key once.
- **Rate limiting**: every route is throttled. Tune via `POST2SITE_RATE_LIMIT` (`max,minutes`, default `60,1`).
- **Search**: `?q=` uses a `FULLTEXT` index on MySQL/MariaDB (created by the migration on those drivers) and falls back to `LIKE` elsewhere (e.g. SQLite in tests).

## Validation

```bash
composer test
composer pint:test
composer validate --strict
```

## Docs

- [Package Proposal](docs/package_proposal.md)
- [Host Integration Walkthrough](docs/host_integration_walkthrough.md)
