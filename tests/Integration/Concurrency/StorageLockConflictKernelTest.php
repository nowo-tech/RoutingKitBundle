<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Concurrency;

use Closure;
use Nowo\RoutingKitBundle\Storage\FilesystemUrlRedirectStorage;
use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;
use Nowo\RoutingKitBundle\Tests\Integration\Concurrency\App\CommitThenLockedUrlRedirectStorage;
use Nowo\RoutingKitBundle\Tests\Integration\Redirect\App\RedirectTestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function dirname;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use const LOCK_EX;
use const LOCK_UN;

/**
 * REQ-RUNTIME-003 rule 6 end to end: another worker holds the storage lock longer than `storage.lock_timeout`. The
 * bundle's admin routes answer 409 + X-Conflict-Code (CONFLICT_001 with Retry-After when nothing was written,
 * CONFLICT_002 without it when the request already wrote), the host's routes are never answered by the bundle, and
 * the stored data is unchanged.
 */
final class StorageLockConflictKernelTest extends TestCase
{
    private string $baseDir;

    private ?RedirectTestKernel $kernel = null;

    /** @var array<string, string> */
    private array $cookies = [];

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/rk_lock_conflict_' . uniqid('', true);
        mkdir($this->baseDir . '/templates', 0777, true);
        mkdir($this->baseDir . '/translations', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        (new Filesystem())->remove($this->baseDir);
    }

    public function testHeldLockAnswersRetrySafeConflictOnAdminRoutesAndWritesNothing(): void
    {
        $storage = $this->boot()->get(UrlRedirectStorageInterface::class);
        self::assertInstanceOf(UrlRedirectStorageInterface::class, $storage);

        $form   = (string) $this->request('GET', '/_routing/redirects/new')->getContent();
        $token  = $this->token($form, 'url_redirect[_csrf_token]');
        $holder = $this->holdLock($this->baseDir . '/var/redirects.json.lock');

        try {
            $post = $this->request('POST', '/_routing/redirects/new', ['url_redirect' => [
                'source_path' => '/old',
                'target_url'  => '/new',
                'status_code' => '301',
                'enabled'     => '1',
                '_csrf_token' => $token,
            ]]);
            $this->assertRetrySafeConflict($post);

            $this->assertRetrySafeConflict($this->request('GET', '/_routing/redirects'));

            // A host route: the bundle never answers it (the redirect matcher fails open, the page is served).
            $page = $this->request('GET', '/contacto');
            self::assertSame(200, $page->getStatusCode());
            self::assertSame('page:contacto', $page->getContent());
            self::assertFalse($page->headers->has('X-Conflict-Code'));
        } finally {
            flock($holder, LOCK_UN);
            fclose($holder);
        }

        self::assertSame([], $storage->all(), 'Nothing was written.');

        // Once the lock is free the same request succeeds.
        $form = (string) $this->request('GET', '/_routing/redirects/new')->getContent();
        $ok   = $this->request('POST', '/_routing/redirects/new', ['url_redirect' => [
            'source_path' => '/old',
            'target_url'  => '/new',
            'status_code' => '301',
            'enabled'     => '1',
            '_csrf_token' => $this->token($form, 'url_redirect[_csrf_token]'),
        ]]);
        self::assertSame(302, $ok->getStatusCode());
        self::assertCount(1, $storage->all());
    }

    public function testConflictAfterAWriteOfTheSameRequestAnswersCommitted(): void
    {
        $lock = $this->baseDir . '/var/redirects.json.lock';
        $host = static function (ContainerConfigurator $config) use ($lock): void {
            $config->services()->set(CommitThenLockedUrlRedirectStorage::class)
                ->arg('$inner', service(FilesystemUrlRedirectStorage::class))
                ->arg('$lockPath', $lock);
            $config->extension('nowo_routing_kit', ['url_redirects' => ['storage' => CommitThenLockedUrlRedirectStorage::class]]);
        };
        $container = $this->boot(Closure::fromCallable($host));

        $form = (string) $this->request('GET', '/_routing/redirects/new')->getContent();
        $post = $this->request('POST', '/_routing/redirects/new', ['url_redirect' => [
            'source_path' => '/old',
            'target_url'  => '/new',
            'status_code' => '301',
            'enabled'     => '1',
            '_csrf_token' => $this->token($form, 'url_redirect[_csrf_token]'),
        ]]);

        self::assertSame(409, $post->getStatusCode());
        self::assertSame('CONFLICT_002', $post->headers->get('X-Conflict-Code'));
        self::assertFalse($post->headers->has('Retry-After'), 'Part was written: do not invite a blind retry.');

        $inner = $container->get(FilesystemUrlRedirectStorage::class);
        self::assertInstanceOf(FilesystemUrlRedirectStorage::class, $inner);
        self::assertCount(1, $inner->all(), 'The first step stands; the request reported it instead of a success.');
        self::assertNull($inner->findBySource('/old'));
    }

    public function testStatusCanBeConfiguredTo503(): void
    {
        $host = static function (ContainerConfigurator $config): void {
            $config->extension('nowo_routing_kit', ['conflict_response' => ['status' => 503, 'retry_after' => 4]]);
        };
        $this->boot(Closure::fromCallable($host));

        $holder = $this->holdLock($this->baseDir . '/var/redirects.json.lock');
        try {
            $index = $this->request('GET', '/_routing/redirects');
        } finally {
            flock($holder, LOCK_UN);
            fclose($holder);
        }

        self::assertSame(503, $index->getStatusCode());
        self::assertSame('CONFLICT_001', $index->headers->get('X-Conflict-Code'));
        self::assertSame('4', $index->headers->get('Retry-After'));
    }

    private function assertRetrySafeConflict(Response $response): void
    {
        self::assertSame(409, $response->getStatusCode());
        self::assertSame('CONFLICT_001', $response->headers->get('X-Conflict-Code'));
        self::assertSame('1', $response->headers->get('Retry-After'));
    }

    /**
     * @return resource
     */
    private function holdLock(string $path)
    {
        @mkdir(dirname($path), 0777, true);
        $handle = fopen($path, 'c+');
        self::assertIsResource($handle);
        self::assertTrue(flock($handle, LOCK_EX));

        return $handle;
    }

    private function boot(?Closure $more = null): ContainerInterface
    {
        $host = static function (ContainerConfigurator $config) use ($more): void {
            $config->extension('nowo_routing_kit', [
                'storage' => ['lock_timeout' => 0.05],
                // No shared cache: every request reads the store, so the lock is met on the first read.
                'url_redirects' => ['cache_pool' => null],
            ]);
            if ($more instanceof Closure) {
                $more($config);
            }
        };

        $this->kernel = new RedirectTestKernel($this->baseDir, true, Closure::fromCallable($host));
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    private function token(string $html, string $name): string
    {
        $pattern = '/name="' . preg_quote(htmlspecialchars($name), '/') . '"[^>]*value="([^"]+)"/';
        self::assertSame(1, preg_match($pattern, $html, $m), 'CSRF field ' . $name . ' rendered.');

        return $m[1];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function request(string $method, string $uri, array $parameters = []): Response
    {
        self::assertInstanceOf(RedirectTestKernel::class, $this->kernel);
        $request  = Request::create($uri, $method, $parameters, $this->cookies);
        $response = $this->kernel->handle($request);
        foreach ($response->headers->getCookies() as $cookie) {
            $this->cookies[$cookie->getName()] = (string) $cookie->getValue();
        }
        $this->kernel->terminate($request, $response);

        return $response;
    }
}
