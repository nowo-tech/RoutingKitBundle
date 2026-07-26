# Upgrading

## Table of contents

- [To 1.1.4](#to-114)
  - [Install / update](#install--update)
  - [Behaviour / security](#behaviour--security)
  - [Breaking / migration](#breaking--migration)
- [To 1.1.3](#to-113)
- [To 1.1.2](#to-112)
  - [Install / update](#install--update-1)
  - [Behaviour / security](#behaviour--security-1)
  - [Breaking / migration](#breaking--migration-1)
- [To 1.1.1](#to-111)
  - [Install / update](#install--update-2)
  - [Behaviour / security](#behaviour--security-2)
  - [Breaking / migration](#breaking--migration-2)
- [To 1.1.0](#to-110)
  - [Requirements](#requirements)
  - [Install / update](#install--update-3)
  - [Breaking / migration](#breaking--migration-3)
  - [Behaviour notes](#behaviour-notes)
- [To 1.0.3](#to-103)
  - [Requirements](#requirements-1)
  - [Install / update](#install--update-4)
  - [Behavior notes](#behavior-notes)
  - [Breaking changes](#breaking-changes)
- [To 1.0.2](#to-102)
  - [Requirements](#requirements-2)
  - [Install / update](#install--update-5)
  - [Behavior notes](#behavior-notes-1)
  - [Breaking changes](#breaking-changes-1)
- [To 1.0.1](#to-101)
  - [Requirements](#requirements-3)
  - [Install / update](#install--update-6)
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
