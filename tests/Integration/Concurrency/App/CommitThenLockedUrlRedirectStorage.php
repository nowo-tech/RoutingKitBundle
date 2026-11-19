<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Concurrency\App;

use DateTimeImmutable;
use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\Storage\StorageLockTimeoutException;
use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;

/**
 * Host storage (aliased onto UrlRedirectStorageInterface) for the committed-then-conflict case: `save()` first commits
 * an audit row through the real filesystem store, then meets a storage lock that does not free up in time — what a
 * two-step host write sees when another worker grabs the lock between its steps.
 */
final class CommitThenLockedUrlRedirectStorage implements UrlRedirectStorageInterface
{
    public function __construct(
        private readonly UrlRedirectStorageInterface $inner,
        private readonly string $lockPath,
    ) {
    }

    public function all(): array
    {
        return $this->inner->all();
    }

    public function findById(string $id): ?UrlRedirect
    {
        return $this->inner->findById($id);
    }

    public function findBySource(string $sourcePath): ?UrlRedirect
    {
        return $this->inner->findBySource($sourcePath);
    }

    public function enabledMap(): array
    {
        return $this->inner->enabledMap();
    }

    public function save(UrlRedirect $redirect): UrlRedirect
    {
        $this->inner->save((new UrlRedirect())->setSourcePath('/audit-' . $redirect->getSourcePath())->setTargetUrl('/audit')->setEnabled(false));

        throw new StorageLockTimeoutException($this->lockPath, 0.05);
    }

    public function delete(string $id): void
    {
        $this->inner->delete($id);
    }

    public function recordHit(string $sourcePath, DateTimeImmutable $at): void
    {
        $this->inner->recordHit($sourcePath, $at);
    }
}
