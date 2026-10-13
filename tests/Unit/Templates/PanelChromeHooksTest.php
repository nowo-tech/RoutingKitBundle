<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Tests\Unit\Templates;

use Nowo\RoutingKitBundle\Model\RoutePathDefinition;
use Nowo\RoutingKitBundle\Tests\Support\PanelTemplateRenderer;
use PHPUnit\Framework\TestCase;

use function substr_count;

/**
 * Spec 003: a host repaints the panel with config (class map, partials, options), a layout that pulls the slot
 * blocks, and `@!NowoRoutingKitBundle` block overrides — without copying any page.
 */
final class PanelChromeHooksTest extends TestCase
{
    private const HOST_LAYOUT = <<<'TWIG'
        {% set rk_page_header_in_layout = true %}
        {% set rk_title_suffix = ' — Host' %}
        {% set host_actions %}{% if block('rk_back_link') is defined %}{{ block('rk_back_link') }}{% endif %}{% if block('rk_page_actions') is defined %}{{ block('rk_page_actions') }}{% endif %}{% endset %}
        <title>{% block title %}{% endblock %}</title>
        {% block stylesheets %}{% endblock %}
        <header class="host-header"><h1>Routing</h1><div class="host-actions">{{ host_actions }}</div></header>
        <main class="kit-admin">{% block body %}{% endblock %}</main>
        {% block javascripts %}{% endblock %}
        TWIG;

    private const HOST_MODAL_SHELL = <<<'TWIG'
        <dialog class="confirm-dialog" id="{{ modal_id }}" aria-labelledby="{{ modal_id }}-label">
            <h2 class="confirm-dialog__title" id="{{ modal_id }}-label">{{ modal_title }}</h2>
            {% block rk_modal_body %}{% endblock %}
        </dialog>
        TWIG;

    private const HOST_PAGINATION = '<nav class="host-pagination" data-route="{{ route }}" data-page="{{ pagination.page }}" data-total="{{ pagination.total }}" data-count="{{ item_count }}"></nav>';

    /**
     * @var array<string, string>
     */
    private const HOST_CLASSES = [
        'btn_primary'     => 'btn-primary no-underline',
        'btn_secondary'   => 'btn-ghost',
        'btn_link'        => 'btn-ghost no-underline',
        'inline_form'     => 'inline',
        'action_edit'     => 'btn-ghost btn-icon no-underline',
        'action_delete'   => 'btn-danger btn-icon',
        'delete_form'     => 'inline',
        'row_actions'     => 'inline-flex gap-1 nowo-ui-row-actions nowo-ui-row-actions--icon',
        'results'         => 'panel overflow-x-auto',
        'table_wrap'      => 'min-w-0',
        'table'           => 'data-table w-full',
        'thead_row'       => 'border-b',
        'th'              => 'px-2 py-2',
        'th_actions'      => 'w-0 px-2 py-2 text-end',
        'row'             => 'row-hover',
        'td'              => 'px-2 py-3',
        'td_actions'      => 'w-0 px-2 py-3 text-end',
        'badge_yes'       => 'badge badge--ok',
        'badge_no'        => 'badge',
        'empty'           => 'px-2 py-4',
        'form_panel'      => 'panel',
        'form_heading'    => 'mb-1 text-base font-semibold',
        'form_hint'       => 'mb-4 text-sm',
        'alert'           => 'alert alert--danger',
        'form'            => 'nowo-ui-form nowo-ui-form--stack',
        'form_actions'    => 'mt-4 flex gap-2',
        'modal_form'      => 'kit-modal__panel',
        'modal_form_body' => 'confirm-dialog__content',
        'modal_actions'   => 'confirm-dialog__actions',
    ];

