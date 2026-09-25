<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\EventSubscriber;

use Nowo\RoutingKitBundle\Routing\RouteCacheInvalidator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Before routing, rebuilds this worker's router when the route table was changed by any worker.
 */
final class RouteTableFreshnessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RouteCacheInvalidator $invalidator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Before RouterListener (32) and CanonicalRedirectSubscriber (33).
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 4096],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->invalidator->refreshIfStale();
    }
}
