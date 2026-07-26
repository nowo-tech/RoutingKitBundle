# Demo applications with FrankenPHP (development and production)

This document describes how the Routing Kit Bundle demo runs under **FrankenPHP** in Docker, and how to reproduce **development** (hot reload / classic mode) and **production** (worker mode) configurations.

## Table of contents

- [Overview](#overview)
- [What the demo includes](#what-the-demo-includes)
- [Development configuration](#development-configuration)
  - [1. Caddyfile (development)](#1-caddyfile-development)
  - [2. PHP configuration (development)](#2-php-configuration-development)
  - [3. Twig configuration (development)](#3-twig-configuration-development)
  - [4. Docker Compose (development)](#4-docker-compose-development)
  - [5. Entrypoint](#5-entrypoint)
  - [6. Start the demo (development)](#6-start-the-demo-development)
- [Production configuration](#production-configuration)
  - [1. Caddyfile (production)](#1-caddyfile-production)
  - [2. PHP / Twig (production)](#2-php--twig-production)
  - [3. Build and run (production)](#3-build-and-run-production)
- [Switching classic vs worker (`FRANKENPHP_MODE`)](#switching-classic-vs-worker-frankenphp_mode)
- [Useful demo URLs](#useful-demo-urls)
- [Troubleshooting](#troubleshooting)
  - [Changes to Twig or PHP do not appear on refresh](#changes-to-twig-or-php-do-not-appear-on-refresh)
  - [Web Profiler or Twig Inspector not visible](#web-profiler-or-twig-inspector-not-visible)
  - [Panel shows UNSAFE or export/import fails](#panel-shows-unsafe-or-exportimport-fails)
  - [Demo does not respond or `make up` times out](#demo-does-not-respond-or-make-up-times-out)
  - [Caddyfile or mode changes have no effect](#caddyfile-or-mode-changes-have-no-effect)

---

## Overview

**The `demo/` folder is not shipped when the bundle is installed** (e.g. via `composer require nowo-tech/routing-kit-bundle`). It is excluded from the Composer package (`archive.exclude` in `composer.json`). The demo exists only in the source repository for development, testing, and documentation.

The demo uses:

- **FrankenPHP** (Caddy + PHP) in a single container (`dunglas/frankenphp:1-php8.5-alpine` for Symfony 8).
- **Docker Compose** with the app and the parent bundle mounted (`../..` → `/var/routing-kit-bundle`).
- **Two Caddyfiles**: `Caddyfile` (worker) and `Caddyfile.dev` (classic / no worker).
- An **entrypoint** that selects the Caddyfile from **`FRANKENPHP_MODE`** (`classic` \| `worker`, default **`worker`** in `.env.example`).

There is one demo: **demo/symfony8**. From the bundle root:

```bash
make -C demo up-symfony8
# or
make -C demo/symfony8 up
```

| Aspect | Development (classic) | Production (worker) |
| --- | --- | --- |
| FrankenPHP worker | Off (`Caddyfile.dev`) | On (`php_server { worker … }`) |
| Twig cache | Off (`config/packages/dev/twig.yaml`) | On (default) |
| OPcache revalidation | Every request (`docker/php-dev.ini`) | Image defaults |
| HTTP cache headers | `no-store` / `no-cache` | Omitted |
| `APP_ENV` / `APP_DEBUG` | `dev` / `1` | `prod` / `0` |

**Port:** `PORT` from `demo/symfony8/.env` (default **8058**). Override in `.env` if needed.

This bundle does **not** run long blocking conversions or third-party HTTP from the demo, so no extra timeout hierarchy beyond normal FrankenPHP/PHP defaults is required.

---

## What the demo includes

Configured for local development and debugging:

- **Symfony Web Profiler** and **DebugBundle** — `dev` / `test`.
- **Twig Inspector** (`nowo-tech/twig-inspector-bundle`) — `dev` / `test`.
- **Routing Kit Bundle** — `all` environments; panel at `/_routing`.

Example `config/bundles.php`:

```php
<?php

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['dev' => true, 'test' => true],
    Nowo\TwigInspectorBundle\NowoTwigInspectorBundle::class => ['dev' => true, 'test' => true],
    Nowo\RoutingKitBundle\NowoRoutingKitBundle::class => ['all' => true],
];
```

Path storage: `var/routing_kit/paths.json` inside the demo app. Import order in `config/routes.yaml`: panel → app controllers → `type: nowo_routing_kit` last.

---

## Development configuration

Goal: changes to PHP, Twig, or config visible on the next refresh (prefer `FRANKENPHP_MODE=classic`).

### 1. Caddyfile (development)

`demo/symfony8/docker/frankenphp/Caddyfile.dev` — plain `php_server` (no worker), cache-busting headers:

```caddyfile
{
	skip_install_trust
}

:80 {
	root * /app/public
	encode zstd br gzip
	header Cache-Control "no-store, no-cache, must-revalidate, max-age=0"
	header Pragma "no-cache"
	php_server
}
```

### 2. PHP configuration (development)

`demo/symfony8/docker/php-dev.ini`:

```ini
opcache.revalidate_freq=0
```

Mounted in Compose as `/usr/local/etc/php/conf.d/99-dev.ini:ro`. Do not use this file in production.

### 3. Twig configuration (development)

`demo/symfony8/config/packages/dev/twig.yaml`:

```yaml
twig:
    cache: false
```

### 4. Docker Compose (development)

Mounts typically include:

- `.:/app`
- `../..:/var/routing-kit-bundle`
- `./docker/frankenphp/Caddyfile.dev:/etc/frankenphp/Caddyfile.dev`
- `./docker/php-dev.ini:/usr/local/etc/php/conf.d/99-dev.ini:ro`

Compose passes `FRANKENPHP_MODE=${FRANKENPHP_MODE:-worker}` and publishes `${PORT:-8058}:80`. DNS (`8.8.8.8` / `8.8.4.4`) is set so Composer can resolve Packagist.

### 5. Entrypoint

`demo/symfony8/docker/entrypoint.sh` waits for Composer’s `vendor/autoload_runtime.php`, then:

- `FRANKENPHP_MODE=classic` → copies `Caddyfile.dev` over the active Caddyfile
- `FRANKENPHP_MODE=worker` → keeps the image default (worker-enabled) Caddyfile

Then starts FrankenPHP.

### 6. Start the demo (development)

```bash
# From bundle root
make -C demo/symfony8 up
# → Demo started at: http://localhost:8058
```

For hot-reload-friendly PHP, set `FRANKENPHP_MODE=classic` in `demo/symfony8/.env` and recreate (see below).

---

## Production configuration

Goal: worker mode and normal caching.

### 1. Caddyfile (production)

`demo/symfony8/docker/frankenphp/Caddyfile`:

```caddyfile
{
	skip_install_trust
}

:80 {
	root * /app/public
	encode zstd br gzip
	php_server {
		worker /app/public/index.php 2
	}
}
```

### 2. PHP / Twig (production)

- Do **not** mount `php-dev.ini`.
- Do **not** set `twig.cache: false` for `prod`.
- Use `APP_ENV=prod` and `APP_DEBUG=0`.

### 3. Build and run (production)

```bash
cd demo/symfony8
# Set APP_ENV=prod APP_DEBUG=0 and FRANKENPHP_MODE=worker in the environment
docker compose build
docker compose up -d
# Prefer: composer install --no-dev && php bin/console cache:warmup --env=prod
```

---

## Switching classic vs worker (`FRANKENPHP_MODE`)

Mode is selected by **`FRANKENPHP_MODE`** (`classic` \| `worker`), not by `APP_ENV`. Default in `.env.example` / Compose is **`worker`**.

| Mode | Effect |
| --- | --- |
| `classic` | Entrypoint copies `Caddyfile.dev` (no worker; better hot-reload). |
| `worker` | Entrypoint keeps the worker-enabled Caddyfile. |

After changing `FRANKENPHP_MODE`, recreate the container so the entrypoint re-runs:

```bash
cd demo/symfony8
docker compose up -d --force-recreate
# or
make -C demo/symfony8 restart
```

---

## Useful demo URLs

| Path | Purpose |
| --- | --- |
| `/` | Home |
| `/about` | Sample `#[Routable]` page |
| `/blog/{slug}` | Sample with required param |
| `/_routing` | Path CRUD panel |
| `/health` | Health check |

---

## Troubleshooting

### Changes to Twig or PHP do not appear on refresh

- Set `FRANKENPHP_MODE=classic` and recreate the container.
- Confirm `config/packages/dev/twig.yaml` has `cache: false` and `php-dev.ini` is mounted.
- Hard-refresh the browser or use a private window.

### Web Profiler or Twig Inspector not visible

- Check `APP_ENV=dev` and `APP_DEBUG=1`.
- Ensure profiler / inspector bundles are enabled for `dev` in `config/bundles.php`.
- Clear cache: `make -C demo/symfony8 cache-clear`.

### Panel shows UNSAFE or export/import fails

- Demo may set `panel.role: null` for local use — do not expose publicly.
- Export/import signing key must be ≥32 characters (`APP_SECRET` / `panel.export_signing_key`).

### Demo does not respond or `make up` times out

- Ensure `PORT` in `.env` is free; check `docker compose logs php`.
- Confirm Composer finished (`vendor/autoload_runtime.php` present).
- Run `make -C demo/symfony8 verify` for an HTTP health check.

### Caddyfile or mode changes have no effect

- FrankenPHP reads the Caddyfile at start — recreate/restart after edits.
- For classic mode, edit `Caddyfile.dev` (the file the entrypoint copies), not only the worker `Caddyfile`.
