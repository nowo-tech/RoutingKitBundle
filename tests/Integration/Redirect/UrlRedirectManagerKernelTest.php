<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Redirect;

use Closure;
use Nowo\RoutingKitBundle\Event\RoutePathDefinitionChangedEvent;
use Nowo\RoutingKitBundle\Event\RoutePathsChangedEvent;
use Nowo\RoutingKitBundle\Event\UrlRedirectChangedEvent;
use Nowo\RoutingKitBundle\EventSubscriber\CanonicalRedirectSubscriber;
use Nowo\RoutingKitBundle\EventSubscriber\RoutePathAuditSubscriber;
use Nowo\RoutingKitBundle\Redirect\InvalidUrlRedirectException;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectData;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectManager;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectManagerInterface;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectMatcher;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectMatcherInterface;
use Nowo\RoutingKitBundle\Routing\PublicPathResolver;
use Nowo\RoutingKitBundle\Routing\PublicPathResolverInterface;
use Nowo\RoutingKitBundle\Service\RoutePathManager;
use Nowo\RoutingKitBundle\Service\RoutePathManagerInterface;
use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;
use Nowo\RoutingKitBundle\Tests\Integration\Redirect\App\RecordingRoutePathManager;
use Nowo\RoutingKitBundle\Tests\Integration\Redirect\App\RedirectTestKernel;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

use function is_array;
use function is_object;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * REQ-ARCH-001 rules 1, 3, 4, 5 on a real kernel: the application services are reachable by interface, a host can
 * replace them by aliasing the interface, redirect writes from application code validate, invalidate the matcher
 * and dispatch the id-only event (the route path twin event is covered by RoutePathManagerTest).
 */
final class UrlRedirectManagerKernelTest extends TestCase
{
    private string $baseDir;

    private ?RedirectTestKernel $kernel = null;

    /** @var list<object> */
    private array $events = [];

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/rk_redirect_manager_' . uniqid('', true);
        mkdir($this->baseDir . '/templates', 0777, true);
        mkdir($this->baseDir . '/translations', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        (new Filesystem())->remove($this->baseDir);
    }

    public function testInterfacesResolveToTheDefaults(): void
    {
        $container = $this->boot();

        self::assertInstanceOf(UrlRedirectManager::class, $container->get(UrlRedirectManagerInterface::class));
        self::assertInstanceOf(UrlRedirectMatcher::class, $container->get(UrlRedirectMatcherInterface::class));
        self::assertInstanceOf(RoutePathManager::class, $container->get(RoutePathManagerInterface::class));
        self::assertInstanceOf(PublicPathResolver::class, $container->get(PublicPathResolverInterface::class));
        // The class ids keep working.
        self::assertSame($container->get(RoutePathManager::class), $container->get(RoutePathManagerInterface::class));
    }

    public function testApplicationCodeCreatesUpdatesAndDeletesARedirect(): void
    {
        $container = $this->boot();
        $manager   = $this->manager($container);
        $matcher   = $container->get(UrlRedirectMatcherInterface::class);
        self::assertInstanceOf(UrlRedirectMatcherInterface::class, $matcher);
        self::assertNull($matcher->match('/old-page'));

        $created = $manager->save(new UrlRedirectData('/old-page/', '/contact', 301, true, 'import'), actorId: 'importer');
        self::assertNotNull($created->getId());
        self::assertSame('importer', $created->getCreatedBy());
        // Cache invalidated by the manager: the next lookup sees the new row.
        self::assertSame(['target' => '/contact', 'status' => 301], $matcher->match('/old-page'));

        $updated = $manager->save(new UrlRedirectData('/old-page', '/contacto', 302), $created->getId(), 'editor');
        self::assertSame($created->getId(), $updated->getId());
        self::assertSame('importer', $updated->getCreatedBy());
        self::assertSame('editor', $updated->getUpdatedBy());
        self::assertSame(['target' => '/contacto', 'status' => 302], $matcher->match('/old-page'));

        self::assertTrue($manager->delete($created->getId(), 'editor'));
        self::assertFalse($manager->delete($created->getId()));
        self::assertNull($matcher->match('/old-page'));

        $events = $this->eventsOf(UrlRedirectChangedEvent::class);
        self::assertSame(
            [
                [UrlRedirectChangedEvent::ACTION_CREATED, 'importer'],
                [UrlRedirectChangedEvent::ACTION_UPDATED, 'editor'],
                [UrlRedirectChangedEvent::ACTION_DELETED, 'editor'],
            ],
            array_map(static fn (UrlRedirectChangedEvent $e): array => [$e->action, $e->actorId], $events),
        );
        foreach ($events as $event) {
            self::assertSame($created->getId(), $event->redirectId);
            self::assertSame('/old-page', $event->sourcePath);
        }
    }

    public function testInvalidDataWritesNothingAndDispatchesNothing(): void
    {
        $container = $this->boot();
        $manager   = $this->manager($container);

        self::assertArrayHasKey('source_path', $manager->validate(new UrlRedirectData('/_routing/x', '/')));

        try {
            $manager->save(new UrlRedirectData('/a', '/a'));
            self::fail('A self-loop must be rejected.');
        } catch (InvalidUrlRedirectException $exception) {
            self::assertNotSame([], $exception->getErrors());
        }

        $storage = $container->get(UrlRedirectStorageInterface::class);
        self::assertInstanceOf(UrlRedirectStorageInterface::class, $storage);
        self::assertSame([], $storage->all());
        self::assertSame([], $this->eventsOf(UrlRedirectChangedEvent::class));

        $this->expectException(OutOfBoundsException::class);
        $manager->save(new UrlRedirectData('/b', '/contact'), 'missing-id');
    }

