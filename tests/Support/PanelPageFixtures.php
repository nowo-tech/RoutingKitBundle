<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Support;

use DateTimeImmutable;
use Nowo\RoutingKitBundle\Controller\RoutingPanelController;
use Nowo\RoutingKitBundle\Controller\UrlRedirectPanelController;
use Nowo\RoutingKitBundle\Form\RoutePathDefinitionType;
use Nowo\RoutingKitBundle\Form\RoutingPanelActionType;
use Nowo\RoutingKitBundle\Form\RoutingPanelImportType;
use Nowo\RoutingKitBundle\Form\UrlRedirectType;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\Panel\PanelView;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\ConfirmPage;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

use function count;

/**
 * Realistic page contexts for the panel templates, shaped like the controllers build them (view models + the shared
 * PanelView context). Used by the page contract test and the template tests.
 */
final class PanelPageFixtures
{
    public const PREFIX = '/_routing';

    /**
     * @return list<RoutePathDefinition>
     */
    public static function definitions(): array
    {
        return [
            new RoutePathDefinition('app_blog_show', 'en', '/blog/{slug}', id: 'rk_1'),
            new RoutePathDefinition('app_blog_show', 'es', '/blog/{slug}', id: 'rk_2'),
            new RoutePathDefinition('app_about', 'es', '/sobre-nosotros', enabled: false, id: 'rk_3'),
        ];
    }

    /**
     * @return list<UrlRedirect>
     */
    public static function redirects(): array
    {
        return [
            (new UrlRedirect('r1'))->setSourcePath('/tratamientos/unas-encarnadas')->setTargetUrl('/podologia')->setStatusCode(301)->setEnabled(true)->setHits(42)->setLastHitAt(new DateTimeImmutable('2026-10-01 10:00:00'))->setNote('Old WordPress site'),
            (new UrlRedirect('r2'))->setSourcePath('/promo-2025')->setTargetUrl('https://shop.example.com/sale')->setStatusCode(302)->setEnabled(false),
        ];
    }

    /**
     * @param list<RoutePathDefinition> $rows
     *
     * @return array<string, mixed>
     */
    public static function index(array $rows, string $importDisplay = 'inline', bool $roleGateDisabled = false, bool $redirects = true): array
    {
        $rowActions = [];
        foreach ($rows as $key => $row) {
            $rowActions[$key] = [
                Action::edit(self::PREFIX . '/edit/' . $row->id, PanelView::label('panel.edit')),
                Action::delete(self::PREFIX . '/delete/' . $row->id, confirm: self::routeDeleteConfirm($row), label: PanelView::label('panel.delete'), form: self::actionForm()),
            ];
        }
        $actions = [
            Action::create(self::PREFIX . '/new', PanelView::label('panel.new')),
            new Action('export', PanelView::label('panel.export'), self::PREFIX . '/export', method: 'POST', form: self::actionForm()),
            new Action('custom', PanelView::label('panel.clear_cache'), self::PREFIX . '/clear-cache', method: 'POST', form: self::actionForm()),
        ];
        if ($importDisplay === 'modal') {
            $actions[] = new Action('import', PanelView::label('panel.import'), modalId: PanelView::IMPORT_MODAL_ID);
        }

        return [
            'page' => new ListPage(
                new PageHeader(PanelView::label('panel.title'), PanelView::label('panel.subtitle', ['%default%' => 'en']), $actions),
                [
                    new Column('routeName', PanelView::label('panel.col.route'), required: true),
                    new Column('locale', PanelView::label('panel.col.locale')),
                    new Column('path', PanelView::label('panel.col.path'), required: true),
                    new Column('canonicalStyle', PanelView::label('panel.col.canonical'), priority: 3),
                    new Column('enabled', PanelView::label('panel.col.enabled'), priority: 2),
                ],
                $rows,
                rowActions: static fn (mixed $row, int|string $key): array => $rowActions[$key] ?? [],
                pagination: new Pagination(1, 50, count($rows), 1, 'nowo_routing_kit_panel'),
                empty: new EmptyState(message: PanelView::label('panel.empty')),
                caption: PanelView::label('panel.count', ['%count%' => count($rows)]),
                bundle: PanelView::BUNDLE,
                attributes: ['data-testid' => 'routing-kit-definitions-table'],
                actionsLabel: PanelView::label('panel.col.actions'),
            ),
            'import_form'     => self::factory()->createNamed('', RoutingPanelImportType::class)->createView(),
            'import_display'  => $importDisplay,
            'import_modal_id' => PanelView::IMPORT_MODAL_ID,
        ] + self::shared('routes', $roleGateDisabled, $redirects);
    }

