<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Storage;

use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use RuntimeException;

use function array_values;
use function dirname;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function flock;
use function fopen;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function json_last_error;
use function json_last_error_msg;
use function mkdir;
use function rename;
use function sprintf;
use function tempnam;
use function uniqid;

use const JSON_ERROR_NONE;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const LOCK_EX;
use const LOCK_UN;

/**
 * JSON file storage for route path definitions (Doctrine-free default).
 *
 * Mutations take an exclusive lock on `{paths}.lock` to avoid lost updates.
 * Corrupt JSON fails closed (does not wipe the file on the next save).
 */
final class FilesystemRoutePathStorage implements RoutePathStorageInterface
{
    public function __construct(
        private readonly string $filePath,
    ) {
    }

    public function all(): array
    {
        return array_values($this->withExclusiveLock(fn (): array => $this->loadUnlocked()));
    }

    public function find(string $routeName, string $locale): ?RoutePathDefinition
    {
        return $this->withExclusiveLock(function () use ($routeName, $locale): ?RoutePathDefinition {
            foreach ($this->loadUnlocked() as $definition) {
                if ($definition->routeName === $routeName && $definition->locale === $locale) {
                    return $definition;
                }
            }

            return null;
        });
    }

    public function findById(string $id): ?RoutePathDefinition
    {
        return $this->withExclusiveLock(fn (): ?RoutePathDefinition => $this->loadUnlocked()[$id] ?? null);
    }

    public function findByRouteName(string $routeName): array
    {
        return $this->withExclusiveLock(function () use ($routeName): array {
            $out = [];
            foreach ($this->loadUnlocked() as $definition) {
                if ($definition->routeName === $routeName) {
                    $out[] = $definition;
                }
            }

            return $out;
        });
    }

    public function save(RoutePathDefinition $definition): RoutePathDefinition
    {
        return $this->withExclusiveLock(function () use ($definition): RoutePathDefinition {
            $items = $this->loadUnlocked();
            $id    = $definition->id ?? uniqid('rk_', true);

            foreach ($items as $existing) {
                if ($existing->id !== $id
                    && $existing->routeName === $definition->routeName
                    && $existing->locale === $definition->locale
                ) {
                    throw new RuntimeException(sprintf('A path already exists for route "%s" and locale "%s".', $definition->routeName, $definition->locale));
                }
            }

            $saved      = $definition->withId($id);
            $items[$id] = $saved;
            $this->persistUnlocked($items);

            return $saved;
        });
    }

    public function delete(string $id): void
    {
        $this->withExclusiveLock(function () use ($id): void {
            $items = $this->loadUnlocked();
            unset($items[$id]);
            $this->persistUnlocked($items);
        });
    }

    public function replaceAll(array $definitions): array
    {
        return $this->withExclusiveLock(function () use ($definitions): array {
            $items = [];
            $seen  = [];

            foreach ($definitions as $definition) {
                $id = $definition->id ?? uniqid('rk_', true);
                $rl = $definition->routeName . "\0" . $definition->locale;
                if (isset($seen[$rl])) {
                    throw new RuntimeException(sprintf('A path already exists for route "%s" and locale "%s".', $definition->routeName, $definition->locale));
                }
                $seen[$rl]  = true;
                $saved      = $definition->withId($id);
                $items[$id] = $saved;
            }

            $this->persistUnlocked($items);

            return array_values($items);
        });
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    private function withExclusiveLock(callable $callback): mixed
    {
        $this->ensureDirectory();

        $lockPath = $this->filePath . '.lock';
        $handle   = @fopen($lockPath, 'c+');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to open storage lock "%s".', $lockPath)); // @codeCoverageIgnore
        }

        if (!flock($handle, LOCK_EX)) {
            fclose($handle); // @codeCoverageIgnore

            throw new RuntimeException(sprintf('Unable to lock storage "%s".', $lockPath)); // @codeCoverageIgnore
        }

        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * @return array<string, RoutePathDefinition>
     */
    private function loadUnlocked(): array
    {
        if (!is_file($this->filePath)) {
            return [];
        }

        $raw = file_get_contents($this->filePath);
        if ($raw === false) {
            throw new RuntimeException(sprintf('Unable to read route path storage "%s".', $this->filePath)); // @codeCoverageIgnore
        }
        if ($raw === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(sprintf('Corrupt route path storage "%s": %s', $this->filePath, json_last_error_msg()));
        }
        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('Corrupt route path storage "%s": expected a JSON array.', $this->filePath));
        }

        $items = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $definition = RoutePathDefinition::fromArray($row);
            if ($definition->id === null || $definition->id === '') {
                continue;
            }
            $items[$definition->id] = $definition;
        }

        return $items;
    }

    /**
     * @param array<string, RoutePathDefinition> $items
     */
    private function persistUnlocked(array $items): void
    {
        $this->ensureDirectory();

        $payload = [];
        foreach ($items as $definition) {
            $payload[] = $definition->toArray();
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $dir  = dirname($this->filePath);
        $tmp  = tempnam($dir, 'rk_');
        if ($tmp === false) {
            throw new RuntimeException('Unable to create temporary storage file.'); // @codeCoverageIgnore
        }

        if (@file_put_contents($tmp, $json . "\n") === false) {
            throw new RuntimeException('Unable to write route path storage.'); // @codeCoverageIgnore
        }

        if (!@rename($tmp, $this->filePath)) {
            throw new RuntimeException(sprintf('Unable to replace storage file "%s".', $this->filePath)); // @codeCoverageIgnore
        }
    }

    private function ensureDirectory(): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Unable to create storage directory "%s".', $dir));
        }
    }
}
