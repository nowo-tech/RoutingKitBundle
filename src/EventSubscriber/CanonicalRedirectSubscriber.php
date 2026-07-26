<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\EventSubscriber;

use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Model\TrailingSlashStyle;
use Nowo\RoutingKitBundle\Routing\PublicPathResolver;
use Nowo\RoutingKitBundle\Routing\SafePublicPath;
use Nowo\RoutingKitBundle\Storage\RoutePathStorageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function rtrim;

/**
 * Redirects non-canonical locale/path twins and trailing-slash variants when configured.
 */
final class CanonicalRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RoutePathStorageInterface $storage,
        private readonly PublicPathResolver $paths,
        private readonly LocaleProviderInterface $locales,
        private readonly bool $enabled = true,
        private readonly int $redirectStatus = 301,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 33],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        $request  = $event->getRequest();
        $pathInfo = $request->getPathInfo();

        foreach ($this->storage->all() as $stored) {
            if (!$stored->enabled) {
                continue;
            }

            foreach ($this->locales->getLocales() as $locale) {
                $definition = $this->paths->resolveDefinition($stored->routeName, $locale);
                if (!$definition instanceof RoutePathDefinition) {
                    continue;
                }

                $canonical = $this->paths->canonicalPath($definition);
                $alias     = $this->paths->aliasPath($definition);

                if ($this->paths->shouldRedirectAlias($definition)
                    && $this->pathEquals($pathInfo, $alias)
                    && !$this->pathEquals($pathInfo, $canonical)
                    && SafePublicPath::isSafeRedirectTarget($canonical)
                ) {
                    $event->setResponse(new RedirectResponse($canonical, $this->redirectStatus));

                    return;
                }

                if (!$this->isManagedPath($pathInfo, $canonical, $alias)) {
                    continue;
                }

                $slashTarget = $this->trailingSlashTarget($pathInfo, $definition->trailingSlash);
                if ($slashTarget !== null
                    && $slashTarget !== $pathInfo
                    && SafePublicPath::isSafeRedirectTarget($slashTarget)
                ) {
                    $event->setResponse(new RedirectResponse($slashTarget, $this->redirectStatus));

                    return;
                }
            }
        }
    }

    private function isManagedPath(string $pathInfo, string $canonical, string $alias): bool
    {
        return $this->pathEquals($pathInfo, $canonical)
            || $this->pathEquals($pathInfo, $alias)
            || $this->pathEquals($this->stripTrailingSlash($pathInfo), $this->stripTrailingSlash($canonical))
            || $this->pathEquals($this->stripTrailingSlash($pathInfo), $this->stripTrailingSlash($alias));
    }

    private function pathEquals(string $a, string $b): bool
    {
        return rtrim($a, '/') === rtrim($b, '/') || $a === $b;
    }

    private function stripTrailingSlash(string $path): string
    {
        if ($path === '/') {
            return '/';
        }

        return rtrim($path, '/') ?: '/';
    }

    private function trailingSlashTarget(string $pathInfo, TrailingSlashStyle $style): ?string
    {
        if ($pathInfo === '/') {
            return null;
        }

        return match ($style) {
            TrailingSlashStyle::RedirectToOmit => str_ends_with($pathInfo, '/') ? rtrim($pathInfo, '/') : null,
            TrailingSlashStyle::RedirectToKeep => str_ends_with($pathInfo, '/') ? null : $pathInfo . '/',
            default                            => null,
        };
    }
}
