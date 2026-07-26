# Upgrading

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
