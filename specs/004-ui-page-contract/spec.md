# Feature Specification: UiKit page contract for the admin panel

**Feature Branch**: `feature/ui-page-contract`  
**Status**: Implemented (unreleased, next minor)  
**Created**: 2026-10-18  
**Depends on**: [`003-admin-chrome-hooks`](../003-admin-chrome-hooks/spec.md)  
**Upstream request**: REQ-UI-003 phase 1 (`BUNDLES_UI_PAGE_CONTRACT.md`), UiKit 2.0.0

---

## Summary

The panel pages stop shipping their own chrome (layout, page header, table, pagination, modal shell, inline
`confirm()`, `PanelChrome` class map) and extend the UiKit 2 page scaffolds, fed by UiKit view models. A host themes
each view type once in `nowo_ui_kit` and the panel inherits it. Configuration moves to the canonical `web_ui.*`
vocabulary; the 1.x keys stay as deprecated aliases (bridged into `nowo_ui_kit.theme.bundles.NowoRoutingKitBundle`)
for one minor version.

## User scenarios

- **US-01 (P1)** — Given a host with `nowo_ui_kit.layouts.admin` and `nowo_ui_kit.theme`, when it opens the panel,
  then the paths / redirects lists, forms and confirm page render in its layout with its theme, without any
  RoutingKit-specific configuration or template copy.
- **US-02 (P1)** — Given a strict CSP, when an operator deletes a path or a redirect from a list, then the UiKit
  confirm dialog asks for confirmation (no inline JavaScript); without JavaScript the redirect delete page still works.
- **US-03 (P1)** — Given a 1.8 configuration (`panel.path_prefix`, `panel.role`, `web_ui.classes`, `web_ui.partials`,
  a `body` layout), when the host upgrades, then the panel keeps working and deprecations name the replacements.
- **US-04 (P2)** — Given one page-specific need, when the host sets `web_ui.templates.<page>` to a template that
  extends `@!NowoRoutingKitBundle/…`, then it overrides one data block only.

## Requirements

- **FR-001** Pages → scaffolds: `panel/index`, `panel/redirects/index` → `page/list`; `panel/form`,
  `panel/redirects/form` → `page/form`; `panel/redirects/delete` → `page/confirm` (see `docs/TEMPLATES.md`).
- **FR-002** Controllers build `ListPage` / `FormPage` / `ConfirmPage` with `Label`s of the `NowoRoutingKitBundle`
  domain, `bundle: NowoRoutingKitBundle`, `layout: web_ui.layout_template`; deletes are POST `Action`s with a CSRF
  `FormView` and a `Confirm`.
- **FR-003** Removed local chrome: `panel/layout`, `panel/base`, `panel/_modal_shell`, inline `confirm()`; the import
  modal uses the UiKit `modal_shell` partial, the nav the `tabs` partial, notices the `flashes` partial / slots.
- **FR-004** Canonical keys `web_ui.enabled`, `web_ui.path_prefix`, `web_ui.layout_template` (default null),
  `web_ui.templates.<page>`; deprecated aliases `panel.enabled`, `panel.path_prefix`, `panel.role`; deprecated
  `web_ui.classes` / `partials` (bridged), `css_framework`, `icon_set`, `ui_kit_assets`, `main_landmark`,
  `delete_confirm_attr`.
- **FR-005** A `web_ui.layout_template` without the canonical blocks is wrapped by a deprecated bridge.
- **FR-006** Every label / placeholder used by the pages exists in the 7 shipped locales.

## Success criteria

- **SC-001** `PageContractTest` / `PageContractGermanTest` (UiKit `PageContractTestCase`) green for every page and state.
- **SC-002** BC tests for the deprecated keys, the theme bridge and the legacy layout bridge.
- **SC-003** Demo: all admin pages 200, `lint:container` without bundle deprecations, Playwright e2e green, WebP
  screenshots refreshed; gates (CS, Rector, PHPStan, Igor, Twig lint, translations, 100 % lines) green.
