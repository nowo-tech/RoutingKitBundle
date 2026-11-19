<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\EventSubscriber;

use Nowo\RoutingKitBundle\Concurrency\StorageWriteLedger;
use Nowo\RoutingKitBundle\Storage\StorageLockTimeoutException;
use Nowo\TransactionContract\TransientStorageFailure;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

use function is_bool;
use function is_int;
use function is_object;
use function is_string;
use function max;
use function method_exists;
use function str_starts_with;

/**
 * REQ-RUNTIME-003 rule 6 for the bundle's own admin routes: a {@see TransientStorageFailure} (a storage lock wait that
 * ran out, {@see StorageLockTimeoutException}, or the same failure thrown by a host
 * storage) becomes a 409 (or the configured 503) with `X-Conflict-Code`:
 *
 * - `CONFLICT_001` + `Retry-After` when nothing was written in this request;
 * - `CONFLICT_002`, no `Retry-After`, when the request already wrote: a filesystem store committed since the mark
 *   taken at the main kernel.request ({@see StorageWriteLedger}), or transaction-doctrine's request CommitMark
 *   (attribute `_nowo_transaction_doctrine_commit_mark`, when the host installed it) saw a commit.
 *
 * The answer is an HttpException, so the host's error page renders it (HTML or JSON) and the headers ride along. Only
 * requests whose `_controller` is one of this bundle's controllers are answered: host routes keep their own handling.
 * Any other failure (unique-key refusals, I/O errors) is left alone.
 *
 * Stateless: the mark lives on the request, the ledger is read by difference.
 */
final readonly class ConcurrencyConflictSubscriber implements EventSubscriberInterface
{
    public const CODE_HEADER = 'X-Conflict-Code';

    public const RETRY_SAFE = 'CONFLICT_001';

    public const COMMITTED = 'CONFLICT_002';

    /** Request attribute holding the ledger count at the main kernel.request. */
    public const MARK_ATTRIBUTE = '_nowo_routing_kit_write_mark';

    /** transaction-doctrine >= 1.6 request CommitMark (read duck-typed: the bundle does not require it). */
    public const TRANSACTION_DOCTRINE_MARK_ATTRIBUTE = '_nowo_transaction_doctrine_commit_mark';

    private const CONTROLLER_NAMESPACE = 'Nowo\\RoutingKitBundle\\Controller\\';

    /** Most throwables walked along getPrevious() (a cycle cannot hang a worker). */
    private const MAX_CHAIN = 32;

    public function __construct(
        private StorageWriteLedger $ledger,
        private int $status = 409,
        private int $retryAfter = 1,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Before anything of the request can write (RouteTableFreshnessSubscriber runs at 4096 too, and only reads).
            KernelEvents::REQUEST => ['onKernelRequest', 4097],
            // Before the framework ErrorListener (-128) renders the failure.
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $event->getRequest()->attributes->set(self::MARK_ATTRIBUTE, $this->ledger->count());
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->isBundleController($request)) {
            return;
        }

        $failure = $this->transientFailure($event->getThrowable());
        if (!$failure instanceof TransientStorageFailure) {
            return;
        }

        $code    = $this->committedInRequest($request) ? self::COMMITTED : self::RETRY_SAFE;
        $headers = [self::CODE_HEADER => $code, 'Cache-Control' => 'no-store'];
        if ($code === self::RETRY_SAFE) {
            $headers['Retry-After'] = (string) max($this->retryAfter, $failure->retryAfterSeconds() ?? 0);
        }

        $message = $code === self::RETRY_SAFE
            ? 'The routing data is being changed by another request. Try again in a moment.'
            : 'The routing data is being changed by another request and part of this request was already saved. Reload the page before trying again.';

        $event->setThrowable(new HttpException($this->status, $message, $event->getThrowable(), $headers));
    }

    private function isBundleController(Request $request): bool
    {
        $controller = $request->attributes->get('_controller');

        return is_string($controller) && str_starts_with($controller, self::CONTROLLER_NAMESPACE);
    }

    private function transientFailure(Throwable $throwable): ?TransientStorageFailure
    {
        for ($i = 0, $current = $throwable; $current instanceof Throwable && $i < self::MAX_CHAIN; ++$i, $current = $current->getPrevious()) {
            if ($current instanceof TransientStorageFailure) {
                return $current;
            }
        }

        return null;
    }

    private function committedInRequest(Request $request): bool
    {
        $mark = $request->attributes->get(self::MARK_ATTRIBUTE);
        if (is_int($mark) && $this->ledger->count() !== $mark) {
            return true;
        }

        $hostMark = $request->attributes->get(self::TRANSACTION_DOCTRINE_MARK_ATTRIBUTE);
        if (is_object($hostMark) && method_exists($hostMark, 'committedSince')) {
            $committed = $hostMark->committedSince();

            return is_bool($committed) && $committed;
        }

        return false;
    }
}
