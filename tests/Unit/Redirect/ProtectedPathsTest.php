<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Redirect;

use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Redirect\ProtectedPaths;
use PHPUnit\Framework\TestCase;

final class ProtectedPathsTest extends TestCase
{
    public function testDefaults(): void
    {
        $paths = new ProtectedPaths(new ConfigurableLocaleProvider('es', ['es', 'en', 'pt_BR']));

        foreach (['', '/', '/admin', '/admin/users', '/ADMIN/x', '/_profiler', '/_routing/redirects', '/media/a.jpg', '/login',
            '/en/login/magic/check', '/es/api/x', '/pt-br/register', '/PT_BR/register', '/robots.txt', '/sitemap.xml', '/favicon.ico',
            '/.well-known/security.txt', '/en/_wdt/1', 'admin'] as $path) {
            self::assertTrue($paths->isProtected($path), $path);
        }

        foreach (['/administration', '/blog/old', '/en/old-page', '/en', '/en/', '/fr/login', '/ig', '/medias', '/es/administracion'] as $path) {
            self::assertFalse($paths->isProtected($path), $path);
        }
    }

    public function testCustomLists(): void
    {
        $paths = new ProtectedPaths(new ConfigurableLocaleProvider('en', ['en']), ['/panel/', '', '/Settings'], ['/llms', '']);

        self::assertTrue($paths->isProtected('/panel'));
        self::assertTrue($paths->isProtected('/settings/x'));
        self::assertTrue($paths->isProtected('/llms-full.txt'));
        self::assertTrue($paths->isProtected('/en/llms.txt'));
        self::assertFalse($paths->isProtected('/admin'), 'Custom lists replace the defaults.');
        self::assertFalse($paths->isProtected('/_profiler'));
    }
}
