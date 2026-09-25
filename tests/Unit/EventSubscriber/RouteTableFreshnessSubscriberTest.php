<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\EventSubscriber;

use Nowo\RoutingKitBundle\EventSubscriber\RouteTableFreshnessSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelEvents;

final class RouteTableFreshnessSubscriberTest extends TestCase
{
    public function testRunsBeforeRoutingAndCanonicalRedirects(): void
    {
        $events = RouteTableFreshnessSubscriber::getSubscribedEvents();

        self::assertSame(['onKernelRequest', 4096], $events[KernelEvents::REQUEST]);
    }
}
