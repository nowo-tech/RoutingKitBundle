<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\EventSubscriber;

use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Optional redirect from "/" to the default-locale home.
 */
final class RootRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LocaleProviderInterface $locales,
        private readonly bool $enabled = true,
        private readonly CanonicalStyle $homeCanonicalStyle = CanonicalStyle::WithoutPrefix,
        private readonly string $homePath = '/',
        private readonly int $redirectStatus = 302,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 34],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        if ($event->getRequest()->getPathInfo() !== '/') {
            return;
        }

        // When home is served without prefix, "/" is already the home — no redirect.
        if ($this->homeCanonicalStyle === CanonicalStyle::WithoutPrefix && $this->homePath === '/') {
            return;
        }

        $target = match ($this->homeCanonicalStyle) {
            CanonicalStyle::WithPrefix    => '/' . $this->locales->getDefaultLocale() . $this->homePath,
            CanonicalStyle::WithoutPrefix => $this->homePath,
        };

        $event->setResponse(new RedirectResponse($target, $this->redirectStatus));
    }
}
