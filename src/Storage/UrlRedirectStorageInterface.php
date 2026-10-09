<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Storage;

use DateTimeImmutable;
use Nowo\RoutingKitBundle\Model\UrlRedirect;
use RuntimeException;

/**
 * Persistence for operator URL redirects (REQ-REDIR-001).
 *
 * Default: {@see FilesystemUrlRedirectStorage} (JSON file). Hosts that keep redirects in a database
 * implement this interface (e.g. over a Doctrine repository) and set
 * `nowo_routing_kit.url_redirects.storage` to the service id.
 */
interface UrlRedirectStorageInterface
{
    /**
     * All rows, ordered by source path.
     *
     * @return list<UrlRedirect>
     */
    public function all(): array;

    public function findById(string $id): ?UrlRedirect;

    /**
     * @param string $sourcePath normalized path ({@see UrlRedirect::normalizePath()})
     */
    public function findBySource(string $sourcePath): ?UrlRedirect;

    /**
     * Enabled redirects keyed by normalized source path (the hot-path read, cached by the matcher).
     *
     * @return array<string, array{target: string, status: int}>
     */
    public function enabledMap(): array;

    /**
     * Inserts or updates; assigns an id on insert. Must reject a duplicate source path.
     *
     * @throws RuntimeException on duplicate source or I/O failure
     */
    public function save(UrlRedirect $redirect): UrlRedirect;

    public function delete(string $id): void;

    /**
     * Counts one hit (`hits + 1`, `lastHitAt = $at`). Unknown source paths are ignored.
     */
    public function recordHit(string $sourcePath, DateTimeImmutable $at): void;
}
