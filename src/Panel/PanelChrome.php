<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Panel;

use function array_diff;
use function array_keys;
use function array_map;
use function array_values;
use function in_array;
use function is_string;
use function trim;

/**
 * 1.x admin chrome hooks (spec 003): keys accepted by {@code web_ui.classes} and {@code web_ui.partials}.
 *
 * @deprecated since 1.9, the panel pages extend the UiKit scaffolds (REQ-UI-003) and read the UiKit theme slots.
 *             The maps are bridged into {@code nowo_ui_kit.theme.bundles.NowoRoutingKitBundle} by
 *             {@see self::themeBridge()} for one minor version; removed in 2.0.
 */
final class PanelChrome
{
    /**
     * @var list<string>
     */
    public const CLASS_KEYS = [
        // buttons and row actions
        'btn_primary', 'btn_secondary', 'btn_danger', 'btn_link',
        'action_edit', 'action_delete', 'row_actions', 'delete_form', 'inline_form',
        // page chrome
        'page_header', 'page_title', 'page_intro', 'toolbar', 'count', 'flash', 'muted', 'empty', 'badge_yes', 'badge_no',
        // tables
        'results', 'table_wrap', 'table', 'thead_row', 'th', 'th_actions', 'row', 'td', 'td_actions',
        // forms
        'form_panel', 'form_heading', 'form_hint', 'alert', 'form', 'form_actions',
        // import (inline section or modal)
        'import_title', 'import_form', 'modal_form', 'modal_form_body', 'modal_actions',
    ];

    /**
     * @var list<string>
     */
    public const PARTIAL_KEYS = ['nav', 'pagination', 'action_inner', 'modal_shell'];

    /**
     * 1.x class key => UiKit theme slot (null: no equivalent region, the value is dropped).
     *
     * @var array<string, string|null>
     */
    public const CLASS_SLOTS = [
        'btn_primary'     => 'btn_primary',
        'btn_secondary'   => 'btn_secondary',
        'btn_danger'      => 'btn_danger',
        'btn_link'        => 'btn_link',
        'action_edit'     => 'action_neutral',
        'action_delete'   => 'action_danger',
        'row_actions'     => 'row_actions',
        'delete_form'     => null,
        'inline_form'     => null,
        'page_header'     => 'page_header',
        'page_title'      => 'page_title',
        'page_intro'      => 'page_intro',
        'toolbar'         => 'toolbar',
        'count'           => null,
        'flash'           => 'flash_success',
        'muted'           => 'muted',
        'empty'           => 'empty',
        'badge_yes'       => 'badge_success',
        'badge_no'        => 'badge_neutral',
        'results'         => null,
        'table_wrap'      => 'table_wrap',
        'table'           => 'table',
        'thead_row'       => 'table_head_row',
        'th'              => 'table_head_cell',
        'th_actions'      => 'table_actions_head',
        'row'             => 'table_row',
        'td'              => 'table_cell',
        'td_actions'      => 'table_actions_cell',
        'form_panel'      => 'form_panel',
        'form_heading'    => null,
        'form_hint'       => null,
        'alert'           => 'flash_error',
        'form'            => 'form',
        'form_actions'    => 'form_actions',
        'import_title'    => 'card_title',
        'import_form'     => null,
        'modal_form'      => null,
        'modal_form_body' => 'modal_body',
        'modal_actions'   => null,
    ];

    /**
     * 1.x partial key => UiKit partial name (null: incompatible input, ignored).
     *
     * @var array<string, string|null>
     */
    public const PARTIAL_NAMES = [
        'nav'          => 'tabs',
        'pagination'   => 'pagination',
        'action_inner' => null,
        'modal_shell'  => null,
    ];

    /**
     * Row-action slots: UiKit adds their host classes to the action classes (no framework defaults to drop).
     */
    private const ADDITIVE_SLOTS = ['action_neutral', 'action_danger'];

    /**
     * @param array<array-key, mixed> $map
     * @param list<string> $allowed
     *
     * @return list<string> keys of $map that are not in $allowed
     */
    public static function unknownKeys(array $map, array $allowed): array
    {
        return array_values(array_map('strval', array_diff(array_keys($map), $allowed)));
    }

    /**
     * Drops empty / non-string values so templates fall back to their defaults.
     *
     * @param array<array-key, mixed> $map
     *
     * @return array<string, string>
     */
    public static function clean(array $map): array
    {
        $clean = [];
        foreach ($map as $key => $value) {
            if (is_string($key) && is_string($value) && trim($value) !== '') {
                $clean[$key] = trim($value);
            }
        }

        return $clean;
    }

    /**
     * Bridges the 1.x maps into a {@code nowo_ui_kit.theme.bundles.NowoRoutingKitBundle} entry. A 1.x class value
     * replaced the whole class list, so the slot drops UiKit's framework defaults ({@code framework_defaults: false})
     * and keeps only its semantic {@code nowo-ui-*} class plus the host value.
     *
     * @param array<array-key, mixed> $classes web_ui.classes
     * @param array<array-key, mixed> $partials web_ui.partials
     *
     * @return array{classes?: array<string, array{value: string, framework_defaults: bool}|string>, partials?: array<string, string>}
     */
    public static function themeBridge(array $classes, array $partials): array
    {
        $bridge = [];
        foreach (self::clean($classes) as $key => $value) {
            $slot = self::CLASS_SLOTS[$key] ?? null;
            if ($slot !== null) {
                $bridge['classes'][$slot] = in_array($slot, self::ADDITIVE_SLOTS, true)
                    ? $value
                    : ['value' => $value, 'framework_defaults' => false];
            }
        }
        foreach (self::clean($partials) as $key => $template) {
            $name = self::PARTIAL_NAMES[$key] ?? null;
            if ($name !== null) {
                $bridge['partials'][$name] = $template;
            }
        }

        return $bridge;
    }
}
