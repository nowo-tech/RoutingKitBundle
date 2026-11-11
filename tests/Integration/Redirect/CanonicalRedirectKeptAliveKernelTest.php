<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Redirect;

use Nowo\RoutingKitBundle\Model\AliasMode;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Routing\RouteCacheInvalidator;
use Nowo\RoutingKitBundle\Storage\RoutePathStorageInterface;
use Nowo\RoutingKitBundle\Tests\Integration\Redirect\App\RedirectTestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

use function file_put_contents;
use function gc_collect_cycles;
use function memory_get_usage;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

/**
 * REQ-RUNTIME-002: canonical / alias redirects in ONE kept-alive kernel without the services resetter.
 *
 * The loop drives `http_kernel` handle / terminate directly (never `Kernel::handle()`, which runs
 * `services_resetter`), the way a host that removes or bypasses the resetter does. Two locales ("tenants") alternate.
 * Half-way, another worker disables the English path: it writes the shared storage and bumps the shared route-table
 * version file, exactly what its RoutePathManager does; this worker's subscriber never sees the
 * RoutePathsChangedEvent. From the next request on, this worker must stop redirecting the English alias while the
 * Spanish one keeps redirecting.
 */
final class CanonicalRedirectKeptAliveKernelTest extends TestCase
{
    private const REQUESTS = 600;

    private const EDIT_AT = 300;

    private string $baseDir;

    private RedirectTestKernel $kernel;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/rk_canonical_kernel_' . uniqid('', true);
        mkdir($this->baseDir . '/templates', 0777, true);
        mkdir($this->baseDir . '/translations', 0777, true);
        $this->kernel = new RedirectTestKernel($this->baseDir);
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        (new Filesystem())->remove($this->baseDir);
    }

    public function testKeptAliveWorkerFollowsPathEditsMadeByAnotherWorker(): void
    {
        $storage = $this->service(RoutePathStorageInterface::class);
        $english = $storage->save(new RoutePathDefinition('contact', 'en', '/contact', CanonicalStyle::WithoutPrefix, aliasMode: AliasMode::Redirect));
        $storage->save(new RoutePathDefinition('contacto', 'es', '/contacto', CanonicalStyle::WithoutPrefix, aliasMode: AliasMode::Redirect));
        $versionFile = $this->service(RouteCacheInvalidator::class)->getVersionFile();

        $http = $this->kernel->getContainer()->get('http_kernel');
        self::assertInstanceOf(HttpKernelInterface::class, $http);
        self::assertInstanceOf(TerminableInterface::class, $http);
        $before = 0;

        for ($i = 0; $i < self::REQUESTS; ++$i) {
            if ($i === self::EDIT_AT) {
                // Another worker disables the English path: shared storage + shared route-table version only.
                $storage->save(new RoutePathDefinition('contact', 'en', '/contact', CanonicalStyle::WithoutPrefix, aliasMode: AliasMode::Redirect, enabled: false, id: $english->id));
                file_put_contents($versionFile, "edited-by-worker-b\n");
            }

            $englishTurn = $i % 2 === 0;
            $request     = Request::create($englishTurn ? '/en/contact' : '/es/contacto');
            $response    = $http->handle($request);

            if ($englishTurn) {
                if ($i < self::EDIT_AT) {
                    self::assertSame(301, $response->getStatusCode(), 'request #' . $i);
                    self::assertSame('/contact', $response->headers->get('Location'));
                } else {
                    self::assertFalse($response->isRedirect(), 'request #' . $i . ': the path disabled by another worker must not redirect.');
                }
            } else {
                self::assertSame(301, $response->getStatusCode(), 'request #' . $i);
                self::assertSame('/contacto', $response->headers->get('Location'), 'request #' . $i . ': the other locale is untouched.');
            }
            $http->terminate($request, $response);

            if ($i === 49) {
                gc_collect_cycles();
                $before = memory_get_usage();
            }
        }

        gc_collect_cycles();
        self::assertLessThan(2 * 1024 * 1024, memory_get_usage() - $before, 'Memory must stay flat over 550 requests.');
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $service = $container->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }
}
