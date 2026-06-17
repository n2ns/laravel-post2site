# Contributing

Thanks for helping improve Laravel Post2Site.

## Development setup

```bash
composer install
composer test
```

`composer test` runs PHPUnit (via Orchestra Testbench). `composer pint:test` checks code style.

## Pull request guidelines

- Keep changes focused and easy to review.
- Add or update tests for behavior changes.
- Update `README.md` and the relevant `docs/` pages when routes, config keys, contracts, or the API contract change.
- Run `composer test` and `composer pint:test` before opening a PR.

## Commit hygiene

- Do not commit `.env` files or API keys.
- Do not commit `vendor/` or other generated output.
- Explain user-visible behavior changes in the PR description.

## Design principles

This package is a generic backend for the Content Publishing API Contract. Keep host-specific assumptions out of the package: model categories, URL shapes, and entity validation belong behind the bindable contracts (`PublicUrlResolver`, `ContentScopeValidator`, `ScopeContextProvider`, `PublicationTarget`, `PostRepository`), not in the core. See [docs/architecture.md](docs/architecture.md).

## Changelog

Record user-visible changes in `CHANGELOG.md` (Keep a Changelog format).
