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

use function array_unique;
use function array_values;
use function count;
use function implode;
use function preg_match;
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
        $definition = $this->prepareDefinition($definition);
        $this->assertWithinLimit($definition);
        $this->assertNoConflicts([$definition], replaceAll: false);

        $saved = $this->storage->save($definition);
        $this->eventDispatcher->dispatch(new RoutePathsChangedEvent($saved));

        if ($invalidateCache && $this->autoInvalidateCache) {
            $this->cacheInvalidator->invalidate();
        }

        return $saved;
    }

    /**
     * Import definitions through the same validation / limits / conflict rules as panel saves.
     *
     * @param list<RoutePathDefinition> $definitions
     */
    public function import(array $definitions, bool $replaceAll = false): int
    {
        $prepared = [];
        foreach ($definitions as $definition) {
            $prepared[] = $this->prepareDefinition($definition);
        }

        if ($replaceAll) {
            if (count($prepared) > $this->maxDefinitions) {
                throw new RuntimeException(sprintf('Maximum number of route path definitions reached (%d).', $this->maxDefinitions));
            }
        } else {
            $extra = 0;
            foreach ($prepared as $definition) {
                if ($definition->id === null || !$this->storage->findById($definition->id) instanceof RoutePathDefinition) {
                    ++$extra;
                }
            }
            if (count($this->storage->all()) + $extra > $this->maxDefinitions) {
                throw new RuntimeException(sprintf('Maximum number of route path definitions reached (%d).', $this->maxDefinitions));
            }
        }

        $this->assertNoConflicts($prepared, $replaceAll);

        if ($replaceAll) {
            $savedRows = $this->storage->replaceAll($prepared);
            foreach ($savedRows as $saved) {
                $this->eventDispatcher->dispatch(new RoutePathsChangedEvent($saved));
            }
        } else {
            foreach ($prepared as $definition) {
                $saved = $this->storage->save($definition);
                $this->eventDispatcher->dispatch(new RoutePathsChangedEvent($saved));
            }
        }

        if ($this->autoInvalidateCache) {
            $this->cacheInvalidator->invalidate();
        }

        return count($prepared);
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

    private function prepareDefinition(RoutePathDefinition $definition): RoutePathDefinition
    {
        if (!$this->allowControllerOverride) {
            $definition = $definition->withoutController();
        }

        if ($definition->id !== null && $definition->id !== '' && preg_match('/^[A-Za-z0-9_.-]+$/', $definition->id) !== 1) {
            throw new RuntimeException('Invalid route path id: use only letters, digits, underscore, dot, or hyphen.');
        }

        $errors = $this->validator->validate(
            $definition->routeName,
            $definition->path,
            $definition->locale,
            $definition->controller,
        );
        if ($errors !== []) {
            throw new RuntimeException('Invalid route path: ' . implode(' ', $errors));
        }

        return $definition;
    }

    private function assertWithinLimit(RoutePathDefinition $definition): void
    {
        if ($definition->id === null && count($this->storage->all()) >= $this->maxDefinitions) {
            throw new RuntimeException(sprintf('Maximum number of route path definitions reached (%d).', $this->maxDefinitions));
        }
    }

    /**
     * @param list<RoutePathDefinition> $definitions
     */
    private function assertNoConflicts(array $definitions, bool $replaceAll): void
    {
        if (!$this->rejectConflicts) {
            return;
        }

        $messages = $this->conflictDetector->conflictsWithin($definitions);
        if (!$replaceAll) {
            foreach ($definitions as $definition) {
                foreach ($this->conflictDetector->conflictsFor($definition) as $message) {
                    $messages[] = $message;
                }
            }
        }

        $messages = array_values(array_unique($messages));
        if ($messages !== []) {
            throw new RuntimeException('Path conflicts: ' . implode(' ', $messages));
        }
    }
}
