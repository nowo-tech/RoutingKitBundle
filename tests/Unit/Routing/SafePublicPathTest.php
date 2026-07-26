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
        self::assertFalse(SafePublicPath::isSafeStoredPath("/about\t"));
        self::assertFalse(SafePublicPath::isSafeStoredPath("/about\x01"));
        self::assertFalse(SafePublicPath::isSafeStoredPath("/about\x7f"));
        self::assertFalse(SafePublicPath::isSafeStoredPath('/%2f%2fevil'));
        self::assertFalse(SafePublicPath::isSafeStoredPath('/%252f%252fevil'));
        self::assertFalse(SafePublicPath::isSafeStoredPath('/%5cevil'));
        self::assertFalse(SafePublicPath::isSafeStoredPath('/%00null'));
        self::assertFalse(SafePublicPath::isSafeStoredPath('/%0d%0ainject'));
        self::assertFalse(SafePublicPath::isSafeStoredPath('/foo/../admin'));
        self::assertFalse(SafePublicPath::isSafeStoredPath('/javascript:alert(1)'));
        self::assertFalse(SafePublicPath::isSafeRedirectTarget('//evil'));
    }
}
