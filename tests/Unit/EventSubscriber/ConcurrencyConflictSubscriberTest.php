<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\EventSubscriber;

use Exception;
use LogicException;
use Nowo\RoutingKitBundle\Concurrency\StorageWriteLedger;
use Nowo\RoutingKitBundle\Controller\RoutingPanelController;
use Nowo\RoutingKitBundle\EventSubscriber\ConcurrencyConflictSubscriber;
use Nowo\RoutingKitBundle\Storage\StorageLockTimeoutException;
use Nowo\TransactionContract\TransientStorageFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

final class ConcurrencyConflictSubscriberTest extends TestCase
{
    private const BUNDLE_CONTROLLER = RoutingPanelController::class . '::create';

    public function testSubscribesBeforeWritesAndBeforeTheErrorListener(): void
    {
        $events = ConcurrencyConflictSubscriber::getSubscribedEvents();
        self::assertSame(['onKernelRequest', 4097], $events[KernelEvents::REQUEST]);
        self::assertSame(['onKernelException', 0], $events[KernelEvents::EXCEPTION]);
    }

    public function testMarkIsTakenOnTheMainRequestOnly(): void
    {
        $ledger = new StorageWriteLedger();
        $ledger->record();
        $subscriber = new ConcurrencyConflictSubscriber($ledger);

        $main = new Request();
        $subscriber->onKernelRequest(new RequestEvent($this->kernel(), $main, HttpKernelInterface::MAIN_REQUEST));
        self::assertSame(1, $main->attributes->get(ConcurrencyConflictSubscriber::MARK_ATTRIBUTE));

        $sub = new Request();
        $subscriber->onKernelRequest(new RequestEvent($this->kernel(), $sub, HttpKernelInterface::SUB_REQUEST));
        self::assertFalse($sub->attributes->has(ConcurrencyConflictSubscriber::MARK_ATTRIBUTE));
    }

    public function testNothingWrittenAnswers409RetrySafeWithRetryAfter(): void
    {
        $ledger = new StorageWriteLedger();
        $answer = $this->answer(new ConcurrencyConflictSubscriber($ledger), $this->request($ledger), $failure = $this->timeout());

        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(['X-Conflict-Code' => 'CONFLICT_001', 'Cache-Control' => 'no-store', 'Retry-After' => '1'], $answer->getHeaders());
        self::assertSame($failure, $answer->getPrevious());
        self::assertStringContainsString('Try again', $answer->getMessage());
    }

    public function testWriteEarlierInTheRequestAnswersCommittedWithoutRetryAfter(): void
    {
        $ledger  = new StorageWriteLedger();
        $request = $this->request($ledger);
        $ledger->record();

        $answer = $this->answer(new ConcurrencyConflictSubscriber($ledger), $request, $this->timeout());

        self::assertSame(409, $answer->getStatusCode());
        self::assertSame(['X-Conflict-Code' => 'CONFLICT_002', 'Cache-Control' => 'no-store'], $answer->getHeaders());
        self::assertStringContainsString('already saved', $answer->getMessage());
    }

