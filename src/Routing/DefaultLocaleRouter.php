<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Routing;

use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

use function str_ends_with;

/**
 * The default locale never appears in a URL (`nowo_routing_kit.default_locale_unprefixed`).
 *
 * Decorates `router.default`: generating a route for the default locale (explicit `_locale`, or the one the request
 * context carries) uses its bare twin `<route><twin_suffix>` (default `_unlocalized`, the AuthKit / SiteBackup
 * convention: `nowo_auth_kit_login` → `nowo_auth_kit_login_unlocalized` = `/login`) when that route exists. Every link,
 * redirect, form action and e-mail URL follows, whoever generates it (Twig, controllers, other bundles). Routes without
 * a twin, other locales and twin names themselves are generated unchanged. RoutingKit's own stored routes already use
 * the unprefixed path for the default locale with `register_unprefixed_default`.
 *
 * The "has no twin" memo only depends on the compiled route set; it is cleared with the router state by
 * {@see RouteCacheInvalidator} (route-table changes in long-running workers), so it is worker-safe.
 */
final class DefaultLocaleRouter implements RouterInterface, RequestMatcherInterface, WarmableInterface
{
    public const DEFAULT_TWIN_SUFFIX = '_unlocalized';

    /** @var array<string, true> twin route names that do not exist */
    private array $missingTwins = []; // @igor-ignore - deterministic memo of the compiled route set, cleared by RouteCacheInvalidator with the router state

    public function __construct(
        private readonly RouterInterface&RequestMatcherInterface&WarmableInterface $inner,
        private readonly LocaleProviderInterface $locales,
        private readonly string $twinSuffix = self::DEFAULT_TWIN_SUFFIX,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        $locale = $parameters['_locale'] ?? $this->inner->getContext()->getParameter('_locale');
        if ($locale !== $this->locales->getDefaultLocale() || str_ends_with($name, $this->twinSuffix)) {
            return $this->inner->generate($name, $parameters, $referenceType);
        }

        $twin = $name . $this->twinSuffix;
        if (!isset($this->missingTwins[$twin])) {
            $bare = $parameters;
            unset($bare['_locale']);
            try {
                return $this->inner->generate($twin, $bare, $referenceType);
            } catch (RouteNotFoundException) {
                // @igor-ignore - deterministic memo of the compiled route set
                $this->missingTwins[$twin] = true;
            }
        }

        return $this->inner->generate($name, $parameters, $referenceType);
    }

    /**
     * The decorated router (RouteCacheInvalidator resets its memoized state).
     */
    public function getInnerRouter(): RouterInterface&RequestMatcherInterface&WarmableInterface
    {
        return $this->inner;
    }

    /**
     * Forget the "has no twin" memo (the route set changed).
     */
    public function clearTwinMemo(): void
    {
        // @igor-ignore - deterministic memo of the compiled route set
        $this->missingTwins = [];
    }

    public function setContext(RequestContext $context): void
    {
        $this->inner->setContext($context);
    }

    public function getContext(): RequestContext
    {
        return $this->inner->getContext();
    }

    public function getRouteCollection(): RouteCollection
    {
        return $this->inner->getRouteCollection();
    }

    /**
     * @return array<string, mixed>
     */
    public function match(string $pathinfo): array
    {
        return $this->inner->match($pathinfo);
    }

    /**
     * @return array<string, mixed>
     */
    public function matchRequest(Request $request): array
    {
        return $this->inner->matchRequest($request);
    }

    /**
     * @return string[]
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        return $this->inner->warmUp($cacheDir, $buildDir);
    }
}
