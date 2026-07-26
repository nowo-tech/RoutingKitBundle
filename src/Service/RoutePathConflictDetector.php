<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Service;

use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Routing\PublicPathResolver;
use Nowo\RoutingKitBundle\Storage\RoutePathStorageInterface;

use function sprintf;

/**
 * Detects path collisions between definitions (same public path, different route/locale).
 */
final class RoutePathConflictDetector
{
    public function __construct(
        private readonly RoutePathStorageInterface $storage,
        private readonly PublicPathResolver $paths,
    ) {
    }

    /**
     * @return list<string> Human-readable conflict messages
     */
    public function conflictsFor(RoutePathDefinition $candidate): array
    {
        $messages       = [];
        $candidatePaths = [
            $this->paths->unprefixedPath($candidate),
            $this->paths->prefixedPath($candidate),
        ];

        foreach ($this->storage->all() as $existing) {
            if (!$existing->enabled) {
                continue;
            }
            if ($candidate->id !== null && $existing->id === $candidate->id) {
                continue;
            }
            if ($existing->routeName === $candidate->routeName && $existing->locale === $candidate->locale) {
                $messages[] = sprintf(
                    'A row already exists for route "%s" and locale "%s".',
                    $candidate->routeName,
                    $candidate->locale,
                );
                continue;
            }

            $existingPaths = [
                $this->paths->unprefixedPath($existing),
                $this->paths->prefixedPath($existing),
            ];

            foreach ($candidatePaths as $left) {
                foreach ($existingPaths as $right) {
                    if ($left === $right) {
                        $messages[] = sprintf(
                            'Public path "%s" collides with "%s" (%s).',
                            $left,
                            $existing->routeName,
                            $existing->locale,
                        );
                    }
                }
            }
        }

        return array_values(array_unique($messages));
    }
}
