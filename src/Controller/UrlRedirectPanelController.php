<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Controller;

use DateTimeImmutable;
use Nowo\RoutingKitBundle\Form\RoutingPanelActionType;
use Nowo\RoutingKitBundle\Form\UrlRedirectType;
use Nowo\RoutingKitBundle\Model\UrlRedirect;
use Nowo\RoutingKitBundle\NowoRoutingKitBundle;
use Nowo\RoutingKitBundle\Panel\PanelView;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectMatcher;
use Nowo\RoutingKitBundle\Redirect\UrlRedirectValidator;
use Nowo\RoutingKitBundle\Security\PanelAccessGuard;
use Nowo\RoutingKitBundle\Storage\UrlRedirectStorageInterface;
use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Column;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\ConfirmPage;
use Nowo\UiKitBundle\View\EmptyState;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\Pagination;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

use function array_slice;
use function array_values;
use function ceil;
use function count;
use function in_array;
use function is_numeric;
use function is_string;
use function max;
use function min;

/**
 * Panel CRUD for operator URL redirects under `{web_ui.path_prefix}/redirects` (REQ-REDIR-007).
 *
 * Active only when `url_redirects.enabled` and `web_ui.enabled` are true; otherwise every action is a
 * 404. Access uses the panel guard (security.access_roles). Writes are CSRF-protected; delete uses the UiKit
 * confirm dialog, with a full-page confirmation (page/confirm) as the no-JS fallback (CSP-friendly).
 */
#[AsController]
final class UrlRedirectPanelController
{
    public const CSRF_TOKEN_ID = 'nowo_routing_kit_url_redirect';

    public const DELETE_CSRF_TOKEN_PREFIX = 'nowo_routing_kit_url_redirect_delete_';

    private const STATUS_MESSAGES = ['created', 'saved', 'deleted'];

    public function __construct(
        private readonly PanelView $view,
        private readonly FormFactoryInterface $formFactory,
        private readonly ?PanelAccessGuard $accessGuard = null,
        private readonly ?UrlRedirectStorageInterface $storage = null,
        private readonly ?UrlRedirectMatcher $matcher = null,
        private readonly ?UrlRedirectValidator $validator = null,
        private readonly ?TranslatorInterface $translator = null,
        private readonly int $listPageSize = 50,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?ClockInterface $clock = null,
    ) {
    }

    #[Route('/redirects', name: 'nowo_routing_kit_redirects', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $storage  = $this->enabledStorage();
        $all      = $storage->all();
        $total    = count($all);
        $pageSize = max(1, $this->listPageSize);
        $pages    = max(1, (int) ceil($total / $pageSize));
        $page     = max(1, min($pages, $request->query->getInt('page', 1)));
        $rows     = array_values(array_slice($all, ($page - 1) * $pageSize, $pageSize));
        $status   = $request->query->getString('status');
        $prefix   = $this->view->pathPrefix;

        $rowActions = [];
        foreach ($rows as $key => $redirect) {
            $rowActions[$key] = [
                new Action('edit', PanelView::label('panel.edit'), $prefix . '/redirects/' . $redirect->getId() . '/edit', icon: 'edit'),
                $this->deleteAction($redirect, ['data-testid' => 'redirect-delete']),
            ];
        }