    /**
     * @param list<string> $conflicts
     *
     * @return array<string, mixed>
     */
    public static function form(?RoutePathDefinition $existing, array $conflicts = [], ?string $error = null): array
    {
        $form = self::factory()->createNamed('', RoutePathDefinitionType::class, [
            'route_name' => $existing->routeName ?? 'app_blog_show',
            'locale'     => $existing->locale ?? 'en',
            'path'       => $existing->path ?? '/blog/{slug}',
            'enabled'    => true,
        ], [
            'routables' => [['route_name' => 'app_blog_show', 'controller' => 'App\\Controller\\BlogController::show'], ['route_name' => 'app_about', 'controller' => 'App\\Controller\\PageController::about']],
            'locales'   => ['en', 'es'],
        ]);
        if ($error !== null) {
            $form->addError(new FormError($error));
        }

        return [
            'page' => new FormPage(
                new PageHeader(PanelView::label($existing instanceof RoutePathDefinition ? 'panel.edit_title' : 'panel.create_title'), PanelView::label('panel.form_hint')),
                $form->createView(),
                PanelView::label('panel.save'),
                self::PREFIX . '/',
                PanelView::label('panel.cancel'),
                deleteAction: $existing instanceof RoutePathDefinition
                    ? Action::delete(self::PREFIX . '/delete/' . $existing->id, confirm: self::routeDeleteConfirm($existing), label: PanelView::label('panel.delete'), form: self::actionForm())
                    : null,
                bundle: PanelView::BUNDLE,
                attributes: ['data-testid' => 'routing-kit-definition-form'],
            ),
            'definition' => $existing,
            'conflicts'  => $conflicts,
        ] + self::shared('routes');
    }

    /**
     * @param list<UrlRedirect> $rows
     *
     * @return array<string, mixed>
     */
    public static function redirectsIndex(array $rows, ?string $status = null): array
    {
        $rowActions = [];
        foreach ($rows as $key => $redirect) {
            $rowActions[$key] = [
                Action::edit(self::PREFIX . '/redirects/' . $redirect->getId() . '/edit', PanelView::label('panel.edit')),
                self::redirectDeleteAction($redirect, ['data-testid' => 'redirect-delete']),
            ];
        }

        return [
            'page' => new ListPage(
                new PageHeader(PanelView::label('redirects.title'), PanelView::label('redirects.intro'), [
                    new Action('create', PanelView::label('redirects.new'), self::PREFIX . '/redirects/new', variant: 'primary', attributes: ['data-testid' => 'redirect-new']),
                ]),
                [
                    new Column('sourcePath', PanelView::label('redirects.col.source'), required: true),
                    new Column('targetUrl', PanelView::label('redirects.col.target'), required: true),
                    new Column('statusCode', PanelView::label('redirects.col.status')),
                    new Column('enabled', PanelView::label('redirects.col.enabled')),
                    new Column('hits', PanelView::label('redirects.col.hits'), align: 'end', priority: 2),
                    new Column('note', PanelView::label('redirects.col.note'), priority: 3),
                ],
                $rows,
                rowActions: static fn (mixed $row, int|string $key): array => $rowActions[$key] ?? [],
                pagination: new Pagination(1, 50, count($rows), 1, 'nowo_routing_kit_redirects'),
                empty: new EmptyState(message: PanelView::label('redirects.empty')),
                caption: PanelView::label('redirects.count', ['%count%' => count($rows)]),
                bundle: PanelView::BUNDLE,
                attributes: ['data-testid' => 'redirect-table'],
                actionsLabel: PanelView::label('redirects.col.actions'),
            ),
            'status' => $status,
        ] + self::shared('redirects');
    }

