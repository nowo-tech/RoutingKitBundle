# Routing Kit Bundle

[![CI](https://github.com/nowo-tech/RoutingKitBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/RoutingKitBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/routing-kit-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/routing-kit-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/routing-kit-bundle.svg)](https://packagist.org/packages/nowo-tech/routing-kit-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-7.4%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/RoutingKitBundle.svg?style=social&label=Star)](https://github.com/nowo-tech/RoutingKitBundle) [![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** [Install from Packagist](https://packagist.org/packages/nowo-tech/routing-kit-bundle) · Give it a **star** on [GitHub](https://github.com/nowo-tech/RoutingKitBundle) so more developers can find it.

**Routing Kit Bundle** — DB-driven (pluggable storage) **locale paths** for Symfony: dual `/foo` + `/{locale}/foo` access, admin-defined **canonical** URLs, Twig CRUD panel, route-cache invalidation, and **SeoKitBundle** path bridge. Tested on Symfony **7.4**, **8.0**, and **8.1** · PHP 8.2+ (Symfony 8.x requires PHP 8.4+).

![FrankenPHP Friendly Worker Mode](docs/images/frankenphp-friendly.png)

This bundle is **FrankenPHP worker mode friendly**.

## Features

- **Storage** — One row per `(routeName, locale)`; filesystem JSON by default; swap via `RoutePathStorageInterface`.
- **Locales** — YAML `default_locale` + `locales`, or a custom `LocaleProviderInterface`.
- **Canonical definition** — Paths stored without `{_locale}`; the loader always builds `/{_locale}/…`.
- **Styles** — `canonical_style`, trailing slash, alias mode (`redirect` / `alias`).
- **Fallback** — Missing locale row → default-locale path for that route name.
- **`#[Routable]`** — Controllers offered in the CRUD; params + constraints validated on save.
- **Loader** — `type: nowo_routing_kit` overwrites same-named app routes when imported last.
- **Panel** — Twig CRUD under `/_routing` + clear routing cache (auto after save/delete).
- **SeoKit** — Optional bridge decorates `SeoPathBuilderInterface` for canonical/hreflang paths.

## Installation

```bash
composer require nowo-tech/routing-kit-bundle
```

With **Symfony Flex**, the recipe registers the bundle and adds config (see `.symfony/recipe`). Without Flex, see [docs/INSTALLATION.md](docs/INSTALLATION.md).

```yaml
# config/routes.yaml — panel, then app routes, then DB loader LAST
nowo_routing_kit_panel:
    resource: '@NowoRoutingKitBundle/Resources/config/routes.yaml'

# … application routes …

nowo_routing_kit_db:
    resource: .
    type: nowo_routing_kit
```

## Configuration

```yaml
nowo_routing_kit:
    default_locale: en
    locales: [en, es]
    panel:
        path_prefix: /_routing
    redirects:
        canonical_enabled: true
    seo_kit_bridge: true
```

## Usage

```php
use Nowo\RoutingKitBundle\Attribute\Routable;
use Nowo\RoutingKitBundle\Attribute\RouteParam;

#[Routable(name: 'app_about')]
public function about(): Response { … }

#[Routable(name: 'app_blog_show', params: [
    new RouteParam('slug', required: true, requirement: '[a-z0-9-]+'),
])]
public function show(string $slug): Response { … }
```

Open `/_routing` to manage paths. See [docs/USAGE.md](docs/USAGE.md).

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)

### Additional documentation

- [Demo (FrankenPHP)](docs/DEMO-FRANKENPHP.md)
- [GitHub Actions CI requirements](docs/GITHUB_CI.md)

## Requirements

- PHP `>=8.2` (<8.6); **Symfony 8.0** and **8.1** require **PHP 8.4+**
- Symfony **7.4**, **8.0**, or **8.1**
- Twig for the CRUD panel templates

## Development

```bash
make up
make install
make test
make cs-check
make phpstan
make release-check
```

Install git hooks once per clone: `make setup-hooks` (REQ-GIT-001).

### Demos

```bash
make -C demo up-symfony8
```

Open http://localhost:8058 — panel at `/_routing`. FrankenPHP mode is controlled by `FRANKENPHP_MODE` (`classic` \| `worker`, default **worker**) in the demo `.env`.

## Tests and coverage

PHPUnit with **100%** line coverage of production PHP under `src/` (optional SeoKit bridge files excluded when SeoKit is not a require-dev dependency):

```bash
make test-coverage
make coverage-check
```

## License

MIT
