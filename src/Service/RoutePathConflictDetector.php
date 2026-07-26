<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Service;

use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Routing\PublicPathResolver;
use Nowo\RoutingKitBundle\Storage\RoutePathStorageInterface;

use function array_unique;
use function array_values;
use function count;
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
        return $this->conflictsAgainst($candidate, $this->storage->all());
    }

    /**
     * Pairwise conflicts inside an import batch (ignores current storage).
     *
     * @param list<RoutePathDefinition> $definitions
     *
     * @return list<string>
     */
    public function conflictsWithin(array $definitions): array
    {
        $messages = [];
        $n        = count($definitions);

        for ($i = 0; $i < $n; ++$i) {
            $left = $definitions[$i];
            for ($j = $i + 1; $j < $n; ++$j) {
                $right = $definitions[$j];
                foreach ($this->pairMessages($left, $right) as $message) {
                    $messages[] = $message;
                }
            }
        }

        return array_values(array_unique($messages));
    }

    /**
     * @param list<RoutePathDefinition> $against
     *
     * @return list<string>
     */
    private function conflictsAgainst(RoutePathDefinition $candidate, array $against): array
    {
        $messages = [];

        foreach ($against as $existing) {
            if ($candidate->id !== null && $existing->id === $candidate->id) {
                continue;
            }

            foreach ($this->pairMessages($candidate, $existing) as $message) {
                $messages[] = $message;
            }
        }

        return array_values(array_unique($messages));
    }

    /**
     * @return list<string>
     */
    private function pairMessages(RoutePathDefinition $left, RoutePathDefinition $right): array
    {
        if ($left->routeName === $right->routeName && $left->locale === $right->locale) {
            return [sprintf(
                'A row already exists for route "%s" and locale "%s".',
                $left->routeName,
                $left->locale,
            )];
        }

        $messages   = [];
        $leftPaths  = $this->paths->occupiedPublicPaths($left);
        $rightPaths = $this->paths->occupiedPublicPaths($right);

        foreach ($leftPaths as $path) {
            foreach ($rightPaths as $other) {
                if ($path === $other) {
                    $messages[] = sprintf(
                        'Public path "%s" collides with "%s" (%s).',
                        $path,
                        $right->routeName,
                        $right->locale,
                    );
                }
            }
        }

        return $messages;
    }
}
