<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Model;

use Nowo\RoutingKitBundle\Model\AliasMode;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Model\TrailingSlashStyle;
use PHPUnit\Framework\TestCase;

final class RoutePathDefinitionTest extends TestCase
{
    public function testRoundTripArray(): void
    {
        $definition = new RoutePathDefinition(
            routeName: 'app_about',
            locale: 'es',
            path: '/sobre-nosotros',
            canonicalStyle: CanonicalStyle::WithPrefix,
            trailingSlash: TrailingSlashStyle::Omit,
            aliasMode: AliasMode::Redirect,
            enabled: true,
            controller: 'App\\Controller\\AboutController::index',
            id: 'rk_1',
        );

        $restored = RoutePathDefinition::fromArray($definition->toArray());

        self::assertSame('app_about', $restored->routeName);
        self::assertSame('es', $restored->locale);
        self::assertSame('/sobre-nosotros', $restored->path);
        self::assertSame(CanonicalStyle::WithPrefix, $restored->canonicalStyle);
        self::assertSame('rk_1', $restored->id);
    }

    public function testWithIdReturnsClonedDefinition(): void
    {
        $definition = new RoutePathDefinition('app_home', 'en', '/');

        $withId = $definition->withId('rk_2');

        self::assertNull($definition->id);
        self::assertSame('rk_2', $withId->id);
        self::assertSame('app_home', $withId->routeName);
    }

    public function testFromArrayUsesDefaultsForMissingOrInvalidValues(): void
    {
        $definition = RoutePathDefinition::fromArray([
            'route_name'      => 'app_blog',
            'locale'          => 'en',
            'path'            => '/blog',
            'canonical_style' => 'invalid',
            'trailing_slash'  => 'invalid',
            'alias_mode'      => 'invalid',
        ]);

        self::assertSame(CanonicalStyle::WithoutPrefix, $definition->canonicalStyle);
        self::assertSame(TrailingSlashStyle::Omit, $definition->trailingSlash);
        self::assertSame(AliasMode::Redirect, $definition->aliasMode);
        self::assertTrue($definition->enabled);
        self::assertNull($definition->controller);
        self::assertNull($definition->id);
    }
}
