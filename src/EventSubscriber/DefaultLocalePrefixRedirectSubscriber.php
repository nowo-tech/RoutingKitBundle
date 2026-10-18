<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\EventSubscriber;

use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Routing\DefaultLocaleRouter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function in_array;
use function is_array;
use function is_string;
use function str_starts_with;

/**
 * `GET /{default}/…` answers a permanent redirect to the bare URL of the same page (`/en/login` → `/login`), query
 * string kept, when the matched route has a bare twin ({@see DefaultLocaleRouter}); otherwise nothing changes.
 *
 * Runs right after the router matched the request (RouterListener is 32), GET / HEAD of the main request only (a form
 * POST is never redirected). Stateless.
 */
final class DefaultLocalePrefixRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urls,
        private readonly LocaleProviderInterface $locales,
        private readonly int $status = 301,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 31]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return;
        }

        $defaultLocale = $this->locales->getDefaultLocale();
        $prefix        = '/' . $defaultLocale;
        $path          = $request->getPathInfo();
        $route         = $request->attributes->get('_route');
        if (
            !is_string($route)
            || ($path !== $prefix && !str_starts_with($path, $prefix . '/'))
            || $request->attributes->get('_locale') !== $defaultLocale
        ) {
            return;
        }

        $parameters = $request->attributes->get('_route_params', []);
        $parameters = is_array($parameters) ? $parameters : [];
        $bare       = $this->urls->generate($route, ['_locale' => $defaultLocale] + $parameters);
        $prefixed   = $request->getBaseUrl() . $prefix;
        if ($bare === $prefixed || str_starts_with($bare, $prefixed . '/')) {
            return;
        }

        $query = $request->getQueryString();
        $event->setResponse(new RedirectResponse($bare . ($query === null ? '' : '?' . $query), $this->status));
    }
}
