<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\EventSubscriber;

use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\Redirect\LivePublicPaths;
use Nowo\RoutingKitBundle\Redirect\ProtectedPaths;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectMatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function str_contains;
use function str_starts_with;

/**
 * Applies operator URL redirects before routing (REQ-REDIR-005).
 *
 * RouterListener runs at 32; this listener runs at 33, main request, GET / HEAD only. Skips protected
 * paths and paths a live page serves (a page published after the redirect was saved wins). The
 * visitor's query string follows internal targets only, never to another host. Rows whose
 * internal-looking target is unsafe (`/\host`, saved before validation) are ignored.
 */
final class UrlRedirectSubscriber implements EventSubscriberInterface
{
    public const PRIORITY = 33;

    public function __construct(
        private readonly UrlRedirectMatcher $redirects,
        private readonly ProtectedPaths $protectedPaths,
        private readonly LivePublicPaths $livePaths,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', self::PRIORITY],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethodCacheable()) {
            return;
        }

        $path = $request->getPathInfo();
        if ($this->protectedPaths->isProtected(UrlRedirect::normalizePath($path))) {
            return;
        }

        $redirect = $this->redirects->match($path);
        if ($redirect === null) {
            return;
        }

        $target   = $redirect['target'];
        $internal = UrlRedirect::isInternalTarget($target);
        if (str_starts_with($target, '/') ? !$internal : !UrlRedirect::isExternalTarget($target)) {
            return;
        }

        if ($this->livePaths->isLive($path)) {
            return;
        }

        $query = $request->getQueryString();
        if ($internal && $query !== null && $query !== '' && !str_contains($target, '?')) {
            $target .= '?' . $query;
        }

        $this->redirects->recordHit($path);
        $event->setResponse(new RedirectResponse($target, $redirect['status']));
    }
}