    /**
     * @return array<string, mixed>
     */
    public static function redirectsForm(?UrlRedirect $existing): array
    {
        $form = self::factory()->create(UrlRedirectType::class, [
            'source_path' => $existing?->getSourcePath() ?? '',
            'target_url'  => $existing?->getTargetUrl() ?? '',
            'status_code' => $existing?->getStatusCode() ?? 301,
            'enabled'     => $existing?->isEnabled() ?? true,
            'note'        => $existing?->getNote() ?? '',
        ]);

        return [
            'page' => new FormPage(
                new PageHeader(PanelView::label($existing instanceof UrlRedirect ? 'redirects.edit_title' : 'redirects.create_title'), PanelView::label('redirects.form_hint')),
                $form->createView(),
                PanelView::label('panel.save'),
                self::PREFIX . '/redirects',
                PanelView::label('panel.cancel'),
                deleteAction: $existing instanceof UrlRedirect ? self::redirectDeleteAction($existing) : null,
                bundle: PanelView::BUNDLE,
                attributes: ['data-testid' => 'routing-kit-redirect-form-panel'],
            ),
            'redirect' => $existing,
        ] + self::shared('redirects');
    }

    /**
     * @return array<string, mixed>
     */
    public static function redirectsDelete(UrlRedirect $redirect): array
    {
        return [
            'page' => new ConfirmPage(
                new PageHeader(PanelView::label('redirects.delete_title')),
                self::redirectDeleteConfirm($redirect),
                new Action('delete', PanelView::label('panel.delete'), self::PREFIX . '/redirects/' . $redirect->getId() . '/delete', method: 'POST', form: self::actionForm(UrlRedirectPanelController::DELETE_CSRF_TOKEN_PREFIX . $redirect->getId())),
                self::PREFIX . '/redirects',
                bundle: PanelView::BUNDLE,
                attributes: ['data-testid' => 'redirect-delete-form'],
            ),
            'redirect' => $redirect,
        ] + self::shared('redirects');
    }

    /**
     * Context PanelView::render() adds to every page.
     *
     * @return array<string, mixed>
     */
    public static function shared(string $section, bool $roleGateDisabled = false, bool $redirects = true): array
    {
        $view = new PanelView(new Environment(new ArrayLoader()), self::PREFIX, urlRedirectsEnabled: $redirects, roleGateDisabled: $roleGateDisabled);

        return [
            'path_prefix'                    => self::PREFIX,
            'nav'                            => $view->nav($section),
            'nav_current'                    => $section,
            'role_gate_disabled'             => $roleGateDisabled,
            'nowo_routing_kit_legacy_layout' => null,
        ];
    }

    public static function factory(): FormFactoryInterface
    {
        return FormKitTestSupport::createFormFactory(new class implements CsrfTokenManagerInterface {
            public function getToken(string $tokenId): CsrfToken
            {
                return new CsrfToken($tokenId, 'token-' . $tokenId);
            }

            public function refreshToken(string $tokenId): CsrfToken
            {
                return $this->getToken($tokenId);
            }

            public function removeToken(string $tokenId): ?string
            {
                return null;
            }

            public function isTokenValid(CsrfToken $token): bool
            {
                return true;
            }
        }, true);
    }

    private static function actionForm(string $tokenId = RoutingPanelController::CSRF_TOKEN_ID): FormView
    {
        return self::factory()->createNamed('', RoutingPanelActionType::class, null, ['csrf_token_id' => $tokenId])->createView();
    }

    private static function routeDeleteConfirm(RoutePathDefinition $definition): Confirm
    {
        return new Confirm(
            PanelView::label('panel.delete_title'),
            PanelView::label('panel.delete_confirm_message', ['%route%' => $definition->routeName, '%locale%' => $definition->locale, '%path%' => $definition->path]),
            PanelView::label('panel.delete'),
        );
    }

    /**
     * @param array<string, string> $attributes
     */
    private static function redirectDeleteAction(UrlRedirect $redirect, array $attributes = []): Action
    {
        return new Action(
            'delete',
            PanelView::label('panel.delete'),
            self::PREFIX . '/redirects/' . $redirect->getId() . '/delete',
            method: 'POST',
            confirm: self::redirectDeleteConfirm($redirect),
            attributes: $attributes,
            form: self::actionForm(UrlRedirectPanelController::DELETE_CSRF_TOKEN_PREFIX . $redirect->getId()),
        );
    }

    private static function redirectDeleteConfirm(UrlRedirect $redirect): Confirm
    {
        return new Confirm(
            PanelView::label('redirects.delete_title'),
            PanelView::label('redirects.delete_confirm', ['%source%' => $redirect->getSourcePath(), '%target%' => $redirect->getTargetUrl()]),
            PanelView::label('panel.delete'),
        );
    }
}
