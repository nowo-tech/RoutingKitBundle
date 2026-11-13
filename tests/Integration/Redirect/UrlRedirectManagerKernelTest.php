<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Redirect;

use Closure;
use Nowo\RoutingKitBundle\Event\UrlRedirectChangedEvent;
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
