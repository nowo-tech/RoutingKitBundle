<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Storage;

use DateTimeImmutable;
use Nowo\RoutingKitBundle\Model\UrlRedirect;
use RuntimeException;

use function array_values;
use function bin2hex;
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
use function random_bytes;
use function rename;
use function sprintf;
use function strcmp;
use function tempnam;
use function usort;

use const JSON_ERROR_NONE;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const LOCK_EX;
use const LOCK_SH;
use const LOCK_UN;

/**
 * JSON file storage for operator URL redirects (Doctrine-free default, REQ-REDIR-001).
 *
 * Same locking model as {@see FilesystemRoutePathStorage}: exclusive lock on `{file}.lock` for writes,
 * shared lock for reads, atomic rename, corrupt JSON fails closed. Hit counting rewrites the file
 * under the exclusive lock: dispatch hits through Messenger (`url_redirects.hits.message_bus`) or
 * disable them (`url_redirects.hits.enabled: false`) on high-traffic legacy URLs.
 */
final class FilesystemUrlRedirectStorage implements UrlRedirectStorageInterface
{
    public function __construct(
        private readonly string $filePath,
    ) {
    }

    public function all(): array
    {
        $rows = array_values($this->withLock(LOCK_SH, fn (): array => $this->loadUnlocked()));
        usort($rows, static fn (UrlRedirect $a, UrlRedirect $b): int => strcmp($a->getSourcePath(), $b->getSourcePath()));

        return $rows;
    }

    public function findById(string $id): ?UrlRedirect
    {
        return $this->withLock(LOCK_SH, fn (): ?UrlRedirect => $this->loadUnlocked()[$id] ?? null);
    }

    public function findBySource(string $sourcePath): ?UrlRedirect
    {
        return $this->withLock(LOCK_SH, fn (): ?UrlRedirect => $this->findBySourceIn($this->loadUnlocked(), $sourcePath));
    }

    public function enabledMap(): array
    {
        return $this->withLock(LOCK_SH, function (): array {
            $map = [];
            foreach ($this->loadUnlocked() as $redirect) {
                if ($redirect->isEnabled() && $redirect->getSourcePath() !== '') {
                    $map[$redirect->getSourcePath()] = ['target' => $redirect->getTargetUrl(), 'status' => $redirect->getStatusCode()];
                }
            }

            return $map;
        });
    }

    public function save(UrlRedirect $redirect): UrlRedirect
    {
        return $this->withLock(LOCK_EX, function () use ($redirect): UrlRedirect {
            $items    = $this->loadUnlocked();
            $existing = $this->findBySourceIn($items, $redirect->getSourcePath());
            if ($existing instanceof UrlRedirect && $existing->getId() !== $redirect->getId()) {
                throw new RuntimeException(sprintf('A redirect already exists for "%s".', $redirect->getSourcePath()));
            }

            $id = $redirect->getId();
            if ($id === null || $id === '') {
                $id = 'rd_' . bin2hex(random_bytes(8));
                $redirect->setId($id);
            }
            $items[$id] = $redirect;
            $this->persistUnlocked($items);

            return $redirect;
        });
    }

    public function delete(string $id): void
    {
        $this->withLock(LOCK_EX, function () use ($id): void {
            $items = $this->loadUnlocked();
            if (!isset($items[$id])) {
                return;
            }
            unset($items[$id]);
            $this->persistUnlocked($items);
        });
    }

    public function recordHit(string $sourcePath, DateTimeImmutable $at): void
    {
        $this->withLock(LOCK_EX, function () use ($sourcePath, $at): void {
            $items    = $this->loadUnlocked();
            $redirect = $this->findBySourceIn($items, $sourcePath);
            if (!$redirect instanceof UrlRedirect) {
                return;
            }
            $redirect->setHits($redirect->getHits() + 1)->setLastHitAt($at);
            $this->persistUnlocked($items);
        });
    }

    /**
     * @param array<string, UrlRedirect> $items
     */
    private function findBySourceIn(array $items, string $sourcePath): ?UrlRedirect
    {
        foreach ($items as $redirect) {
            if ($redirect->getSourcePath() === $sourcePath) {
                return $redirect;
            }
        }

        return null;
    }

    /**
     * @template T
     *
     * @param LOCK_EX|LOCK_SH $operation
     * @param callable(): T $callback
     *
     * @return T
     */
    private function withLock(int $operation, callable $callback): mixed
    {
        $this->ensureDirectory();

        $lockPath = $this->filePath . '.lock';
        $handle   = @fopen($lockPath, 'c+');
        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to open storage lock "%s".', $lockPath)); // @codeCoverageIgnore
        }

        if (!flock($handle, $operation)) {
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
     * @return array<string, UrlRedirect>
     */
    private function loadUnlocked(): array
    {
        if (!is_file($this->filePath)) {
            return [];
        }

        $raw = file_get_contents($this->filePath);
        if ($raw === false) {
            throw new RuntimeException(sprintf('Unable to read redirect storage "%s".', $this->filePath)); // @codeCoverageIgnore
        }
        if ($raw === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(sprintf('Corrupt redirect storage "%s": %s', $this->filePath, json_last_error_msg()));
        }
        if (!is_array($decoded)) {
            throw new RuntimeException(sprintf('Corrupt redirect storage "%s": expected a JSON array.', $this->filePath));
        }

        $items = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $redirect = UrlRedirect::fromArray($row);
            $id       = $redirect->getId();
            if ($id === null) {
                continue;
            }
            $items[$id] = $redirect;
        }

        return $items;
    }

    /**
     * @param array<string, UrlRedirect> $items
     */
    private function persistUnlocked(array $items): void
    {
        $payload = [];
        foreach ($items as $redirect) {
            $payload[] = $redirect->toArray();
        }

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $tmp  = tempnam(dirname($this->filePath), 'rd_');
        if ($tmp === false) {
            throw new RuntimeException('Unable to create temporary storage file.'); // @codeCoverageIgnore
        }

        if (@file_put_contents($tmp, $json . "\n") === false) {
            throw new RuntimeException('Unable to write redirect storage.'); // @codeCoverageIgnore
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
