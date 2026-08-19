# Upgrading

## To 1.4.2

No application upgrade steps.

```bash
composer update nowo-tech/routing-kit-bundle
```

## To 1.4.1

No application upgrade steps. **Demos only:** Hot Reload Bundle `^1.4` (FrankenPHP Mercure/`hot_reload`, `dev`/`test`).

```bash
composer update nowo-tech/routing-kit-bundle
php bin/console cache:clear
```

## To 1.4.0

From **1.3.1** — panel index actions (export, clear-cache, import, delete) are Symfony forms (`form_*`).

```bash
composer update nowo-tech/routing-kit-bundle
php bin/console cache:clear
```

### Host Twig overrides

If you override `panel/index.html.twig`, stop using raw HTML `<form>` / Twig `constant(...::CSRF_TOKEN_ID)` / a bare `csrf_token` string. Use the controller context instead:

| Action | Context |
| --- | --- |
| Export | `export_form` |
| Clear cache | `clear_cache_form` |
| Import | `import_form` |
| Delete (per row) | `delete_forms[row.id]` |

`csrf_token` is no longer passed to the index template.

## To 1.3.1

From **1.3.0** — maintainer/CI fix only (`composer.lock` content-hash). No host migration.

```bash
composer update nowo-tech/routing-kit-bundle
```

## To 1.3.0

From **1.2.0** — FormKit panel form + UiKit pagination.

```bash
composer update nowo-tech/routing-kit-bundle
php bin/console cache:clear
php bin/console assets:install --symlink --relative public
```

Requires `nowo-tech/form-kit-bundle` `^2.2` and `symfony/form`. Panel create/edit uses a Symfony form (`RoutePathDefinitionType` + FormKit profile `routing_kit`). Pagination on the panel index uses UiKit `_pagination`.

## To 1.2.0

From **1.1.9** — UiKit, Twig Extra (REQ-TWIG-004), Twig-CS-Fixer.

```bash
composer update nowo-tech/routing-kit-bundle
php bin/console cache:clear
```

### UiKit composition

Panel `nowo-ui.css` now comes from **UiKitBundle** (`asset('css/nowo-ui.css', 'nowo_ui_kit')`). Hosts that linked `nowo_routing_kit` for that stylesheet must switch package (or rely on `panel/base.html.twig`). Require `nowo-tech/ui-kit-bundle` `^1.4` and run `assets:install`. Panel templates import `@NowoUiKitBundle/macros/ui.html.twig` for primary toolbar actions (`ui.btn` on index).

### Twig Extra Bundle (REQ-TWIG-004)

Hosts that render this bundle's Twig templates must install:

```bash
composer require twig/extra-bundle twig/string-extra
```

and enable `Twig\Extra\TwigExtraBundle\TwigExtraBundle`. Flex recipes usually register it automatically.

### Twig-CS-Fixer (maintainers)

Package maintainers: `composer twig:lint` / `composer twig:fix` use `.twig-cs-fixer.php` over `src/` (and `templates/` when present).


## Table of contents