    public function testIndexWithHostConfigNeedsNoPageFork(): void
    {
        $html = $this->hostRenderer()->render('@NowoRoutingKitBundle/panel/index.html.twig', PanelDefaultOutputTest::indexContext([
            new RoutePathDefinition('app_home', 'en', '/home', id: 'rk_1'),
            new RoutePathDefinition('app_about', 'es', '/sobre', enabled: false, id: 'rk_2'),
        ]));

        self::assertStringContainsString('<title>Routing Kit — Host</title>', $html);
        self::assertStringNotContainsString('nowo-ui-page-header', $html, 'Header comes from the layout.');
        self::assertStringNotContainsString('nowo-ui.css', $html);
        self::assertStringNotContainsString('row(s)', $html);

        // page actions in the layout header: secondary actions, import modal trigger, primary last
        self::assertMatchesRegularExpression('#<div class="host-actions">\s*<form-start name="" options="{&quot;action&quot;:&quot;\\\\/_routing-kit\\\\/export&quot;,&quot;method&quot;:&quot;POST&quot;,&quot;attr&quot;:{&quot;class&quot;:&quot;inline&quot;}}">#', $html);
        self::assertMatchesRegularExpression('#<button type="submit" class="btn-ghost">Export</button>.*<button type="submit" class="btn-ghost">Clear routing cache</button>.*<button type="button" class="btn-ghost" data-nowo-modal-open data-nowo-modal-target="modal-routing-import">Import</button>\s*<a class="btn-primary no-underline nowo-ui-action-create" href="/_routing-kit/new">New path</a>\s*</div></header>#s', $html);

        // results panel keeps the E2E test id; table chrome from the class map
        self::assertMatchesRegularExpression('#<section class="panel overflow-x-auto" data-testid="routing-kit-definitions-table">\s*<div class="min-w-0">\s*<table class="data-table w-full">#', $html);
        self::assertStringContainsString('<th class="w-0 px-2 py-2 text-end">Actions</th>', $html);
        self::assertStringContainsString('<td class="px-2 py-3"><span class="badge badge--ok">yes</span></td>', $html);
        self::assertStringContainsString('<td class="px-2 py-3"><span class="badge">no</span></td>', $html);
        self::assertStringContainsString('<div class="inline-flex gap-1 nowo-ui-row-actions nowo-ui-row-actions--icon" aria-label="Actions">', $html);

        // action_inner partial + CSP-friendly delete confirmation attributes
        self::assertStringContainsString('<span class="visually-hidden">Edit</span>', $html);
        self::assertStringContainsString('&quot;class&quot;:&quot;inline&quot;,&quot;data-controller&quot;:&quot;confirm-submit&quot;,&quot;data-action&quot;:&quot;submit-&gt;confirm-submit#confirm&quot;,&quot;data-confirm-submit-message-value&quot;:&quot;Delete?&quot;', $html);
        self::assertStringNotContainsString('onsubmit', $html);

        // pagination partial inside the results panel, then the import modal from the host shell
        self::assertMatchesRegularExpression('#<nav class="host-pagination" data-route="nowo_routing_kit_panel" data-page="1" data-total="2" data-count="2"></nav>\s*</section>#', $html);
        self::assertMatchesRegularExpression('#<dialog class="confirm-dialog" id="modal-routing-import" aria-labelledby="modal-routing-import-label">\s*<h2 class="confirm-dialog__title" id="modal-routing-import-label">Import \(signed JSON\)</h2>\s*<form-start name="routing_panel_import" options="{&quot;action&quot;:&quot;\\\\/_routing-kit\\\\/import&quot;,&quot;method&quot;:&quot;POST&quot;,&quot;attr&quot;:{&quot;class&quot;:&quot;kit-modal__panel&quot;}}">\s*<div class="confirm-dialog__content">\s*<form-row name="payload_json">\s*<form-row name="replace_all">\s*</div>\s*<div class="confirm-dialog__actions">\s*<button type="button" class="btn-ghost" data-nowo-modal-close>Cancel</button>\s*<button type="submit" class="btn-primary no-underline">Import</button>#', $html);
        self::assertStringNotContainsString('<h2>Import (signed JSON)</h2>', $html, 'Inline import section replaced by the modal.');
    }

    public function testEmptyIndexUsesTheEmptyClass(): void
    {
        $html = $this->hostRenderer()->render('@NowoRoutingKitBundle/panel/index.html.twig', PanelDefaultOutputTest::indexContext([]));

        self::assertStringContainsString('<tr><td colspan="6" class="px-2 py-4">No path definitions yet.</td></tr>', $html);
    }

    public function testFormWithHostConfig(): void
    {
        $html = $this->hostRenderer()->render('@NowoRoutingKitBundle/panel/form.html.twig', PanelDefaultOutputTest::formContext(null, ['Invalid path'], []));

        self::assertStringContainsString('<title>Create path — Host</title>', $html);
        self::assertMatchesRegularExpression('#<div class="host-actions"><a class="btn-ghost no-underline" href="/_routing-kit/">&larr; Routing Kit</a></div>#', $html);
        self::assertMatchesRegularExpression('#<section class="panel" data-testid="routing-kit-definition-form">\s*<h2 class="mb-1 text-base font-semibold">Create path</h2>\s*<p class="mb-4 text-sm">Define path#', $html);
        self::assertStringContainsString('<div class="alert alert--danger" role="alert">', $html);
        self::assertStringContainsString('{&quot;attr&quot;:{&quot;class&quot;:&quot;nowo-ui-form nowo-ui-form--stack&quot;}}', $html);
        self::assertMatchesRegularExpression('#<div class="mt-4 flex gap-2">\s*<button type="submit" class="btn-primary no-underline nowo-ui-action-save">Save</button>\s*<a class="btn-ghost nowo-ui-action-cancel" href="/_routing-kit/">Cancel</a>#', $html);
        self::assertSame(1, substr_count($html, '<h1'), 'Only the layout heading.');
    }

