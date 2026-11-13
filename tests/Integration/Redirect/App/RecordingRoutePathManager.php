<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Redirect\App;

use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Service\RoutePathManagerInterface;

/**
 * A host decorator wired by aliasing RoutePathManagerInterface (REQ-ARCH-001 rule 1).
 */
final class RecordingRoutePathManager implements RoutePathManagerInterface
{
    /** @var list<string> */
    public array $calls = [];

    public function __construct(private readonly RoutePathManagerInterface $inner)
    {
    }

    public function all(): array
    {
        $this->calls[] = 'all';

        return $this->inner->all();
    }

    public function get(string $id): ?RoutePathDefinition
    {
        return $this->inner->get($id);
    }

    public function previewConflicts(RoutePathDefinition $definition): array
    {
        return $this->inner->previewConflicts($definition);
    }

    public function save(RoutePathDefinition $definition, bool $invalidateCache = true): RoutePathDefinition
    {
        return $this->inner->save($definition, $invalidateCache);
    }

    public function import(array $definitions, bool $replaceAll = false): int
    {
        return $this->inner->import($definitions, $replaceAll);
    }

    public function delete(string $id, bool $invalidateCache = true): void
    {
        $this->inner->delete($id, $invalidateCache);
    }

    public function clearCache(): void
    {
        $this->inner->clearCache();
    }
}
