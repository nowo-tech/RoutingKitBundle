<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Integration\Redirect;

use Closure;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectData;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectManager;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectManagerInterface;
use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;
use Nowo\RoutingKitBundle\Tests\Integration\Redirect\App\RacingUrlRedirectManager;
use Nowo\RoutingKitBundle\Tests\Integration\Redirect\App\RedirectTestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * Two operators have the create form open: A saves `/old → /new`, B submits `/old` a second later. B's pre-check
 * passes, the manager's second validation refuses the write, and the panel has to turn that refusal into a field
 * error — never a 500 on a write that was correctly refused.
 */
final class UrlRedirectPanelRaceKernelTest extends TestCase
{
    private string $baseDir;

    private ?RedirectTestKernel $kernel = null;

    /** @var array<string, string> */
    private array $cookies = [];

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/rk_redirect_race_' . uniqid('', true);
        mkdir($this->baseDir . '/templates', 0777, true);
        mkdir($this->baseDir . '/translations', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        (new Filesystem())->remove($this->baseDir);
    }

    public function testConcurrentDuplicateSourceBecomesAFieldErrorOnTheForm(): void
    {
        $container = $this->boot();

        // Operator A's row, written through the real manager before B submits.
        $real = $container->get(UrlRedirectManager::class);
        self::assertInstanceOf(UrlRedirectManagerInterface::class, $real);
        $a = $real->save(new UrlRedirectData('/old', '/new', 301), actorId: 'operator-a');

        // Operator B: the pre-check passes, the manager's own validation refuses the duplicate source.
        $response = $this->submit('/old', '/other');
        self::assertSame(200, $response->getStatusCode(), 'A refused concurrent write must re-render the form, not fail.');
        $html = html_entity_decode((string) $response->getContent());
        self::assertStringContainsString('already a redirect', $html);

        // Nothing written: A's row is untouched and there is still exactly one row.
        $storage = $container->get(UrlRedirectStorageInterface::class);
        self::assertInstanceOf(UrlRedirectStorageInterface::class, $storage);
        self::assertCount(1, $storage->all());
        self::assertSame('/new', $storage->findById((string) $a->getId())?->getTargetUrl());
    }

    public function testRefusalReportedOnAFieldTheFormDoesNotHaveStaysOnTheForm(): void
    {
        $container = $this->boot('tenant_id');

        $real = $container->get(UrlRedirectManager::class);
        self::assertInstanceOf(UrlRedirectManagerInterface::class, $real);
        $real->save(new UrlRedirectData('/old', '/new', 301));

        $response = $this->submit('/old', '/other');
        self::assertSame(200, $response->getStatusCode(), 'An unknown error key must not 500 the panel.');
        self::assertStringContainsString('already a redirect', html_entity_decode((string) $response->getContent()));

        $storage = $container->get(UrlRedirectStorageInterface::class);
        self::assertInstanceOf(UrlRedirectStorageInterface::class, $storage);
        self::assertCount(1, $storage->all());
    }

    /**
     * A decimal integer-like key is an int by the time it reaches the loop, which `FormInterface::has()` rejects
     * under strict_types. Contrived, but it is the same 500 the guard exists to prevent.
     */
    public function testRefusalReportedOnANumericFieldKeyStaysOnTheForm(): void
    {
        $container = $this->boot('0');

        $real = $container->get(UrlRedirectManager::class);
        self::assertInstanceOf(UrlRedirectManagerInterface::class, $real);
        $real->save(new UrlRedirectData('/old', '/new', 301));

        $response = $this->submit('/old', '/other');
        self::assertSame(200, $response->getStatusCode(), 'A numeric error key must not 500 the panel.');
        self::assertStringContainsString('already a redirect', html_entity_decode((string) $response->getContent()));

        $storage = $container->get(UrlRedirectStorageInterface::class);
        self::assertInstanceOf(UrlRedirectStorageInterface::class, $storage);
        self::assertCount(1, $storage->all());
    }

    private function boot(?string $unknownField = null): ContainerInterface
    {
        $host = static function (ContainerConfigurator $config) use ($unknownField): void {
            $services = $config->services()->defaults()->autowire();
            $services->set(RacingUrlRedirectManager::class)
                ->arg('$inner', service(UrlRedirectManager::class))
                ->arg('$unknownField', $unknownField);
            $services->alias(UrlRedirectManagerInterface::class, RacingUrlRedirectManager::class);
        };

        $this->kernel = new RedirectTestKernel($this->baseDir, true, Closure::fromCallable($host));
        $this->kernel->boot();
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }

    private function submit(string $source, string $target): Response
    {
        $form = (string) $this->request('GET', '/_routing/redirects/new')->getContent();

        return $this->request('POST', '/_routing/redirects/new', ['url_redirect' => [
            'source_path' => $source,
            'target_url'  => $target,
            'status_code' => '301',
            'enabled'     => '1',
            '_csrf_token' => $this->token($form, 'url_redirect[_csrf_token]'),
        ]]);
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
