<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Templates;

use Closure;
use DateTimeImmutable;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\Tests\Support\PanelTemplateRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function count;
use function dirname;

/**
 * BC guard for the admin chrome hooks (spec 003): with no web_ui.classes / partials configured the panel renders
 * the v1.6 markup (whitespace aside). Regenerate on intentional change with
 * `UPDATE_SNAPSHOTS=1 vendor/bin/phpunit --filter PanelDefaultOutputTest`.
 */
final class PanelDefaultOutputTest extends TestCase
{
    #[DataProvider('cases')]
    public function testDefaultOutputMatchesSnapshot(string $snapshot, string $template, Closure $context): void
    {
        $html = PanelTemplateRenderer::normalize((new PanelTemplateRenderer())->render($template, $context()));
        $file = dirname(__DIR__, 2) . '/fixtures/templates/' . $snapshot . '.html';

        if (getenv('UPDATE_SNAPSHOTS') === '1') {
            file_put_contents($file, $html);
        }
        self::assertFileExists($file, 'Missing snapshot; run with UPDATE_SNAPSHOTS=1 once.');
        self::assertSame((string) file_get_contents($file), $html);
    }

    /**
     * @return iterable<string, array{string, string, Closure(): array<string, mixed>}>
     */
    public static function cases(): iterable
    {
        yield 'index (rows)' => ['index_rows', '@NowoRoutingKitBundle/panel/index.html.twig', static fn (): array => self::indexContext([
            new RoutePathDefinition('app_home', 'en', '/home', id: 'rk_1'),
            new RoutePathDefinition('app_about', 'es', '/sobre', enabled: false, id: 'rk_2'),
        ])];
        yield 'index (empty, role gate disabled)' => ['index_empty', '@NowoRoutingKitBundle/panel/index.html.twig', static fn (): array => self::indexContext([], true)];
        yield 'form (create, errors)' => ['form_create', '@NowoRoutingKitBundle/panel/form.html.twig', static fn (): array => self::formContext(null, ['Invalid path'], ['Conflicts with app_x'])];
        yield 'form (edit)' => ['form_edit', '@NowoRoutingKitBundle/panel/form.html.twig', static fn (): array => self::formContext(new RoutePathDefinition('app_home', 'en', '/home', id: 'rk_1'), [], [])];
        yield 'redirects index' => ['redirects_index', '@NowoRoutingKitBundle/panel/redirects/index.html.twig', static fn (): array => self::redirectsContext([self::redirect('r1', true), self::redirect('r2', false)], 'created')];
        yield 'redirects index (empty)' => ['redirects_index_empty', '@NowoRoutingKitBundle/panel/redirects/index.html.twig', static fn (): array => self::redirectsContext([], null)];
        yield 'redirects form' => ['redirects_form', '@NowoRoutingKitBundle/panel/redirects/form.html.twig', static fn (): array => [
            'redirect'    => null,
            'form'        => PanelTemplateRenderer::form('url_redirect', ['sourcePath', 'targetUrl']),
            'path_prefix' => '/_routing-kit',
        ]];
        yield 'redirects delete' => ['redirects_delete', '@NowoRoutingKitBundle/panel/redirects/delete.html.twig', static fn (): array => [
            'redirect'    => self::redirect('r1', true),
            'form'        => PanelTemplateRenderer::form('', ['_token']),
            'path_prefix' => '/_routing-kit',
        ]];
    }

    /**
     * @param list<RoutePathDefinition> $definitions
     *
     * @return array<string, mixed>
     */
    public static function indexContext(array $definitions, bool $roleGateDisabled = false): array
    {
        $deleteForms = [];
        foreach ($definitions as $definition) {
            $deleteForms[(string) $definition->id] = PanelTemplateRenderer::form('', ['_token']);
        }

        return [
            'definitions'        => $definitions,
            'path_prefix'        => '/_routing-kit',
            'locales'            => ['en', 'es'],
            'default'            => 'en',
            'export_form'        => PanelTemplateRenderer::form('', ['_token']),
            'clear_cache_form'   => PanelTemplateRenderer::form('', ['_token']),
            'import_form'        => PanelTemplateRenderer::form('routing_panel_import', ['payload_json', 'replace_all', '_token']),
            'delete_forms'       => $deleteForms,
            'role_gate_disabled' => $roleGateDisabled,
            'page'               => 1,
            'pages'              => 1,
            'total'              => count($definitions),
            'page_size'          => 50,
            'item_count'         => count($definitions),
        ];
    }

    /**
     * @param list<string> $errors
     * @param list<string> $conflicts
     *
     * @return array<string, mixed>
     */
    public static function formContext(?RoutePathDefinition $definition, array $errors, array $conflicts): array
    {
        return [
            'definition'         => $definition,
            'form'               => PanelTemplateRenderer::form('route_path_definition', ['route_name', 'locale', 'path']),
            'path_prefix'        => '/_routing-kit',
            'errors'             => $errors,
            'conflicts'          => $conflicts,
            'role_gate_disabled' => false,
        ];
    }

    /**
     * @param list<UrlRedirect> $redirects
     *
     * @return array<string, mixed>
     */
    public static function redirectsContext(array $redirects, ?string $status): array
    {
        return [
            'redirects'   => $redirects,
            'path_prefix' => '/_routing-kit',
            'status'      => $status,
            'page'        => 1,
            'pages'       => 1,
            'total'       => count($redirects),
            'page_size'   => 50,
            'item_count'  => count($redirects),
        ];
    }

    public static function redirect(string $id, bool $enabled): UrlRedirect
    {
        return (new UrlRedirect($id))
            ->setSourcePath('/old-' . $id)
            ->setTargetUrl('/new-' . $id)
            ->setStatusCode(301)
            ->setEnabled($enabled)
            ->setHits($enabled ? 3 : 0)
            ->setLastHitAt($enabled ? new DateTimeImmutable('2026-10-01 10:00:00') : null)
            ->setNote('note ' . $id);
    }
}