- [To 1.4.2](#to-142)
- [To 1.4.1](#to-141)
- [To 1.4.0](#to-140)
- [To 1.3.1](#to-131)
- [To 1.3.0](#to-130)
- [To 1.2.0](#to-120)
- [To 1.1.9](#to-119)
- [To 1.1.8](#to-118)
- [To 1.1.7](#to-117)
- [To 1.1.6](#to-116)
- [To 1.1.5](#to-115)
  - [Install / update](#install--update)
  - [Behaviour / security](#behaviour--security)
  - [Breaking / migration](#breaking--migration)
- [To 1.1.4](#to-114)
  - [Install / update](#install--update-1)
  - [Behaviour / security](#behaviour--security-1)
  - [Breaking / migration](#breaking--migration-1)
- [To 1.1.3](#to-113)
- [To 1.1.2](#to-112)
  - [Install / update](#install--update-2)
  - [Behaviour / security](#behaviour--security-2)
  - [Breaking / migration](#breaking--migration-2)
- [To 1.1.1](#to-111)
  - [Install / update](#install--update-3)
  - [Behaviour / security](#behaviour--security-3)
  - [Breaking / migration](#breaking--migration-3)
- [To 1.1.0](#to-110)
  - [Requirements](#requirements)
  - [Install / update](#install--update-4)
  - [Breaking / migration](#breaking--migration-4)
  - [Behaviour notes](#behaviour-notes)
- [To 1.0.3](#to-103)
  - [Requirements](#requirements-1)
  - [Install / update](#install--update-5)
  - [Behavior notes](#behavior-notes)
  - [Breaking changes](#breaking-changes)
- [To 1.0.2](#to-102)
  - [Requirements](#requirements-2)
  - [Install / update](#install--update-6)
  - [Behavior notes](#behavior-notes-1)
  - [Breaking changes](#breaking-changes-1)
- [To 1.0.1](#to-101)
  - [Requirements](#requirements-3)
  - [Install / update](#install--update-7)
  - [Breaking changes](#breaking-changes-2)
  - [Behaviour notes (non-breaking)](#behaviour-notes-non-breaking)
- [To 1.0.0](#to-100)
  - [Requirements](#requirements-4)
  - [Install](#install)
  - [Routes (order matters)](#routes-order-matters)
  - [Suggested first-time config](#suggested-first-time-config)
  - [Security](#security)
  - [SeoKit](#seokit)
  - [Storage](#storage)

## To 1.1.9

Panel access aligns with REQ-UI-002 (`access_roles` / `access_checker` / `allow_unauthenticated`). Defaults keep `[ROLE_ADMIN]` behaviour.

### Install / update

```bash
composer update nowo-tech/routing-kit-bundle
php bin/console cache:clear
```

### Behaviour / security

- Default gate uses `ConfigurableRoutingKitAccessChecker` + `security.access_roles`.
- Custom checker: set `security.access_checker` to a service implementing `RoutingKitAccessCheckerInterface`.
- `allow_unauthenticated: true` registers `AllowAllRoutingKitAccessChecker` (demos only).
- With the panel enabled and `allow_unauthenticated: false`, the host must register SecurityBundle (compile-time `LogicException` otherwise).
- `PanelAccessGuardPass` wires `security.token_storage` at compile time (unchanged need for a firewall on the panel prefix).

### Breaking / migration

No YAML key renames. If you subclassed or constructed `PanelAccessGuard` manually with `(AuthorizationChecker, roles)`, switch to the checker + token storage constructor (apps using DI only need no code changes).

## To 1.1.8

Bugfix: `PanelAccessGuard` now receives `security.authorization_checker` via `PanelAccessGuardPass` (compiler pass). Extension-time wiring often missed SecurityBundle’s service and denied `/_routing` when `security.access_roles` was non-empty.

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.1.8
php bin/console cache:clear
```

No YAML or public API changes. Clear the container cache so the new compiler pass runs.

Hosts that use the `nowo_routing_kit` asset package need `symfony/asset` (usually already present via FrameworkBundle). The bundle suggests it; the Symfony 8 demo requires it explicitly.

---

## To 1.1.7

Panel CSS extracted to the `nowo_routing_kit` asset package (`css/nowo-ui.css`) for `css_framework: custom` hosts (e.g. Tailwind). See [CONFIGURATION.md](CONFIGURATION.md) and [INSTALLATION.md](INSTALLATION.md) **Assets**.

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.1.7
php bin/console assets:install
php bin/console cache:clear
```

Requires `symfony/asset` for the named package registration (see **To 1.1.8** note).

Hosts using `web_ui.css_framework: custom` should ensure the host layout exposes a `stylesheets` block so `@NowoRoutingKitBundle/panel/base.html.twig` can inject `nowo-ui.css` via `{{ parent() }}`.

---

## To 1.1.6

Documentation polish only (README section order, USAGE Twig override / `web_ui` guidance). **No config or API migration.**

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.1.6
php bin/console cache:clear
```

Hosts that already use `web_ui.layout_template` / `security.access_roles` from **1.1.5** need no further changes.

---

## To 1.1.5

Compliance remedia (2026-07-29): open-PR gate, zero direct deprecations, attribute panel routes, UI/security contract, list pagination.

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.1.5
php bin/console cache:clear
```

### Behaviour / security

- Panel routes use PHP `#[Route]` attributes (imported with `panel.path_prefix`).
- Canonical private access: `security.access_roles` (+ optional `allow_unauthenticated` for demos). `panel.role` remains a BC alias.
- Panel look-and-feel: `web_ui.layout_template` / `css_framework` / `icon_set` + `nowo-ui-*` markup.
- Index list paginates with `panel.list_page_size` (default 50); storage still hard-capped by `panel.max_definitions`.

### Breaking / migration

| Topic | Before | After |
| --- | --- | --- |
| Preferred panel ACL | `panel.role` | `security.access_roles` (`panel.role` still works) |
| Demo open panel | `panel.role: null` | same, or `security.access_roles: []` / `allow_unauthenticated: true` |
| Host layout | Fixed bundle layout | Set `web_ui.layout_template` to the project layout |

---

## To 1.1.4

Security hardening follow-up (path safety, loader defense, panel ids, import limits) plus FrankenPHP / TOC docs.

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.1.4
php bin/console cache:clear
```

### Behaviour / security

- Stored / redirect paths reject more open-redirect and injection shapes (C0/DEL controls, encoded `\` / null / CR-LF, double-encoded `//`, `..` segments).
- `redirects.root_home_path` must already be a safe absolute public path at compile time.
- With `allow_controller_override: true`, `DbRouteLoader` still applies only the `#[Routable]` discovery controller for that route (tampered storage cannot inject another controller).
- Definition `id` values must match `[A-Za-z0-9_.-]+` (panel routes and saves/imports).
- Panel import raw JSON is capped at **1 MiB**.

### Breaking / migration

| Topic | Before (1.1.3) | 1.1.4 |
| --- | --- | --- |
| Path / `root_home_path` edge cases | Some encoded / control / `..` shapes accepted | Rejected by `SafePublicPath` / config validation |
| Stored controller override (override on) | Loader could honor any stored string | Only discovery allowlist for that route name |
| Definition ids | Mostly unrestricted (`[^/]+` in panel routes) | `[A-Za-z0-9_.-]+` only |
| Huge import payloads | Limited only by `max_definitions` after decode | Rejected at **1 MiB** before decode |

Default `uniqid('rk_', true)` ids remain valid. Re-save or clean any hand-edited `paths.json` rows with unsafe paths or exotic ids before upgrade.

---

## To 1.1.3

Documentation alignment with 1.1.2 behaviour. No code or config changes.

```bash
composer require nowo-tech/routing-kit-bundle:^1.1.3
```

---

## To 1.1.2

Storage / HMAC / demo hardening follow-up.

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.1.2
php bin/console cache:clear
```

### Behaviour / security

- Corrupt `paths.json` throws instead of returning an empty set (prevents accidental wipe).
- Filesystem storage uses an exclusive lock file (`paths.json.lock`) during mutations.
- Signing keys shorter than **32** characters are rejected (config + runtime). Strengthen `kernel.secret` or set `panel.export_signing_key`.
- UI warning when `panel.role` is null.

### Breaking / migration

| Topic | Before (1.1.1) | 1.1.2 |
| --- | --- | --- |
| Corrupt `paths.json` | Loaded as empty → next save could wipe | Throws; fix or restore the file |
| Signing key | Any length | ≥ **32** characters |
| Concurrent writes | Last-write-wins without lock | Exclusive flock on `.lock` |
| `panel.role: null` | Silent | Panel (+ demo) shows **UNSAFE** banner |

Ensure `APP_SECRET` / `panel.export_signing_key` is at least 32 characters before using export/import. Backup `paths.json` before upgrade if it might be corrupt.

---

## To 1.1.1

Import/export hardening follow-up to 1.1.0.

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.1.1
php bin/console cache:clear
```

### Behaviour / security

- Signed **import** now runs through `RoutePathManager` (same allowlists, conflicts, and `max_definitions` as panel saves). Invalid rows fail the whole import.
- With `allow_controller_override: false` (default), imported `controller` fields are stripped and the DB loader ignores any leftover stored override.
- **Export** is POST + CSRF only (update any bookmarks/scripts that used GET `/_routing/export`).
- Invalid `panel.path_prefix` values (e.g. `javascript:…`) are rejected at config compile time.
- Conflict detection covers default-locale fallback paths, trailing-slash variants, and disabled rows.

### Breaking / migration

| Topic | Before (1.1.0) | 1.1.1 |
| --- | --- | --- |
| Export | GET or POST | **POST + CSRF** only |
| Custom storage | `save` / `delete` | Must implement `replaceAll(array $definitions): array` |
| `path_prefix` | Any non-empty scalar | Must match `^/[A-Za-z0-9/_-]+$` |
| Import | HMAC only; wrote storage directly | HMAC + full manager validation |

No YAML key renames. Prefer a dedicated `panel.export_signing_key` over a weak `kernel.secret`.

---

## To 1.1.0

Security hardening release: CSRF fail-closed, panel role gate, allowlists, path safety, trailing-slash fix, and signed export/import.

### Requirements

Same PHP/Symfony ranges as 1.0.x, plus **`symfony/security-csrf`** (now required).

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.1
php bin/console cache:clear
```

### Breaking / migration

| Topic | Before | 1.1.0 |
| --- | --- | --- |
| CSRF | Optional (fail-open without manager) | Required; invalid token rejected |
| `panel.role` | n/a | Default `ROLE_ADMIN` (needs Security Bundle checker, or set `null`) |
| Controller override | Free-form text field | Off by default; allowlist only when enabled |
| `route_name` / `locale` | Weakly validated | Must be `#[Routable]` + configured locale |
| Paths | Only “starts with `/`” | Rejects `//`, schemes, control chars |

Recommended app security:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/_routing, roles: ROLE_ADMIN }

# config/packages/nowo_routing_kit.yaml
nowo_routing_kit:
    panel:
        role: ROLE_ADMIN
        allow_controller_override: false
```

Demo apps without Security Bundle should set `panel.role: null` **and** keep the panel off public networks.

### Behaviour notes

- Export/import uses HMAC (`panel.export_signing_key` or `kernel.secret`).
- `enabled: false` fully disables panel + loader + redirect subscribers.

---

## To 1.0.3

Patch release: restores Symfony 8 Composer constraints again (narrowed on `main` after `v1.0.2` by the CI code-style job) and refreshes security / routing docs.

### Requirements

Same as 1.0.2:

- **PHP** `>=8.2` and `<8.6`. Symfony **8.x** requires **PHP 8.4+**.
- **Symfony** `^7.4 || ^8.0` (CI minors: **7.4**, **8.0**, **8.1**).
- **Twig** for the routing CRUD panel templates.

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.0.3
php bin/console cache:clear
```

If Composer previously resolved only Symfony 7 packages because of the narrowed constraints on `main` between `v1.0.2` and this tag, re-run update so Symfony 8 apps pick up `|| ^8.0`.

### Behavior notes

- Unchanged from 1.0.2: routes are `{name}.{locale}` with `_canonical_route`.
- Protect `/_routing` (or `panel.path_prefix`) with a firewall — the bundle does not ship authentication. See [SECURITY.md](SECURITY.md).

### Breaking changes

None.

---

## To 1.0.2

Patch release: restores Symfony 8 Composer constraints (broken in 1.0.1) and fixes locale URL generation via `_canonical_route`.

### Requirements

Same as 1.0.0 / 1.0.1:

- **PHP** `>=8.2` and `<8.6`. Symfony **8.x** requires **PHP 8.4+**.
- **Symfony** `^7.4 || ^8.0` (CI minors: **7.4**, **8.0**, **8.1**).
- **Twig** for the routing CRUD panel templates.

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.0.2
php bin/console cache:clear
```

### Behavior notes

- Loaded routes are named `{route}.{locale}` with `_canonical_route` set. Prefer `path('app_home', { _locale: 'en' })` / `generate('app_home', ['_locale' => 'en'])`.
- With `register_unprefixed_default: true`, the default locale has no `/{locale}` prefix; other locales keep the prefix.

### Breaking changes

None beyond 1.0.1 (`#[Routable]` without `label`).

---

## To 1.0.1

Patch release: the CRUD panel lists routes by **`name`** only; the optional `label` argument on `#[Routable]` is removed.

### Requirements

Same as 1.0.0:

- **PHP** `>=8.2` and `<8.6`. Symfony **8.x** requires **PHP 8.4+**.
- **Symfony** `^7.4 || ^8.0` (CI minors: **7.4**, **8.0**, **8.1**).
- **Twig** for the routing CRUD panel templates.

### Install / update

```bash
composer require nowo-tech/routing-kit-bundle:^1.0
php bin/console cache:clear
```

### Breaking changes

| Topic | Before (1.0.0) | 1.0.1 |
| --- | --- | --- |
| `#[Routable]` | Optional `label: '…'` for panel display | Constructor is `name` + `params` only |
| Panel `<select>` | Could show `label` when set | Always shows `route_name` |

If you used `label:`, drop it:

```php
// Before
#[Routable(name: 'app_about', label: 'About')]

// After
#[Routable(name: 'app_about')]
```

Leaving `label:` causes a PHP error (`Unknown named parameter $label`).

### Behaviour notes (non-breaking)

Storage rows, loader behaviour, config keys, and SeoKit bridge are unchanged.

---

## To 1.0.0

First public release. No prior Packagist versions — install fresh.

### Requirements

- **PHP** `>=8.2` and `<8.6`. Symfony **8.x** requires **PHP 8.4+**.
- **Symfony** `^7.4 || ^8.0` (CI minors: **7.4**, **8.0**, **8.1**).
- **Twig** for the routing CRUD panel templates.

### Install

```bash
composer require nowo-tech/routing-kit-bundle:^1.0
```

With Flex, the recipe registers the bundle and copies config. Without Flex, see [INSTALLATION.md](INSTALLATION.md).

### Routes (order matters)

```yaml
# config/routes.yaml
nowo_routing_kit_panel:
    resource: '@NowoRoutingKitBundle/Resources/config/routes.yaml'

# … your application routes …

nowo_routing_kit_db:
    resource: .
    type: nowo_routing_kit
```

Import the DB loader **last** so RoutingKit locale routes (`{name}.{locale}` with `_canonical_route`) are registered after attribute routes.

### Suggested first-time config

```yaml
nowo_routing_kit:
    default_locale: en
    locales: [en, es]
    panel:
        path_prefix: /_routing
    redirects:
        canonical_enabled: true
        root_enabled: false
    seo_kit_bridge: true
```

Mark offerable controllers with `#[Routable]` / `RouteParam`, then open `/_routing` to create path rows.

### Security

Protect `/_routing` (or your `panel.path_prefix`) with a Symfony firewall — the bundle does not ship authentication. See [SECURITY.md](SECURITY.md).

### SeoKit

Requires a SeoKit build that exposes `SeoPathBuilderInterface`. Keep `default_locale` / `locales` aligned between both bundles when `seo_kit_bridge: true`.

### Storage

Default path file: `%kernel.project_dir%/var/routing_kit/paths.json`. Ensure `var/` is not web-accessible. Swap storage via `storage.path_storage` / `LocaleProviderInterface` as needed.