    public function testRedirectsPagesFollowTheClassMap(): void
    {
        $html = $this->hostRenderer(['nowo_routing_kit_url_redirects_enabled' => true])->render(
            '@NowoRoutingKitBundle/panel/redirects/index.html.twig',
            PanelDefaultOutputTest::redirectsContext([PanelDefaultOutputTest::redirect('r1', true)], null),
        );

        self::assertStringContainsString('<a class="btn-primary no-underline nowo-ui-action-create" href="/_routing-kit/redirects/new" data-testid="redirect-new">', $html);
        self::assertStringContainsString('<section class="panel overflow-x-auto" data-testid="routing-kit-redirects-table">', $html);
        self::assertStringContainsString('<span class="badge badge--ok">Yes</span>', $html);
        self::assertStringContainsString('class="btn-danger btn-icon" href="/_routing-kit/redirects/r1/delete" data-testid="redirect-delete" title="Delete" aria-label="Delete">', $html);
    }

    public function testModalImportWithDefaultShellAndAssets(): void
    {
        $html = (new PanelTemplateRenderer([], ['nowo_routing_kit_import_display' => 'modal']))
            ->render('@NowoRoutingKitBundle/panel/index.html.twig', PanelDefaultOutputTest::indexContext([]));

        self::assertStringContainsString('<script src="/assets/nowo_ui_kit/js/nowo-ui-modal.js" defer></script>', $html);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-modal" id="modal-routing-import" tabindex="-1" aria-labelledby="modal-routing-import-label" aria-hidden="true">#', $html);
        self::assertStringContainsString('data-nowo-modal-open data-nowo-modal-target="modal-routing-import">Import</button>', $html);
    }

    public function testBangNamespaceLetsAnAppOverrideExtendTheOriginalPage(): void
    {
        $override = <<<'TWIG'
            {% extends '@!NowoRoutingKitBundle/panel/index.html.twig' %}
            {% block rk_import %}<p class="host-import">custom</p>{% endblock %}
            TWIG;

        $html = (new PanelTemplateRenderer(['@NowoRoutingKitBundle/panel/index.html.twig' => $override]))
            ->render('@NowoRoutingKitBundle/panel/index.html.twig', PanelDefaultOutputTest::indexContext([]));

        self::assertStringContainsString('<p class="host-import">custom</p>', $html);
        self::assertStringContainsString('nowo-ui-page-header', $html, 'Everything else still comes from the bundle page.');
        self::assertStringNotContainsString('Import (signed JSON)', $html);
    }

    public function testCatalogueIsUsedForTheOtherLocales(): void
    {
        $html = (new PanelTemplateRenderer([], [], 'de'))->render('@NowoRoutingKitBundle/panel/index.html.twig', PanelDefaultOutputTest::indexContext([], true));

        self::assertStringContainsString('<strong>UNSICHER:</strong>', $html);
        self::assertStringContainsString('>Exportieren</button>', $html);
        self::assertStringContainsString('0 Zeile(n)', $html);
        self::assertStringContainsString('return confirm', PanelTemplateRenderer::normalize((new PanelTemplateRenderer([], [], 'fr'))->render('@NowoRoutingKitBundle/panel/index.html.twig', PanelDefaultOutputTest::indexContext([new RoutePathDefinition('a', 'en', '/a', id: 'x')]))));
    }

    /**
     * @param array<string, mixed> $extraGlobals
     */
    private function hostRenderer(array $extraGlobals = []): PanelTemplateRenderer
    {
        return new PanelTemplateRenderer([
            'host/layout.html.twig'       => self::HOST_LAYOUT,
            'host/_modal_shell.html.twig' => self::HOST_MODAL_SHELL,
            'host/_pagination.html.twig'  => self::HOST_PAGINATION,
        ], [
            'nowo_routing_kit_layout_template' => 'host/layout.html.twig',
            'nowo_routing_kit_css_framework'   => 'tailwind',
            'nowo_ui_kit_css_framework'        => 'tailwind',
            'nowo_ui_kit_row_actions_display'  => 'icon',
            'nowo_routing_kit_classes'         => self::HOST_CLASSES,
            'nowo_routing_kit_partials'        => [
                'modal_shell'  => 'host/_modal_shell.html.twig',
                'pagination'   => 'host/_pagination.html.twig',
                'action_inner' => '@NowoUiKitBundle/partials/_action_inner.html.twig',
            ],
            'nowo_routing_kit_ui_kit_assets'       => false,
            'nowo_routing_kit_import_display'      => 'modal',
            'nowo_routing_kit_show_row_count'      => false,
            'nowo_routing_kit_primary_action_last' => true,
            'nowo_routing_kit_delete_confirm_attr' => [
                'data-controller'                   => 'confirm-submit',
                'data-action'                       => 'submit->confirm-submit#confirm',
                'data-confirm-submit-message-value' => '%message%',
            ],
        ] + $extraGlobals);
    }
}