        return $this->view->render('redirects_index', 'redirects', [
            'page' => new ListPage(
                new PageHeader(PanelView::label('redirects.title'), PanelView::label('redirects.intro'), [
                    new Action('create', PanelView::label('redirects.new'), $prefix . '/redirects/new', variant: 'primary', attributes: ['data-testid' => 'redirect-new']),
                ], tabs: $this->view->nav('redirects')),
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
                pagination: new Pagination($page, $pageSize, $total, $pages, 'nowo_routing_kit_redirects'),
                empty: new EmptyState(message: PanelView::label('redirects.empty')),
                caption: $this->view->showRowCount ? PanelView::label('redirects.count', ['%count%' => $total]) : null,
                bundle: PanelView::BUNDLE,
                layout: $this->view->layout(),
                attributes: ['data-testid' => 'redirect-table'],
                actionsLabel: PanelView::label('redirects.col.actions'),
            ),
            'status' => in_array($status, self::STATUS_MESSAGES, true) ? $status : null,
        ]);
    }

    #[Route('/redirects/new', name: 'nowo_routing_kit_redirects_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->enabledStorage();

        return $this->form($request, null);
    }

    #[Route('/redirects/{id}/edit', name: 'nowo_routing_kit_redirects_edit', requirements: ['id' => '[A-Za-z0-9_.-]+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        return $this->form($request, $this->find($id));
    }

    /**
     * Full-page confirmation (no-JS fallback of the list's confirm dialog) on GET; deletes on POST.
     */
    #[Route('/redirects/{id}/delete', name: 'nowo_routing_kit_redirects_delete', requirements: ['id' => '[A-Za-z0-9_.-]+'], methods: ['GET', 'POST'])]
    public function delete(Request $request, string $id): Response
    {
        $redirect = $this->find($id);
        $form     = $this->deleteForm($id);
        $form->handleRequest($request);

        if ($request->isMethod('POST')) {
            if (!$form->isSubmitted() || !$form->isValid()) {
                return new Response('Invalid CSRF token.', Response::HTTP_FORBIDDEN);
            }

            $this->enabledStorage()->delete($id);
            $this->matcher?->invalidate();

            return new RedirectResponse($this->view->pathPrefix . '/redirects?status=deleted');
        }

        return $this->view->render('redirects_delete', 'redirects', [
            'page' => new ConfirmPage(
                new PageHeader($this->deleteTitle($redirect), tabs: $this->view->nav('redirects')),
                $this->deleteConfirm($redirect),
                new Action('delete', PanelView::label('panel.delete'), $this->view->pathPrefix . '/redirects/' . $id . '/delete', method: 'POST', form: $form->createView()),
                $this->view->pathPrefix . '/redirects',
                bundle: PanelView::BUNDLE,
                layout: $this->view->layout(),
                attributes: ['data-testid' => 'redirect-delete-form'],
            ),
            'redirect' => $redirect,
        ]);
    }

    private function form(Request $request, ?UrlRedirect $existing): Response
    {
        [$storage, $validator] = $this->services();

        /** @var FormInterface<array<string, mixed>|null> $form */
        $form = $this->formFactory->create(UrlRedirectType::class, [
            'source_path' => $existing?->getSourcePath() ?? '',
            'target_url'  => $existing?->getTargetUrl() ?? '',
            'status_code' => $existing?->getStatusCode() ?? 301,
            'enabled'     => $existing?->isEnabled() ?? true,
            'note'        => $existing?->getNote() ?? '',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data   = $form->getData() ?? [];
            $source = is_string($data['source_path'] ?? null) ? $data['source_path'] : '';
            $target = is_string($data['target_url'] ?? null) ? $data['target_url'] : '';
            $status = is_numeric($data['status_code'] ?? null) ? (int) $data['status_code'] : 0;
            $note   = is_string($data['note'] ?? null) ? $data['note'] : '';
            $errors = $validator->validate($source, $target, $status, $note, $existing?->getId());

            if ($errors === []) {
                $now      = $this->clock?->now() ?? new DateTimeImmutable();
                $user     = $this->accessGuard?->userIdentifier();
                $redirect = $existing ?? (new UrlRedirect())->setCreatedAt($now)->setCreatedBy($user);
                // @igor-ignore - Per-request value object loaded from storage, not a shared service.
                $redirect
                    ->setSourcePath($source)
                    ->setTargetUrl($target)
                    ->setStatusCode($status)
                    ->setEnabled((bool) ($data['enabled'] ?? false))
                    ->setNote($note)
                    ->setUpdatedAt($now)
                    ->setUpdatedBy($user);

                try {
                    $storage->save($redirect);
                    $this->matcher?->invalidate();

                    return new RedirectResponse($this->view->pathPrefix . '/redirects?status=' . ($existing instanceof UrlRedirect ? 'saved' : 'created'));
                } catch (Throwable $exception) {
                    // Storage messages may expose file paths or SQL: log them, show a generic error.
                    $this->logger?->error('RoutingKit: URL redirect not saved: {message}', ['message' => $exception->getMessage(), 'exception' => $exception]);
                    $form->addError(new FormError($this->trans('redirects.error.save_failed'), 'redirects.error.save_failed'));
                }
            }

            foreach ($errors as $field => $key) {
                $form->get($field)->addError(new FormError($this->trans($key), $key));
            }
        }

        return $this->view->render('redirects_form', 'redirects', [
            'page' => new FormPage(
                new PageHeader(PanelView::label($existing instanceof UrlRedirect ? 'redirects.edit_title' : 'redirects.create_title'), PanelView::label('redirects.form_hint'), tabs: $this->view->nav('redirects')),
                $form->createView(),
                PanelView::label('panel.save'),
                $this->view->pathPrefix . '/redirects',
                PanelView::label('panel.cancel'),
                deleteAction: $existing instanceof UrlRedirect ? $this->deleteAction($existing) : null,
                bundle: PanelView::BUNDLE,
                layout: $this->view->layout(),
                attributes: ['data-testid' => 'routing-kit-redirect-form-panel'],
                formThemes: [PanelView::FORM_THEME],
            ),
            'redirect' => $existing,
        ]);
    }

    /**
     * @param array<string, string> $attributes
     */
    private function deleteAction(UrlRedirect $redirect, array $attributes = []): Action
    {
        $href = $this->view->pathPrefix . '/redirects/' . $redirect->getId() . '/delete';

        return new Action(
            'delete',
            PanelView::label('panel.delete'),
            $href,
            method: 'POST',
            confirm: $this->deleteConfirm($redirect),
            icon: 'delete',
            attributes: $attributes,
            form: $this->deleteForm((string) $redirect->getId())->createView(),
            fallbackHref: $href,
        );
    }

    /**
     * Confirm title naming the redirect (UiKit confirm pattern: "Delete redirect /promo-2025?").
     */
    private function deleteTitle(UrlRedirect $redirect): Label
    {
        return PanelView::label('redirects.delete_title', ['%source%' => $redirect->getSourcePath()]);
    }

    private function deleteConfirm(UrlRedirect $redirect): Confirm
    {
        return new Confirm(
            $this->deleteTitle($redirect),
            PanelView::label('redirects.delete_confirm', ['%source%' => $redirect->getSourcePath(), '%target%' => $redirect->getTargetUrl()]),
            PanelView::label('panel.delete'),
        );
    }

    /**
     * @return FormInterface<array<string, mixed>|null>
     */
    private function deleteForm(string $id): FormInterface
    {
        return $this->formFactory->createNamed('', RoutingPanelActionType::class, null, [
            'csrf_token_id' => self::DELETE_CSRF_TOKEN_PREFIX . $id,
        ]);
    }

    private function find(string $id): UrlRedirect
    {
        $redirect = $this->enabledStorage()->findById($id);
        if (!$redirect instanceof UrlRedirect) {
            throw new NotFoundHttpException('Redirect not found.');
        }

        return $redirect;
    }

    private function enabledStorage(): UrlRedirectStorageInterface
    {
        return $this->services()[0];
    }

    /**
     * 404 when the feature or the panel is off; 403 via the guard otherwise.
     *
     * @return array{UrlRedirectStorageInterface, UrlRedirectValidator}
     */
    private function services(): array
    {
        if (!$this->accessGuard instanceof PanelAccessGuard
            || !$this->storage instanceof UrlRedirectStorageInterface
            || !$this->validator instanceof UrlRedirectValidator
        ) {
            throw new NotFoundHttpException('URL redirects are not enabled.');
        }

        $this->accessGuard->assertGranted();

        return [$this->storage, $this->validator];
    }

    private function trans(string $key): string
    {
        return $this->translator?->trans($key, [], NowoRoutingKitBundle::TRANSLATION_DOMAIN) ?? $key;
    }
}
