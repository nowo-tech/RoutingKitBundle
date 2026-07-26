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

use function count;
use function implode;
use function sprintf;

final class RoutePathManager
{
    public function __construct(
        private readonly RoutePathStorageInterface $storage,
        private readonly RoutePathValidator $validator,
        private readonly RouteCacheInvalidator $cacheInvalidator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RoutePathConflictDetector $conflictDetector,
        private readonly bool $autoInvalidateCache = true,
        private readonly int $maxDefinitions = 500,
        private readonly bool $allowControllerOverride = false,
        private readonly bool $rejectConflicts = true,
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

    /**
     * @return list<string>
     */
    public function previewConflicts(RoutePathDefinition $definition): array
    {
        return $this->conflictDetector->conflictsFor($definition);
    }

    public function save(RoutePathDefinition $definition, bool $invalidateCache = true): RoutePathDefinition
    {
        $controller = $definition->controller;
        if (!$this->allowControllerOverride) {
            $controller = null;
            $definition = new RoutePathDefinition(
                routeName: $definition->routeName,
                locale: $definition->locale,
                path: $definition->path,
                canonicalStyle: $definition->canonicalStyle,
                trailingSlash: $definition->trailingSlash,
                aliasMode: $definition->aliasMode,
                enabled: $definition->enabled,
                id: $definition->id,
            );
        }

        $errors = $this->validator->validate(
            $definition->routeName,
            $definition->path,
            $definition->locale,
            $controller,
        );
        if ($errors !== []) {
            throw new RuntimeException('Invalid route path: ' . implode(' ', $errors));
        }

        if ($definition->id === null && count($this->storage->all()) >= $this->maxDefinitions) {
            throw new RuntimeException(sprintf('Maximum number of route path definitions reached (%d).', $this->maxDefinitions));
        }

        if ($this->rejectConflicts) {
            $conflicts = $this->conflictDetector->conflictsFor($definition);
            if ($conflicts !== []) {
                throw new RuntimeException('Path conflicts: ' . implode(' ', $conflicts));
            }
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
