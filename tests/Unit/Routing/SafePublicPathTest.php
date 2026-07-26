<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Routing;

use Nowo\RoutingKitBundle\Routing\SafePublicPath;
use PHPUnit\Framework\TestCase;

final class SafePublicPathTest extends TestCase
{
    public function testRejectsOpenRedirectShapes(): void
    {
        self::assertTrue(SafePublicPath::isSafeStoredPath('/about'));
        self::assertTrue(SafePublicPath::isSafeStoredPath('/blog/{slug}'));
        self::assertFalse(SafePublicPath::isSafeStoredPath('//evil.example'));
        self::assertFalse(SafePublicPath::isSafeStoredPath('/http://evil.example'));
        self::assertFalse(SafePublicPath::isSafeStoredPath("/about\n"));
        self::assertFalse(SafePublicPath::isSafeRedirectTarget('//evil'));
    }
}