    public function testTransactionDoctrineCommitMarkTurnsTheAnswerIntoCommitted(): void
    {
        $ledger  = new StorageWriteLedger();
        $request = $this->request($ledger);
        $request->attributes->set(ConcurrencyConflictSubscriber::TRANSACTION_DOCTRINE_MARK_ATTRIBUTE, new class {
            public function committedSince(): bool
            {
                return true;
            }
        });

        $answer = $this->answer(new ConcurrencyConflictSubscriber($ledger), $request, $this->timeout());
        self::assertSame('CONFLICT_002', $answer->getHeaders()['X-Conflict-Code']);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideMarksThatProveNoCommit(): iterable
    {
        yield 'mark saw no commit' => [new class {
            public function committedSince(): bool
            {
                return false;
            }
        }];
        yield 'non-boolean answer' => [new class {
            public function committedSince(): string
            {
                return 'yes';
            }
        }];
        yield 'object without the method' => [new class {
        }];
        yield 'not an object' => ['committed'];
    }

    #[DataProvider('provideMarksThatProveNoCommit')]
    public function testHostMarkThatProvesNoCommitKeepsRetrySafe(mixed $mark): void
    {
        $ledger  = new StorageWriteLedger();
        $request = $this->request($ledger);
        $request->attributes->set(ConcurrencyConflictSubscriber::TRANSACTION_DOCTRINE_MARK_ATTRIBUTE, $mark);

        $answer = $this->answer(new ConcurrencyConflictSubscriber($ledger), $request, $this->timeout());
        self::assertSame('CONFLICT_001', $answer->getHeaders()['X-Conflict-Code']);
    }

    public function testWithoutAMarkTheFailureAloneDecides(): void
    {
        $ledger = new StorageWriteLedger();
        $ledger->record();
        $request = new Request();
        $request->attributes->set('_controller', self::BUNDLE_CONTROLLER);

        $answer = $this->answer(new ConcurrencyConflictSubscriber($ledger), $request, $this->timeout());
        self::assertSame('CONFLICT_001', $answer->getHeaders()['X-Conflict-Code']);
    }

    public function testConfiguredStatusAndRetryAfterAndTheFailureHint(): void
    {
        $ledger     = new StorageWriteLedger();
        $subscriber = new ConcurrencyConflictSubscriber($ledger, 503, 3);

        $answer = $this->answer($subscriber, $this->request($ledger), $this->timeout());
        self::assertSame(503, $answer->getStatusCode());
        self::assertSame('3', $answer->getHeaders()['Retry-After']);

        // A host storage's larger hint wins; a smaller one does not shorten the configured wait.
        self::assertSame('7', $this->answer($subscriber, $this->request($ledger), new TransientStorageFailure(7))->getHeaders()['Retry-After']);
        self::assertSame('3', $this->answer($subscriber, $this->request($ledger), new TransientStorageFailure(1))->getHeaders()['Retry-After']);
    }

    public function testFailureWrappedByAnotherExceptionIsFound(): void
    {
        $ledger  = new StorageWriteLedger();
        $wrapped = new LogicException('listener failed', 0, $this->timeout());

        $answer = $this->answer(new ConcurrencyConflictSubscriber($ledger), $this->request($ledger), $wrapped);
        self::assertSame('CONFLICT_001', $answer->getHeaders()['X-Conflict-Code']);
        self::assertSame($wrapped, $answer->getPrevious());
    }

    /**
     * @return iterable<string, array{string|null, Throwable}>
     */
    public static function provideFailuresLeftAlone(): iterable
    {
        $timeout = new StorageLockTimeoutException('/var/x.lock', 1.0);

        yield 'host route' => ['App\\Controller\\PageController::show', $timeout];
        yield 'closure or array controller' => [null, $timeout];
        yield 'duplicate refusal is not a conflict' => [self::BUNDLE_CONTROLLER, new RuntimeException('A redirect already exists for "/old".')];
        yield 'I/O error is not a conflict' => [self::BUNDLE_CONTROLLER, new RuntimeException('Unable to write redirect storage.')];
    }

    #[DataProvider('provideFailuresLeftAlone')]
    public function testOtherRoutesAndOtherFailuresAreLeftAlone(?string $controller, Throwable $failure): void
    {
        $ledger  = new StorageWriteLedger();
        $request = new Request();
        if ($controller !== null) {
            $request->attributes->set('_controller', $controller);
        } else {
            $request->attributes->set('_controller', [new stdClass(), 'create']);
        }

        $event = new ExceptionEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST, $failure);
        (new ConcurrencyConflictSubscriber($ledger))->onKernelException($event);

        self::assertSame($failure, $event->getThrowable());
    }

    public function testCyclicPreviousChainDoesNotHang(): void
    {
        $ledger = new StorageWriteLedger();
        $outer  = new RuntimeException('outer');
        $inner  = new RuntimeException('inner', 0, $outer);
        (new ReflectionProperty(Exception::class, 'previous'))->setValue($outer, $inner);

        $request = $this->request($ledger);
        $event   = new ExceptionEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST, $outer);
        (new ConcurrencyConflictSubscriber($ledger))->onKernelException($event);

        self::assertSame($outer, $event->getThrowable());
    }

    private function request(StorageWriteLedger $ledger): Request
    {
        $request = new Request();
        $request->attributes->set('_controller', self::BUNDLE_CONTROLLER);
        $request->attributes->set(ConcurrencyConflictSubscriber::MARK_ATTRIBUTE, $ledger->count());

        return $request;
    }

    private function timeout(): StorageLockTimeoutException
    {
        return new StorageLockTimeoutException('/var/routing_kit/paths.json.lock', 5.0);
    }

    private function answer(ConcurrencyConflictSubscriber $subscriber, Request $request, Throwable $failure): HttpException
    {
        $event = new ExceptionEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST, $failure);
        $subscriber->onKernelException($event);

        $answer = $event->getThrowable();
        self::assertInstanceOf(HttpException::class, $answer);

        return $answer;
    }

    private function kernel(): HttpKernelInterface
    {
        return $this->createStub(HttpKernelInterface::class);
    }
}
