# Upgrading

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

Import the DB loader **last** so stored paths overwrite same-named app routes.

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

Protect `/_routing` (or your `panel.path_prefix`) with a Symfony firewall — the bundle does not ship authentication.

### SeoKit

Requires a SeoKit build that exposes `SeoPathBuilderInterface`. Keep `default_locale` / `locales` aligned between both bundles when `seo_kit_bridge: true`.

### Storage

Default path file: `%kernel.project_dir%/var/routing_kit/paths.json`. Ensure `var/` is not web-accessible. Swap storage via `storage.path_storage` / `LocaleProviderInterface` as needed.
