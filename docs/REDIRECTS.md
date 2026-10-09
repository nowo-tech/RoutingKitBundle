# Operator URL redirects

Operators send retired public URLs (old site, renamed pages, deleted posts) to a new path or an absolute URL from the panel, without a deploy. Spec: [specs/002-url-redirects/spec.md](../specs/002-url-redirects/spec.md).

## Table of contents

- [Enable](#enable)
- [How a request is handled](#how-a-request-is-handled)
- [Protected paths](#protected-paths)
- [Live pages and slug routes](#live-pages-and-slug-routes)
- [Panel](#panel)
- [Storage](#storage)
- [Hit counting](#hit-counting)
- [Caching](#caching)
- [CSP and templates](#csp-and-templates)
- [FrankenPHP worker mode](#frankenphp-worker-mode)
- [Limitations](#limitations)

## Enable

The feature is **opt-in** (it adds a `kernel.request` listener):

```yaml
nowo_routing_kit:
    url_redirects:
        enabled: true
        # storage: App\Redirect\DoctrineUrlRedirectStorage   # default: JSON file
        # file: '%kernel.project_dir%/var/routing_kit/redirects.json'
        # cache_pool: cache.app      # null = read storage once per request
        # cache_ttl: 3600
        # protected_prefixes: [/admin, /login, …]   # replaces the defaults
        # protected_starts: [/_, /robots.txt, …]    # replaces the defaults
        # hits:
        #     enabled: true
        #     message_bus: messenger.default_bus     # null = synchronous write
```

No new route import is needed: the panel section lives under the existing `@NowoRoutingKitBundle/Resources/config/routes.yaml` import (`{panel.path_prefix}/redirects`). When `url_redirects.enabled` is `false` those routes answer **404**.

> The key is `url_redirects`, not `redirects`: `redirects.*` already configures the canonical / root redirect subscribers.

## How a request is handled

`UrlRedirectSubscriber` listens on `kernel.request` at priority **33** (Symfony's `RouterListener` runs at 32):

1. Main request, `GET` / `HEAD` only.
2. The path is normalized (`UrlRedirect::normalizePath()`): percent-decoded, query / fragment dropped, trailing slash trimmed, **case kept**. `/podolog%C3%ADa/` and `/podología` are the same source.
3. Protected paths are skipped (see below).
4. The enabled-redirect map is looked up (cached).
5. Unsafe stored targets are ignored: an internal-looking target must pass `UrlRedirect::isInternalTarget()` (no `//host`, `/\host`, `%5c`, `%2f`, control characters) and any other target must be an absolute `http(s)://host…` URL. Rows written outside the panel (SQL, restore) cannot turn into open redirects.
6. If a live page now serves the path, the page wins (a landing published after the redirect was saved).
7. Otherwise the visitor gets a `301/302/307/308` to the target. The request query string is appended **only for internal targets without their own query** — it is never forwarded to another host.
8. A hit is recorded.

## Protected paths

`ProtectedPaths` refuses (in the panel) and skips (at request time) back-office, authentication and technical URLs, with or without a leading `/{locale}` segment taken from the bundle's `LocaleProviderInterface`:

| Option | Default | Match |
| --- | --- | --- |
| `protected_prefixes` | `/admin`, `/login`, `/logout`, `/register`, `/reset-password`, `/api`, `/health`, `/build`, `/bundles`, `/media`, `/.well-known` + **`panel.path_prefix`** (always added) | Whole segment: `/admin` covers `/admin` and `/admin/x`, not `/administration` |
| `protected_starts` | `/_`, `/robots.txt`, `/sitemap`, `/favicon` | Raw string prefix: `/_` covers `/_profiler`, `/_wdt`, `/_routing` |

- `/` is always protected. Comparison is case-insensitive.
- `/en/login/check` is protected like `/login/check` when `en` is a configured locale. A bare `/en` is not stripped: locale homes are refused as live pages, and short links such as `/ig` stay usable.
- Setting a list **replaces** its defaults (so a host may redirect e.g. retired `/media/…` URLs). Keep your own back-office and auth prefixes in the list.

## Live pages and slug routes

A source is refused in the panel — and skipped at request time — when the router matches it as a `GET` (`LivePublicPaths`). Slug routes such as `/blog/{slug}` match any slug, so a router match alone would call a **deleted** post live and refuse the redirect that retires it. Register a `LivePathCheckerInterface` for those routes (autoconfigured with the tag `nowo_routing_kit.live_path_checker`):

```php
use Nowo\RoutingKitBundle\Redirect\LivePathCheckerInterface;

final class BlogLivePathChecker implements LivePathCheckerInterface
{
    public function __construct(private readonly PostRepository $posts) {}

    public function isLive(string $route, array $parameters): ?bool
    {
        if (!str_starts_with($route, 'blog_post')) {
            return null; // not mine — ask the next checker
        }

        return $this->posts->findOneBySlug((string) ($parameters['slug'] ?? '')) !== null;
    }
}
```

- `true` = live (refuse / skip the redirect), `false` = content gone (redirect applies), `null` = abstain.
- No checker answers → a router match counts as live (conservative).
- A checker that throws counts as live: a database outage never turns live pages into redirects.

## Panel

`{panel.path_prefix}/redirects` (default `/_routing/redirects`) lists, creates, edits and deletes rows. A "Routes | Redirects" switcher appears on both panel sections when the feature is enabled.

- Access: same guard as the routes panel (`security.access_roles`, `security.access_checker`, `allow_unauthenticated`). Firewall the prefix too.
- Layout: `web_ui.layout_template`, `css_framework`, `icon_set` apply.
- Writes are CSRF-protected (`nowo_routing_kit_url_redirect` for the form, `nowo_routing_kit_url_redirect_delete_{id}` per delete).
- Delete goes through a **confirmation page** (GET shows it, POST deletes) — no inline `confirm()` JavaScript.
- Rules (`UrlRedirectValidator`): source `/…` without spaces, `?`, `#`, `\` (≤255); target internal path or `http(s)://` URL (≤500); status 301/302/307/308; note ≤255; source not protected and not live; target ≠ source (loop); target not another row's source (chain); source unique.
- Audit: `createdAt` / `updatedAt` and `createdBy` / `updatedBy` (the panel user's **identifier string**, e.g. e-mail — no foreign key to a User entity).
- Translations: `NowoRoutingKitBundle` domain (`redirects.*`, `nav.*`) in en, es, fr, de, it, pt, nl.

## Storage

Default: `FilesystemUrlRedirectStorage`, a JSON file (`url_redirects.file`) with the same locking model as the path storage (shared lock for reads, exclusive lock + atomic rename for writes, corrupt JSON fails closed). Keep it outside the web root.

For a database, implement `Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface` and set `url_redirects.storage` to the service id. The bundle ships **no Doctrine entity** (it has no Doctrine dependency); the host owns the table and its migration. Example with Doctrine ORM:

```php
#[ORM\Entity]
#[ORM\Table(name: 'url_redirect')]
#[ORM\UniqueConstraint(name: 'uniq_url_redirect_source', columns: ['source_path'])]
class UrlRedirectRow
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] public ?int $id = null;
    #[ORM\Column(length: 255)] public string $sourcePath = '';
    #[ORM\Column(length: 500)] public string $targetUrl = '';
    #[ORM\Column] public int $statusCode = 301;
    #[ORM\Column] public bool $enabled = true;
    #[ORM\Column] public int $hits = 0;
    #[ORM\Column(nullable: true)] public ?\DateTimeImmutable $lastHitAt = null;
    #[ORM\Column(length: 255)] public string $note = '';
    #[ORM\Column(nullable: true)] public ?\DateTimeImmutable $createdAt = null;
    #[ORM\Column(nullable: true)] public ?\DateTimeImmutable $updatedAt = null;
    #[ORM\Column(length: 180, nullable: true)] public ?string $createdBy = null;
    #[ORM\Column(length: 180, nullable: true)] public ?string $updatedBy = null;
}
```

```php
final class DoctrineUrlRedirectStorage implements UrlRedirectStorageInterface
{
    // all(): ORDER BY sourcePath; findById(): find((int) $id); findBySource(): findOneBy(['sourcePath' => …]);
    // enabledMap(): SELECT sourcePath, targetUrl, statusCode WHERE enabled = true (array hydration);
    // save(): map model → row, flush, $redirect->setId((string) $row->id); throw \RuntimeException on duplicate source;
    // delete(): remove + flush; recordHit(): one UPDATE … SET hits = hits + 1, last_hit_at = :at WHERE source_path = :path
}
```

Then generate and review the migration in the host (`php bin/console doctrine:migrations:diff`). On MySQL, give `source_path` a binary / case-sensitive collation (`utf8mb4_bin`) so `/Old` and `/old` stay distinct sources, as they are at request time.

## Hit counting

`hits.enabled: true` (default) counts `hits` and `lastHitAt`. Without a bus each hit writes to storage synchronously — with the JSON storage that rewrites the file under an exclusive lock, so bots hammering a legacy URL serialize on it. For traffic, set `hits.message_bus` (e.g. `messenger.default_bus`) and route the message to an async transport:

```yaml
framework:
    messenger:
        routing:
            Nowo\RoutingKitBundle\Redirect\Message\RecordRedirectHit: async
```

The handler (`RecordRedirectHitHandler`) is registered only when a bus is configured. Unrouted messages are handled synchronously. Hit failures are logged, never surfaced to the visitor. Set `hits.enabled: false` to disable counting.

## Caching

`UrlRedirectMatcher` keeps the enabled-redirect map in `cache_pool` (default `cache.app`) for `cache_ttl` seconds and memoizes it per request. Panel writes invalidate it. Writes outside the panel (SQL, backup restore) are picked up when the TTL expires, or call `UrlRedirectMatcher::invalidate()`. Cache or storage failures degrade to "no redirects" with a log warning.

## CSP and templates

The bundled redirect templates contain no inline `<script>` or `<style>`. If you override them (`templates/bundles/NowoRoutingKitBundle/panel/redirects/*.html.twig`) and need inline code, use the request attribute `csp_nonce`:

```twig
<script nonce="{{ app.request.attributes.get('csp_nonce') }}">…</script>
```

## FrankenPHP worker mode

`UrlRedirectMatcher` implements `ResetInterface` (autoconfigured `kernel.reset`); its per-request memo is also dropped on `invalidate()`. The router context method changed while checking live paths is restored in a `finally` block.

## Limitations

- Source matching is exact on the normalized path (no wildcards / regex, no host-specific redirects).
- Live-page checks use the router's request context before `RouterListener` refreshes it: routes with `host` / `schemes` conditions may be matched against the previous request's context.
- Redirect chains are refused when created through the panel; rows written directly in storage are not re-validated (only target safety is enforced at request time).
