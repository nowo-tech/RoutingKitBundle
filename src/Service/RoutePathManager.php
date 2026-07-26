<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Service;

use Nowo\RoutingKitBundle\Event\RoutePathsChangedEvent;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Routing\RouteCacheInvalidator;
use Nowo\RoutingKitBundle\Storage\RoutePathStorageInterface;
use Nowo\RoutingKitBundle\Validation\RoutePathValidator;
use RuntimeException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function implode;

final class RoutePathManager
{
    public function __construct(
        private readonly RoutePathStorageInterface $storage,
        private readonly RoutePathValidator $validator,
        private readonly RouteCacheInvalidator $cacheInvalidator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly bool $autoInvalidateCache = true,
    ) {
    }

    /**
     * @return list<RoutePathDefinition>
     */
    public function all(): array
    {
        return $this->storage->all();
    }

    public function get(string $id): ?RoutePathDefinition
    {
        return $this->storage->findById($id);
    }

    public function save(RoutePathDefinition $definition, bool $invalidateCache = true): RoutePathDefinition
    {
        $errors = $this->validator->validate($definition->routeName, $definition->path);
        if ($errors !== []) {
            throw new RuntimeException('Invalid route path: ' . implode(' ', $errors));
        }

        $saved = $this->storage->save($definition);
        $this->eventDispatcher->dispatch(new RoutePathsChangedEvent($saved));

        if ($invalidateCache && $this->autoInvalidateCache) {
            $this->cacheInvalidator->invalidate();
        }

        return $saved;
    }

    public function delete(string $id, bool $invalidateCache = true): void
    {
        $existing = $this->storage->findById($id);
        $this->storage->delete($id);
        if ($existing instanceof RoutePathDefinition) {
            $this->eventDispatcher->dispatch(new RoutePathsChangedEvent($existing, deleted: true));
        }

        if ($invalidateCache && $this->autoInvalidateCache) {
            $this->cacheInvalidator->invalidate();
        }
    }

    public function clearCache(): void
    {
        $this->cacheInvalidator->invalidate();
    }
}
