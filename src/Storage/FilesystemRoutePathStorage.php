<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Storage;

use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use RuntimeException;

use function array_values;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;
use function rename;
use function sprintf;
use function tempnam;
use function uniqid;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * JSON file storage for route path definitions (Doctrine-free default).
 */
final class FilesystemRoutePathStorage implements RoutePathStorageInterface
{
    public function __construct(
        private readonly string $filePath,
    ) {
    }

    public function all(): array
    {
        return array_values($this->load());
    }

    public function find(string $routeName, string $locale): ?RoutePathDefinition
    {
        foreach ($this->load() as $definition) {
            if ($definition->routeName === $routeName && $definition->locale === $locale) {
                return $definition;
            }
        }

        return null;
    }

    public function findById(string $id): ?RoutePathDefinition
    {
        return $this->load()[$id] ?? null;
    }

    public function findByRouteName(string $routeName): array
    {
        $out = [];
        foreach ($this->load() as $definition) {
            if ($definition->routeName === $routeName) {
                $out[] = $definition;
            }
        }

        return $out;
    }

    public function save(RoutePathDefinition $definition): RoutePathDefinition
    {
        $items = $this->load();
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
        $this->persist($items);

        return $saved;
    }

    public function delete(string $id): void
    {
        $items = $this->load();
        unset($items[$id]);
        $this->persist($items);
    }

    public function replaceAll(array $definitions): array
    {
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

        $this->persist($items);

        return array_values($items);
    }

    /**
     * @return array<string, RoutePathDefinition>
     */
    private function load(): array
    {
        if (!is_file($this->filePath)) {
            return [];
        }

        $raw = file_get_contents($this->filePath);
        if ($raw === false || $raw === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
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
    private function persist(array $items): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Unable to create storage directory "%s".', $dir));
        }

        $payload = [];
        foreach ($items as $definition) {
            $payload[] = $definition->toArray();
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
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
}