    /**
     * The target rules are reachable from host code now that the manager is public API: nothing else pins them at
     * this level, so a scheme-relative or javascript: target must be refused by the manager itself.
     *
     * @dataProvider unsafeTargets
     */
    public function testManagerRefusesUnsafeTargets(string $target): void
    {
        $container = $this->boot();
        $manager   = $this->manager($container);

        self::assertSame(['target_url' => 'redirects.error.target_invalid'], $manager->validate(new UrlRedirectData('/old', $target)));

        try {
            $manager->save(new UrlRedirectData('/old', $target));
            self::fail($target . ' must be refused.');
        } catch (InvalidUrlRedirectException $exception) {
            self::assertSame(['target_url' => 'redirects.error.target_invalid'], $exception->getErrors());
        }

        $storage = $container->get(UrlRedirectStorageInterface::class);
        self::assertInstanceOf(UrlRedirectStorageInterface::class, $storage);
        self::assertSame([], $storage->all());
        self::assertSame([], $this->eventsOf(UrlRedirectChangedEvent::class));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeTargets(): iterable
    {
        yield 'protocol-relative' => ['//evil.com'];
        yield 'protocol-relative path' => ['//evil.com/promo'];
        yield 'backslash-relative' => ['/\\evil.com'];
        yield 'javascript scheme' => ['javascript:alert(1)'];
        yield 'javascript scheme spaced' => [' javascript:alert(1)'];
        yield 'data scheme' => ['data:text/html,<script>1</script>'];
    }

    public function testListenerFailureIsLoggedAndTheWriteStands(): void
    {
        $container  = $this->boot();
        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        $dispatcher->addListener(UrlRedirectChangedEvent::class, static function (): never {
            throw new RuntimeException('projection down');
        });

        $saved = $this->manager($container)->save(new UrlRedirectData('/gone', '/contact'));

        $storage = $container->get(UrlRedirectStorageInterface::class);
        self::assertInstanceOf(UrlRedirectStorageInterface::class, $storage);
        self::assertSame($saved->getId(), $storage->findBySource('/gone')?->getId());
    }

    /**
     * A host storage adapter that writes rows outside RoutePathManager and dispatches only the deprecated
     * RoutePathsChangedEvent still makes the bundle drop its canonical path table (kept until 2.0). The audit
     * subscriber deliberately stays on the new event only: listening to both would log every manager write twice.
     */
    public function testDispatchingTheDeprecatedEventStillReachesTheCanonicalSubscriberOnly(): void
    {
        $container  = $this->boot();
        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $listeners = array_map(
            static fn (array|callable $listener): string => is_array($listener) && is_object($listener[0]) ? $listener[0]::class : 'closure',
            $dispatcher->getListeners(RoutePathsChangedEvent::class),
        );
        self::assertContains(CanonicalRedirectSubscriber::class, $listeners);
        self::assertNotContains(RoutePathAuditSubscriber::class, $listeners);

        $newListeners = array_map(
            static fn (array|callable $listener): string => is_array($listener) && is_object($listener[0]) ? $listener[0]::class : 'closure',
            $dispatcher->getListeners(RoutePathDefinitionChangedEvent::class),
        );
        self::assertContains(CanonicalRedirectSubscriber::class, $newListeners);
        self::assertContains(RoutePathAuditSubscriber::class, $newListeners);
    }

    public function testHostReplacesTheRoutePathManagerByAliasingTheInterface(): void
    {
        $container = $this->boot(static function (ContainerConfigurator $config): void {
            $services = $config->services()->defaults()->autowire();
            $services->set(RecordingRoutePathManager::class)->arg('$inner', service(RoutePathManager::class));
            $services->alias(RoutePathManagerInterface::class, RecordingRoutePathManager::class);
        });

        $manager = $container->get(RoutePathManagerInterface::class);
        self::assertInstanceOf(RecordingRoutePathManager::class, $manager);
        self::assertSame([], $manager->all());
        self::assertSame(['all'], $manager->calls);
    }

    /**
     * @param (Closure(ContainerConfigurator): void)|null $host
     */
    private function boot(?Closure $host = null): ContainerInterface
    {
        $this->kernel = new RedirectTestKernel($this->baseDir, true, $host);
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
        foreach ([UrlRedirectChangedEvent::class] as $eventClass) {
            $dispatcher->addListener($eventClass, function (object $event): void {
                $this->events[] = $event;
            }, 100);
        }

        return $container;
    }

    private function manager(ContainerInterface $container): UrlRedirectManagerInterface
    {
        $manager = $container->get(UrlRedirectManagerInterface::class);
        self::assertInstanceOf(UrlRedirectManagerInterface::class, $manager);

        return $manager;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function eventsOf(string $class): array
    {
        return array_values(array_filter($this->events, static fn (object $e): bool => $e instanceof $class));
    }
}
