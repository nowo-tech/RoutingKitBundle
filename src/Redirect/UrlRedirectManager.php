<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Redirect;

use DateTimeImmutable;
use Nowo\RoutingKitBundle\Event\UrlRedirectChangedEvent;
use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;
use OutOfBoundsException;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

use function sprintf;

/**
 * Default {@see UrlRedirectManagerInterface} (since 1.17.0): validate → storage write → matcher cache invalidation →
 * {@see UrlRedirectChangedEvent}. The event is dispatched after the storage write returned (the storage port commits
 * before it returns); a listener that throws is logged (its class only) and the write stands.
 *
 * Stateless: safe to share across the requests of a FrankenPHP worker.
 */
final readonly class UrlRedirectManager implements UrlRedirectManagerInterface
{
    public function __construct(
        private UrlRedirectStorageInterface $storage,
        private UrlRedirectValidator $validator,
        private ?UrlRedirectMatcherInterface $matcher = null,
        private ?EventDispatcherInterface $eventDispatcher = null,
        private ?ClockInterface $clock = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function validate(UrlRedirectData $data, ?string $currentId = null): array
    {
        return $this->validator->validate($data->sourcePath, $data->targetUrl, $data->statusCode, $data->note, $currentId);
    }

    public function save(UrlRedirectData $data, ?string $id = null, ?string $actorId = null): UrlRedirect
    {
        $existing = null;
        if ($id !== null) {
            $existing = $this->storage->findById($id);
            if (!$existing instanceof UrlRedirect) {
                throw new OutOfBoundsException(sprintf('URL redirect "%s" not found.', $id));
            }
        }

        $errors = $this->validate($data, $existing?->getId());
        if ($errors !== []) {
            throw new InvalidUrlRedirectException($errors);
        }

        $now      = $this->now();
        $redirect = $existing ?? (new UrlRedirect())->setCreatedAt($now)->setCreatedBy($actorId);
        // @igor-ignore - Per-call model loaded from storage, not shared service state.
        $redirect
            ->setSourcePath($data->sourcePath)
            ->setTargetUrl($data->targetUrl)
            ->setStatusCode($data->statusCode)
            ->setEnabled($data->enabled)
            ->setNote($data->note)
            ->setUpdatedAt($now)
            ->setUpdatedBy($actorId);

        $saved = $this->storage->save($redirect);
        $this->matcher?->invalidate();

        $this->dispatch(
            (string) $saved->getId(),
            $saved->getSourcePath(),
            $existing instanceof UrlRedirect ? UrlRedirectChangedEvent::ACTION_UPDATED : UrlRedirectChangedEvent::ACTION_CREATED,
            $actorId,
            $now,
        );

        return $saved;
    }

    public function delete(string $id, ?string $actorId = null): bool
    {
        $existing = $this->storage->findById($id);
        if (!$existing instanceof UrlRedirect) {
            return false;
        }

        $this->storage->delete($id);
        $this->matcher?->invalidate();
        $this->dispatch($id, $existing->getSourcePath(), UrlRedirectChangedEvent::ACTION_DELETED, $actorId, $this->now());

        return true;
    }

    /**
     * @param UrlRedirectChangedEvent::ACTION_* $action
     */
    private function dispatch(string $id, string $sourcePath, string $action, ?string $actorId, DateTimeImmutable $at): void
    {
        try {
            $this->eventDispatcher?->dispatch(new UrlRedirectChangedEvent($id, $sourcePath, $action, $actorId, $at));
        } catch (Throwable $failure) {
            $this->logger?->error('RoutingKit: URL redirect {action} event listener failed: {error}', ['action' => $action, 'error' => $failure::class, 'redirect_id' => $id]);
        }
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock?->now() ?? new DateTimeImmutable();
    }
}
