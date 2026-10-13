<?php

declare(strict_types=1);

namespace Nowo\RoutingKitBundle\Panel;

use function array_diff;
use function array_keys;
use function array_map;
use function array_values;
use function is_string;
use function trim;

/**
 * Admin chrome hooks (spec 003): keys accepted by {@code web_ui.classes} and {@code web_ui.partials}.
 *
 * Each class key replaces the class list a panel template would otherwise compute (UiKit macro or legacy class);
 * an unset key keeps the default markup. Each partial key replaces one included template.
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
}
