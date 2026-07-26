<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Seo;

use Nowo\RoutingKitBundle\Locale\ConfigurableLocaleProvider;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Routing\PublicPathResolver;
use Nowo\RoutingKitBundle\Seo\RoutingKitSeoPathProvider;
use Nowo\RoutingKitBundle\Storage\FilesystemRoutePathStorage;
use PHPUnit\Framework\TestCase;

final class RoutingKitSeoPathProviderTest extends TestCase
{
    public function testPagePathReturnsCanonical(): void
    {
        $file    = sys_get_temp_dir() . '/rk_seo_' . uniqid('', true) . '.json';
        $storage = new FilesystemRoutePathStorage($file);
        $storage->save(new RoutePathDefinition(
            'app_about',
            'en',
            '/about',
            CanonicalStyle::WithoutPrefix,
        ));

        $locales  = new ConfigurableLocaleProvider('en', ['en', 'es']);
        $provider = new RoutingKitSeoPathProvider(new PublicPathResolver($storage, $locales), $locales);

        self::assertSame('/about', $provider->pagePath('app_about', 'en'));
        self::assertSame('/es/about', $provider->pagePath('app_about', 'es'));
        self::assertSame('/fallback', $provider->pagePath('missing', 'en', '/fallback'));
        self::assertSame('en', $provider->defaultLocale());
        self::assertSame(['en', 'es'], $provider->locales());

        @unlink($file);
    }
}
