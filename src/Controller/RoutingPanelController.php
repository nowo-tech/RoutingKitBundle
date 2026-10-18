<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Controller;

use JsonException;
use Nowo\RoutingKitBundle\Discovery\RoutableControllerDiscovery;
use Nowo\RoutingKitBundle\Form\RoutePathDefinitionType;
use Nowo\RoutingKitBundle\Form\RoutingPanelActionType;
use Nowo\RoutingKitBundle\Form\RoutingPanelImportType;
use Nowo\RoutingKitBundle\Locale\LocaleProviderInterface;
use Nowo\RoutingKitBundle\Model\AliasMode;
use Nowo\RoutingKitBundle\Model\CanonicalStyle;
use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Model\TrailingSlashStyle;
use Nowo\RoutingKitBundle\Panel\PanelView;
use Nowo\RoutingKitBundle\Security\PanelAccessGuard;
use Nowo\RoutingKitBundle\Service\RoutePathImportExport;
use Nowo\RoutingKitBundle\Service\RoutePathManager;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\ConfirmPage;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use RuntimeException;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

use function array_slice;
use function array_values;
use function ceil;
use function count;
use function is_array;
use function is_string;
use function json_decode;
use function max;
use function strlen;

use const JSON_THROW_ON_ERROR;

#[AsController]
final class RoutingPanelController
{
    public const CSRF_TOKEN_ID = 'nowo_routing_kit_panel';

    /** Max raw import JSON size (bytes) to limit admin-panel DoS. */
    private const MAX_IMPORT_PAYLOAD_BYTES = 1_048_576;

    public function __construct(
        private readonly RoutePathManager $manager,
        private readonly RoutableControllerDiscovery $discovery,
        private readonly LocaleProviderInterface $locales,
        private readonly PanelView $view,
        private readonly FormFactoryInterface $formFactory,
        private readonly PanelAccessGuard $accessGuard,
        private readonly RoutePathImportExport $importExport,
        private readonly string $pathPrefix = '/_routing',
        private readonly bool $allowControllerOverride = false,
        private readonly int $listPageSize = 50,
    ) {
    }

    #[Route('/', name: 'nowo_routing_kit_panel', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->accessGuard->assertGranted();

        $all      = $this->manager->all();
        $total    = count($all);
        $pageSize = max(1, $this->listPageSize);
        $pages    = max(1, (int) ceil($total / $pageSize));
        $page     = max(1, min($pages, $request->query->getInt('page', 1)));
        $offset   = ($page - 1) * $pageSize;
        $rows     = array_values(array_slice($all, $offset, $pageSize));

        $rowActions = [];
        foreach ($rows as $key => $row) {
            $rowActions[$key] = [
                Action::edit($this->pathPrefix . '/edit/' . $row->id, PanelView::label('panel.edit')),
                $this->deleteAction($row),
            ];
        }

        $importModal = $this->view->importDisplay === 'modal';
        $actions     = [
            new Action('export', PanelView::label('panel.export'), $this->pathPrefix . '/export', method: 'POST', form: $this->createPanelActionForm()->createView()),
            new Action('custom', PanelView::label('panel.clear_cache'), $this->pathPrefix . '/clear-cache', method: 'POST', form: $this->createPanelActionForm()->createView()),
        ];
        if ($importModal) {
            $actions[] = PanelView::importDialogAction();
        }
        $create  = Action::create($this->pathPrefix . '/new', PanelView::label('panel.new'));
        $actions = $this->view->primaryActionLast ? [...$actions, $create] : [$create, ...$actions];

