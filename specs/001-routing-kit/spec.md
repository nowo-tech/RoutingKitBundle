# RoutingKitBundle — Product Spec (001)

## Goal

Provide DB-driven (pluggable storage) locale-aware public paths for Symfony applications, with:

- Canonical path definition always based on `/{_locale}/…`
- Default locale published **without** `{_locale}` when configured
- Dual access (`/foo` and `/{locale}/foo`) with **admin-defined canonical**
- CRUD Twig panel + automatic and manual route-cache invalidation
- `#[Routable]` discovery for offerable controllers (name + required params + constraints)
- Compatibility with **SeoKitBundle** (paths feed canonical / hreflang)

## Non-goals (v1)

- Multisite / host-based routing
- Full CMS page content
- Replacing SeoKit metadata (title, OG, etc.)

## Domain model

### Row: `(routeName, locale)`

| Field | Description |
| --- | --- |
| `routeName` | Symfony route name (matches `#[Routable(name: …)]`) |
| `locale` | Locale code (`es`, `en`, …) |
| `path` | Path **after** locale prefix, always absolute from `/` (e.g. `/about`, `/blog/{slug}`). Canonical definition is conceptually `/{_locale}` + `path`. |
| `controller` | Optional FQCN::method override; default from discovery |
| `canonicalStyle` | `without_prefix` \| `with_prefix` — which public URL is canonical for this locale |
| `trailingSlash` | `omit` \| `keep` \| `redirect_to_omit` \| `redirect_to_keep` |
| `aliasMode` | `redirect` (301/302 to canonical) \| `alias` (both 200) |
| `enabled` | Soft disable |

### Fallback

If locale `X` has no row for `routeName`, use the **default locale** row for that `routeName`.

### Locales

- YAML config list + `default_locale`, **or**
- Replaceable `LocaleProviderInterface` (e.g. load from DB)

## Routing behaviour

1. Loader type `nowo_routing_kit` builds a `RouteCollection` that **overwrites** same-named app routes when imported **after** them.
2. For each enabled definition (with fallback), register:
   - Prefixed route: `/{_locale}` + path (requirements `_locale` ∈ configured locales)
   - Unprefixed route for **default locale** when style allows: `path` with `defaults._locale = default`
3. `CanonicalRedirectSubscriber`: if `aliasMode=redirect` and request path is the non-canonical twin → redirect to canonical.
4. `RootRedirectSubscriber` (optional): `/` → default-locale home (prefixed or not per config).

## Attribute

```php
#[Routable(
    name: 'app_about',
    params: [
        new RouteParam('slug', required: true, requirement: '[a-z0-9-]+'),
    ],
)]
```

Panel only offers controllers/methods tagged with `#[Routable]`. CRUD validates path placeholders against declared params.

## Cache

- On create/update/delete: `RouteCacheInvalidator` clears router cache (and optional HttpCache tags).
- Panel button: “Clear routing cache”.

## SeoKit bridge

When `nowo-tech/seo-kit-bundle` is present:

- Decorate / feed path resolution so `pagePath(route, locale)` returns RoutingKit public **canonical** path for that locale.
- Keep `default_locale` / `locales` aligned (document shared config or bridge mapping).

## Storage

- `RoutePathStorageInterface`
- Default: filesystem JSON (`var/routing_kit/paths.json`) for zero-Doctrine apps/demos
- Apps may bind a Doctrine implementation

## Panel

Twig CRUD under configurable prefix (default `/_routing`):

- List / create / edit / delete path rows
- Pick routable target from discovery
- Set canonical style, trailing slash, alias mode
- Clear cache button
