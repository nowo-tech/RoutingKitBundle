<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Storage;

use Nowo\RoutingKitBundle\Model\RoutePathDefinition;

interface RoutePathStorageInterface
{
    /**
     * @return list<RoutePathDefinition>
     */
    public function all(): array;

    public function find(string $routeName, string $locale): ?RoutePathDefinition;

    public function findById(string $id): ?RoutePathDefinition;

    /**
     * @return list<RoutePathDefinition>
     */
    public function findByRouteName(string $routeName): array;

    public function save(RoutePathDefinition $definition): RoutePathDefinition;

    public function delete(string $id): void;
}
