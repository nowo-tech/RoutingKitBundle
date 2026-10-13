# Feature Specification: Admin chrome hooks

**Feature Branch**: `003-admin-chrome-hooks`  
**Status**: Implemented (v1.7.0)  
**Created**: 2026-10-13  
**Depends on**: [`001-routing-kit`](../001-routing-kit/spec.md), [`002-url-redirects`](../002-url-redirects/spec.md)  
**Upstream request**: symfony-beacon spec 134 FR-010 (kit upstream improvements)

---

## Summary

Hosts embedding the panel in their own admin (Beacon: Tailwind tokens, page header with actions, `<dialog>` modals, UiKit `row_actions_display`, Stimulus confirmation) copied `panel/base`, `panel/index` and `panel/form` (~190 lines) to change chrome only. This release exposes that chrome as configuration, partial paths, Twig blocks and a small layout contract, and removes the hard-coded English strings. Behaviour (routes, form variables, CSRF, controllers) is untouched.

## User scenarios

- **US-01 (P1)** — Given a host with its own classes, when it sets `web_ui.classes`, then every panel page uses them and no page copy is needed.
- **US-02 (P1)** — Given a host layout with a page header, when it sets `rk_page_header_in_layout` and renders `block('rk_page_actions')` / `block('rk_back_link')`, then the actions move to its header and the page header disappears.
- **US-03 (P1)** — Given a host that shows the signed import in a modal, when it sets `import_display: modal` (and optionally `partials.modal_shell`), then the import form renders in `#modal-routing-import` with a trigger in the page actions.
- **US-04 (P1)** — Given a strict CSP, when the host sets `delete_confirm_attr`, then the delete form carries those attributes and no inline `onsubmit`.
- **US-05 (P2)** — Given one structural change, when the host overrides a page with `{% extends '@!NowoRoutingKitBundle/…' %}`, then it redefines a single block.
- **US-06 (P1)** — Given an existing app with no new options, when it upgrades, then the HTML (and English text) is unchanged.

## Requirements

- **FR-001** `web_ui.classes` (`PanelChrome::CLASS_KEYS`, unknown keys rejected, empty values ignored) → Twig global `nowo_routing_kit_classes`. Wrapper keys (`results`, `form_panel`) add an element (with the `data-testid` hosts rely on) only when set.
- **FR-002** `web_ui.partials`: `nav`, `pagination`, `action_inner`, `modal_shell` → `nowo_routing_kit_partials`.
- **FR-003** Options `ui_kit_assets`, `import_display`, `show_row_count`, `primary_action_last`, `delete_confirm_attr` (`%message%` placeholder).
- **FR-004** Blocks on `panel/index`, `panel/form`, `panel/redirects/*` (see USAGE § Customising the admin chrome); slot blocks are definitions the stock layout never renders.
- **FR-005** Layout contract: `rk_page_header_in_layout`, `rk_title_suffix`.
- **FR-006** `TwigPathsPass` registers `!NowoRoutingKitBundle`.
- **FR-007** i18n: new `panel.*` keys in 7 locales (English values identical to the former literals); `panel.*` translated in de/fr/it/nl/pt; import labels via keys; parity + template-key tests.

## Success criteria

- **SC-001** `PanelDefaultOutputTest` (8 renders incl. redirects pages) proves default output equals v1.6.0.
- **SC-002** `PanelChromeHooksTest` renders a Beacon-like host without page copies (classes, partials, modal import, CSP confirmation, layout slots, `@!` override, other locales).
- **SC-003** `make release-check` gates green (CS, Rector, PHPStan, Igor, translations, 100 % line coverage, demos).

## Host adoption — symfony-beacon forks (spec 134 FR-024)

| Beacon fork | Verdict | How |
|-------------|---------|-----|
| `panel/base.html.twig` | **Delete** | `web_ui.ui_kit_assets: false`. |
| `panel/index.html.twig` | **Delete** | `classes` (`results` keeps `data-testid="routing-kit-definitions-table"`, `badge_yes`/`badge_no`, `th_actions` adds the Actions header, `inline_form`, `delete_form`), `partials.pagination: kit/_pagination.html.twig`, `partials.action_inner`, `import_display: modal` + `partials.modal_shell: kit/routing/_modal_shell.html.twig` (host `<dialog>`, ~8 lines, not a fork) + `modal_*` classes, `show_row_count: false`, `primary_action_last: true`, `delete_confirm_attr` (Stimulus `confirm-submit`). |
| `panel/form.html.twig` | **Delete** | `form_panel` (keeps `data-testid="routing-kit-definition-form"`), `form_heading`, `form_hint`, `alert`, `form`, `form_actions`; back link via `block('rk_back_link')` in the layout header. |
| `kit/routing_kit_panel_layout.html.twig` (bridge) | **Stays, +~5 lines** | `rk_page_header_in_layout`, `rk_title_suffix`, `kit_admin_header_actions` from `rk_back_link` + `rk_page_actions`. |
| `translations/NowoRoutingKitBundle.*.yaml` (host) | **Shrink** | Keys now shipped upstream; keep only Beacon wording that differs (capitalised Yes/No, "Payload JSON", confirmation text). |

Accepted deltas: export / clear-cache buttons keep their `form` wrappers with the configured `inline_form` class (same as the fork); the import modal button order (Cancel, Import) matches the fork.

## Out of scope

- Shipping a confirmation JavaScript in the bundle (hosts wire their own via `delete_confirm_attr`).
