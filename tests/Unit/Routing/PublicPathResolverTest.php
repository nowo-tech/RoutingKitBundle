<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Routing;

use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Model\AliasMode;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Model\TrailingSlashStyle;
use Nowo\RoutingKitBundle\Routing\PublicPathResolver;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use PHPUnit\Framework\TestCase;

final class PublicPathResolverTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir() . '/rk_paths_' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testCanonicalWithoutPrefixAndFallbackLocale(): void
    {
        $storage = new FilesystemRoutePathStorage($this->file);
        $storage->save(new RoutePathDefinition(
            routeName: 'app_about',
            locale: 'en',
            path: '/about',
            canonicalStyle: CanonicalStyle::WithoutPrefix,
            aliasMode: AliasMode::Redirect,
        ));

        $resolver = new PublicPathResolver(
            $storage,
            new ConfigurableLocaleProvider('en', ['en', 'es']),
        );

        $en = $resolver->resolveDefinition('app_about', 'en');
        self::assertNotNull($en);
        self::assertSame('/about', $resolver->canonicalPath($en));
        self::assertSame('/en/about', $resolver->aliasPath($en));

        $es = $resolver->resolveDefinition('app_about', 'es');
        self::assertNotNull($es);
        self::assertSame('/es/about', $resolver->canonicalPath($es));
    }

    public function testSupportsTrailingSlashRootAndAliasModeChecks(): void
    {
        $storage = new FilesystemRoutePathStorage($this->file);
        $storage->save(new RoutePathDefinition(
            routeName: 'app_home',
            locale: 'en',
            path: '/',
            canonicalStyle: CanonicalStyle::WithPrefix,
            trailingSlash: TrailingSlashStyle::Keep,
            aliasMode: AliasMode::Alias,
        ));
        $storage->save(new RoutePathDefinition(
            routeName: 'app_contact',
            locale: 'en',
            path: '/contact',
            trailingSlash: TrailingSlashStyle::Keep,
        ));

        $resolver = new PublicPathResolver(
            $storage,
            new ConfigurableLocaleProvider('en', ['en', 'es']),
        );

        $home    = $resolver->resolveDefinition('app_home', 'en');
        $contact = $resolver->resolveDefinition('app_contact', 'en');

        self::assertNotNull($home);
        self::assertNotNull($contact);
        self::assertSame('/en/', $resolver->prefixedPath($home));
        self::assertSame('/', $resolver->unprefixedPath($home));
        self::assertSame('/contact/', $resolver->canonicalPath($contact));
        self::assertFalse($resolver->shouldRedirectAlias($home));
    }

    public function testReturnsNullWhenDefaultLocaleDefinitionIsDisabled(): void
    {
        $storage = new FilesystemRoutePathStorage($this->file);
        $storage->save(new RoutePathDefinition(
            routeName: 'app_hidden',
            locale: 'en',
            path: '/hidden',
            enabled: false,
        ));

        $resolver = new PublicPathResolver(
            $storage,
            new ConfigurableLocaleProvider('en', ['en', 'es']),
        );

        self::assertNull($resolver->resolveDefinition('app_hidden', 'en'));
    }

    public function testReturnsNullWhenNonDefaultLocaleHasNoFallback(): void
    {
        $storage = new FilesystemRoutePathStorage($this->file);

        $resolver = new PublicPathResolver(
            $storage,
            new ConfigurableLocaleProvider('en', ['en', 'es']),
        );

        self::assertNull($resolver->resolveDefinition('app_missing', 'es'));
    }
}
