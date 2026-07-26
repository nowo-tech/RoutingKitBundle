# RoutingKitBundle Constitution

## Core Principles

### I. Safe maintenance for visitors and operators

Routing Kit Bundle must return a clear **HTTP 503** experience for visitors while keeping operator paths (panel, health checks, exclusions) reachable. Never leak panel credentials, bypass tokens, or internal URLs on the public maintenance page.

### II. Spec-first, test-proven

Product behavior is defined in `specs/001-baseline/spec.md` (GitHub Spec Kit) and `docs/SPEC-DRIVEN-DEVELOPMENT.md`. **PHPUnit** and **PHPStan** (including `nowo-tech/phpstan-frankenphp` rulesets) are the mechanical proof. Behavioral changes require tests in the same change set.

### III. 100% code inventory traceability

Every production source file under `src/` must map to at least one requirement or inventory row in `specs/001-baseline/code-inventory.md`. New files require spec updates in the same PR.

### IV. Consumer contract vs demos

The Packagist contract covers documented configuration, services, Twig namespace `NowoRoutingKitBundle`, translations, and CLI. **`demo/`** trees are illustrative only unless promoted to stable API in the spec.

### V. Symfony compatibility

Support the PHP and Symfony ranges in `composer.json` (Symfony 7|8). Prefer PHP 8 attributes; do not introduce `doctrine/annotations`.

## Security Requirements

- Store only password **hashes** for the panel gate; never plaintext passwords in config.
- Panel POST actions require CSRF when the CSRF manager is available.
- Treat `security.bypass_token` as a shared secret (env); prefer IP exclusions for standing ops access.
- Keep `var/maintenance/` outside the web root.

## Quality Gates

- `composer qa` / `make release-check` before merge or release.
- PHP line coverage floor: **100%** (see README / `coverage-check`).
- `make validate-translations` for locale key parity (en/es/it/fr/pt/de/nl).

## Governance

This constitution guides Spec Kit workflows (`/speckit-*` skills). Amendments require updating this file, the baseline spec if principles affect behavior, and a note in `docs/CHANGELOG.md` when consumer-visible.

**Version**: 1.0.0 | **Ratified**: 2026-07-26 | **Last Amended**: 2026-07-26
