<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Event;

use Nowo\RoutingKitBundle\Event\RoutePathsChangedEvent;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use PHPUnit\Framework\TestCase;

final class RoutePathsChangedEventTest extends TestCase
{
    public function testExposesDefinitionAndDeletedFlag(): void
    {
        $definition = new RoutePathDefinition('app_home', 'en', '/', id: 'rk_1');

        $created = new RoutePathsChangedEvent($definition);
        $deleted = new RoutePathsChangedEvent($definition, deleted: true);

        self::assertSame($definition, $created->definition);
        self::assertFalse($created->deleted);
        self::assertSame($definition, $deleted->definition);
        self::assertTrue($deleted->deleted);
    }
}
