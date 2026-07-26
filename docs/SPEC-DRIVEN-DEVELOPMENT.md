# Spec-driven development

## Table of contents

- [Three layers in sync](#three-layers-in-sync)
- [GitHub Spec Kit baseline](#github-spec-kit-baseline)
- [User stories](#user-stories)
- [Functional scope](#functional-scope)
- [Validating the functional spec](#validating-the-functional-spec)
- [Requirement identifiers](#requirement-identifiers-selected)
- [Contributor workflow](#contributor-workflow)
- [Relationship to Engram](#relationship-to-engram)
- [See also](#see-also)

## Three layers in sync

1. **Spec Kit baseline** — [`specs/001-baseline/`](../specs/001-baseline/) records
   the current product, functional requirements, success criteria, and a complete
   `src/` inventory.
2. **Product behaviour** — locale path CRUD, canonical styles, route loader, and
   SeoKit bridge. Documented in [USAGE.md](USAGE.md) and [CONFIGURATION.md](CONFIGURATION.md).
3. **REQ-* anchors** — identifiers that tie repository policies and implementation
   constraints to their source files and validation, such as `REQ-CS-005`,
   `REQ-DEMO-010`, and `REQ-MAKE-001`.

All three layers must remain aligned.

## GitHub Spec Kit baseline

GitHub Spec Kit is the repository workflow for maintaining the baseline and
incremental feature specifications. Read [SPEC-KIT.md](SPEC-KIT.md) for CLI
installation, initialization, and Cursor Agent skills. The current baseline is
[`specs/001-baseline/`](../specs/001-baseline/), including its
[`spec.md`](../specs/001-baseline/spec.md) and
[`code-inventory.md`](../specs/001-baseline/code-inventory.md).

## User stories

| ID | Intent | Docs |
| --- | --- | --- |
| US-01 | Operator creates a locale path | [USAGE.md](USAGE.md) |
| US-02 | Default locale without prefix | [USAGE.md](USAGE.md) |
| US-03 | Locale fallback | [USAGE.md](USAGE.md) |
| US-04 | Clear routing cache | [USAGE.md](USAGE.md) |
| US-05 | SeoKit bridge | [USAGE.md](USAGE.md) |

## Functional scope

See [`specs/001-baseline/spec.md`](../specs/001-baseline/spec.md) for FR-01 … FR-18.

## Validating the functional spec

```bash
make qa
make phpstan
make test-coverage
make -C demo release-check
```

## Requirement identifiers (selected)

| ID | Topic |
| --- | --- |
| REQ-TWIG-001 | Twig overrides |
| REQ-I18N-002 | Seven locales |
| REQ-CS-005 | PHPStan FrankenPHP |
| REQ-DEMO-010 | `FRANKENPHP_MODE` |
| REQ-GIT-001 | No Cursor co-author trailers |
| REQ-TEST-003 | Coverage ≥ 99% |

## Contributor workflow

1. Update the baseline or add a feature spec under `specs/`.
2. Implement and cover with PHPUnit.
3. Keep README / USAGE / CONFIGURATION aligned.
4. Run `make release-check`.

## Relationship to Engram

See [ENGRAM.md](ENGRAM.md) for local memory / MCP notes.

## See also

- [SPEC-KIT.md](SPEC-KIT.md) — GitHub Spec Kit
- [GITHUB_CI.md](GITHUB_CI.md) — CI requirements (REQ-GIT-001)