        $listPage = new ListPage(
            new PageHeader(PanelView::label('panel.title'), PanelView::label('panel.subtitle', ['%default%' => $this->locales->getDefaultLocale()]), $actions, tabs: $this->view->nav('routes')),
            [
                new Column('routeName', PanelView::label('panel.col.route'), required: true),
                new Column('locale', PanelView::label('panel.col.locale')),
                new Column('path', PanelView::label('panel.col.path'), required: true),
                new Column('canonicalStyle', PanelView::label('panel.col.canonical'), priority: 3),
                new Column('enabled', PanelView::label('panel.col.enabled'), priority: 2),
            ],
            $rows,
            rowActions: static fn (mixed $row, int|string $key): array => $rowActions[$key] ?? [],
            pagination: new Pagination($page, $pageSize, $total, $pages, 'nowo_routing_kit_panel'),
            empty: new EmptyState(message: PanelView::label('panel.empty')),
            caption: $this->view->showRowCount ? PanelView::label('panel.count', ['%count%' => $total]) : null,
            bundle: PanelView::BUNDLE,
            layout: $this->view->layout(),
            attributes: ['data-testid' => 'routing-kit-definitions-table'],
            actionsLabel: PanelView::label('panel.col.actions'),
        );

        return $this->view->render('index', 'routes', [
            'page'            => $listPage,
            'import_form'     => $this->createPanelImportForm()->createView(),
            'import_display'  => $importModal ? 'modal' : 'inline',
            'import_modal_id' => PanelView::IMPORT_MODAL_ID,
        ]);
    }

    #[Route('/new', name: 'nowo_routing_kit_panel_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->accessGuard->assertGranted();

        return $this->form($request, null);
    }

    #[Route('/edit/{id}', name: 'nowo_routing_kit_panel_edit', requirements: ['id' => '[A-Za-z0-9_.-]+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        $this->accessGuard->assertGranted();

        $definition = $this->manager->get($id);
        if (!$definition instanceof RoutePathDefinition) {
            return new Response('Not found', 404);
        }

        return $this->form($request, $definition);
    }

    /**
     * Full-page confirmation (page/confirm, the no-JS fallback of the confirm dialog) on GET; deletes on POST (CSRF).
     */
    #[Route('/delete/{id}', name: 'nowo_routing_kit_panel_delete', requirements: ['id' => '[A-Za-z0-9_.-]+'], methods: ['GET', 'POST'])]
    public function delete(Request $request, string $id): Response
    {
        $this->accessGuard->assertGranted();

        $form = $this->createPanelActionForm();

        if ($request->isMethod('POST')) {
            $form->handleRequest($request);

            if (!$form->isSubmitted() || !$form->isValid()) {
                return new Response('Invalid CSRF token.', 403);
            }

            $this->manager->delete($id);

            return new RedirectResponse($this->pathPrefix . '/');
        }

        $definition = $this->manager->get($id);
        if (!$definition instanceof RoutePathDefinition) {
            return new Response('Not found', 404);
        }

        return $this->view->render('delete', 'routes', [
            'page' => new ConfirmPage(
                new PageHeader(PanelView::label('panel.delete_title'), tabs: $this->view->nav('routes')),
                $this->deleteConfirm($definition),
                new Action('delete', PanelView::label('panel.delete'), $this->pathPrefix . '/delete/' . $id, method: 'POST', form: $form->createView()),
                $this->pathPrefix . '/',
                bundle: PanelView::BUNDLE,
                layout: $this->view->layout(),
                attributes: ['data-testid' => 'routing-kit-delete-form'],
            ),
            'definition' => $definition,
        ]);
    }

    #[Route('/clear-cache', name: 'nowo_routing_kit_panel_clear_cache', methods: ['POST'])]
    public function clearCache(Request $request): Response
    {
        $this->accessGuard->assertGranted();

        if (!$request->isMethod('POST')) {
            return new RedirectResponse($this->pathPrefix . '/');
        }

        $form = $this->createPanelActionForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return new Response('Invalid CSRF token.', 403);
        }

        // @igor-ignore - HTTP handler delegates to services; no controller worker state.
        $this->manager->clearCache();

        return new RedirectResponse($this->pathPrefix . '/');
    }

    #[Route('/export', name: 'nowo_routing_kit_panel_export', methods: ['POST'])]
    public function export(Request $request): Response
    {
        $this->accessGuard->assertGranted();

        if (!$request->isMethod('POST')) {
            return new Response('Method Not Allowed', Response::HTTP_METHOD_NOT_ALLOWED);
        }

        $form = $this->createPanelActionForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return new Response('Invalid CSRF token.', 403);
        }

        $envelope = $this->importExport->export();

        return new JsonResponse($envelope, 200, [
            'Content-Disposition' => 'attachment; filename="routing-kit-paths.json"',
        ]);
    }

    #[Route('/import', name: 'nowo_routing_kit_panel_import', methods: ['POST'])]
    public function import(Request $request): Response
    {
        $this->accessGuard->assertGranted();

        if (!$request->isMethod('POST')) {
            return new RedirectResponse($this->pathPrefix . '/');
        }

        $form = $this->createPanelImportForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return new Response('Invalid CSRF token.', 403);
        }

        /** @var array<string, mixed> $data */
        $data = $form->getData() ?? [];
        $raw  = (string) ($data['payload_json'] ?? '');
        if (strlen($raw) > self::MAX_IMPORT_PAYLOAD_BYTES) {
            return new Response('Import payload too large.', 413);
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new Response('Invalid JSON payload.', 400);
        }
        if (!is_array($decoded)) {
            return new Response('Invalid JSON payload.', 400);
        }

        /* @var array{payload?: mixed, signature?: mixed, version?: mixed} $decoded */
        try {
            $this->importExport->import($decoded, (bool) ($data['replace_all'] ?? false));
            // @igor-ignore - HTTP handler delegates to services; no controller worker state.
            $this->manager->clearCache();
        } catch (Throwable $e) {
            $message = $e instanceof RuntimeException ? $e->getMessage() : 'Import failed.';

            return new Response($message, 400);
        }

        return new RedirectResponse($this->pathPrefix . '/');
    }

    #[Route('/conflicts', name: 'nowo_routing_kit_panel_conflicts', methods: ['GET'])]
    public function previewConflicts(Request $request): JsonResponse
    {
        $this->accessGuard->assertGranted();

        $definition = new RoutePathDefinition(
            routeName: (string) $request->query->get('route_name', ''),
            locale: (string) $request->query->get('locale', ''),
            path: (string) $request->query->get('path', '/'),
            canonicalStyle: CanonicalStyle::tryFrom((string) $request->query->get('canonical_style', CanonicalStyle::WithoutPrefix->value)) ?? CanonicalStyle::WithoutPrefix,
            trailingSlash: TrailingSlashStyle::tryFrom((string) $request->query->get('trailing_slash', TrailingSlashStyle::Omit->value)) ?? TrailingSlashStyle::Omit,
            aliasMode: AliasMode::tryFrom((string) $request->query->get('alias_mode', AliasMode::Redirect->value)) ?? AliasMode::Redirect,
            enabled: true,
            id: $request->query->get('id') !== null && $request->query->get('id') !== ''
                ? (string) $request->query->get('id')
                : null,
        );

        return new JsonResponse([
            'conflicts' => $this->manager->previewConflicts($definition),
        ]);
    }

    private function form(Request $request, ?RoutePathDefinition $existing): Response
    {
        $routables    = $this->discovery->discover();
        $localeList   = $this->locales->getLocales();
        $initialRoute = $existing instanceof RoutePathDefinition
            ? $existing->routeName
            : ($routables[0]['route_name'] ?? '');

        /** @var FormInterface<array<string, mixed>|null> $form */
        $form = $this->formFactory->createNamed(
            '',
            RoutePathDefinitionType::class,
            $this->formDataFromDefinition($existing, $initialRoute),
            [
                'routables'                 => $routables,
                'locales'                   => $localeList,
                'allow_controller_override' => $this->allowControllerOverride,
                'is_create'                 => !$existing instanceof RoutePathDefinition,
                'initial_route_name'        => $initialRoute,
            ],
        );

        $form->handleRequest($request);

        $conflicts = [];

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data       = $form->getData() ?? [];
            $definition = $this->definitionFromFormData($data, $existing?->id);
            $conflicts  = $this->manager->previewConflicts($definition);

            try {
                $this->manager->save($definition);

                return new RedirectResponse($this->pathPrefix . '/');
            } catch (Throwable $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        } elseif (!$form->isSubmitted() && $existing instanceof RoutePathDefinition) {
            $conflicts = $this->manager->previewConflicts($existing);
        }

        $isEdit = $existing instanceof RoutePathDefinition;

        return $this->view->render('form', 'routes', [
            'page' => new FormPage(
                new PageHeader(PanelView::label($isEdit ? 'panel.edit_title' : 'panel.create_title'), PanelView::label('panel.form_hint'), tabs: $this->view->nav('routes')),
                $form->createView(),
                PanelView::label('panel.save'),
                $this->pathPrefix . '/',
                PanelView::label('panel.cancel'),
                deleteAction: $isEdit ? $this->deleteAction($existing) : null,
                bundle: PanelView::BUNDLE,
                layout: $this->view->layout(),
                attributes: ['data-testid' => 'routing-kit-definition-form'],
            ),
            'definition' => $existing,
            'conflicts'  => $conflicts,
        ]);
    }

    /**
     * Delete through the UiKit confirm dialog; without JS, a link to the full-page confirmation (GET of the same URL).
     */
    private function deleteAction(RoutePathDefinition $definition): Action
    {
        $href = $this->pathPrefix . '/delete/' . $definition->id;

        return new Action(
            'delete',
            PanelView::label('panel.delete'),
            $href,
            method: 'POST',
            confirm: $this->deleteConfirm($definition),
            form: $this->createPanelActionForm()->createView(),
            fallbackHref: $href,
        );
    }

    private function deleteConfirm(RoutePathDefinition $definition): Confirm
    {
        return new Confirm(
            PanelView::label('panel.delete_title'),
            PanelView::label('panel.delete_confirm_message', ['%route%' => $definition->routeName, '%locale%' => $definition->locale, '%path%' => $definition->path]),
            PanelView::label('panel.delete'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formDataFromDefinition(?RoutePathDefinition $definition, string $initialRoute): array
    {
        if ($definition instanceof RoutePathDefinition) {
            return [
                'route_name'      => $definition->routeName,
                'locale'          => $definition->locale,
                'path'            => $definition->path,
                'canonical_style' => $definition->canonicalStyle->value,
                'trailing_slash'  => $definition->trailingSlash->value,
                'alias_mode'      => $definition->aliasMode->value,
                'enabled'         => $definition->enabled,
                'controller'      => $definition->controller ?? '',
            ];
        }

        return [
            'route_name'      => $initialRoute,
            'locale'          => $this->locales->getDefaultLocale(),
            'path'            => '/',
            'canonical_style' => CanonicalStyle::WithoutPrefix->value,
            'trailing_slash'  => TrailingSlashStyle::Omit->value,
            'alias_mode'      => AliasMode::Redirect->value,
            'enabled'         => true,
            'controller'      => '',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function definitionFromFormData(array $data, ?string $id): RoutePathDefinition
    {
        $controller = null;
        if ($this->allowControllerOverride) {
            $raw        = $data['controller'] ?? null;
            $controller = is_string($raw) && $raw !== '' ? $raw : null;
        }

        return new RoutePathDefinition(
            routeName: (string) ($data['route_name'] ?? ''),
            locale: (string) ($data['locale'] ?? ''),
            path: (string) ($data['path'] ?? '/'),
            canonicalStyle: CanonicalStyle::tryFrom((string) ($data['canonical_style'] ?? CanonicalStyle::WithoutPrefix->value)) ?? CanonicalStyle::WithoutPrefix,
            trailingSlash: TrailingSlashStyle::tryFrom((string) ($data['trailing_slash'] ?? TrailingSlashStyle::Omit->value)) ?? TrailingSlashStyle::Omit,
            aliasMode: AliasMode::tryFrom((string) ($data['alias_mode'] ?? AliasMode::Redirect->value)) ?? AliasMode::Redirect,
            enabled: (bool) ($data['enabled'] ?? false),
            controller: $controller,
            id: $id,
        );
    }

    /**
     * @return FormInterface<array<string, mixed>|null>
     */
    private function createPanelActionForm(): FormInterface
    {
        return $this->formFactory->createNamed('', RoutingPanelActionType::class);
    }

    /**
     * @return FormInterface<array<string, mixed>|null>
     */
    private function createPanelImportForm(): FormInterface
    {
        return $this->formFactory->createNamed('', RoutingPanelImportType::class);
    }
}
