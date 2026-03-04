<?php
/**
 * @copyright 2026 Eric Peterson (eric@puzzlehead.org)
 * @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
 *
 * Badge System
 *
 * Computes and renders achievement badges for persons based on their
 * performance metrics across host, player, constructor, and editor roles.
 *
 * @package KEALOA_Reference
 */

declare(strict_types=1);

namespace com\epeterso2\kealoa;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Kealoa_Badges {

    /**
     * Badge definitions: each metric that can earn a badge.
     *
     * Keys:
     *   key   – unique identifier
     *   role  – host | player | constructor | editor
     *   label – human-readable short name
     *   short – brief word shown on the badge
     *   unit  – '' or '%' (appended to display values)
     *   icon  – SVG filename (without directory)
     */
    private const BADGE_DEFINITIONS = [
        [
            'key'   => 'host_rounds',
            'role'  => 'host',
            'label' => 'Rounds Hosted',
            'short' => 'Hosted',
            'unit'  => '',
            'icon'  => 'host-rounds.svg',
        ],
        [
            'key'   => 'host_players',
            'role'  => 'host',
            'label' => 'Unique Players Hosted',
            'short' => 'Players',
            'unit'  => '',
            'icon'  => 'host-players.svg',
        ],
        [
            'key'   => 'host_streak',
            'role'  => 'host',
            'label' => 'Host Correct Streak',
            'short' => 'Streak',
            'unit'  => '',
            'icon'  => 'host-streak.svg',
        ],
        [
            'key'   => 'host_accuracy',
            'role'  => 'host',
            'label' => 'Host Accuracy',
            'short' => 'Accuracy',
            'unit'  => '%',
            'icon'  => 'player-accuracy.svg',
        ],
        [
            'key'   => 'player_rounds',
            'role'  => 'player',
            'label' => 'Rounds Played',
            'short' => 'Played',
            'unit'  => '',
            'icon'  => 'player-rounds.svg',
        ],
        [
            'key'   => 'player_accuracy',
            'role'  => 'player',
            'label' => 'Overall Accuracy',
            'short' => 'Accuracy',
            'unit'  => '%',
            'icon'  => 'player-accuracy.svg',
        ],
        [
            'key'   => 'player_correct',
            'role'  => 'player',
            'label' => 'Best Correct',
            'short' => 'Correct',
            'unit'  => '',
            'icon'  => 'player-correct.svg',
        ],
        [
            'key'   => 'player_streak',
            'role'  => 'player',
            'label' => 'Best Streak',
            'short' => 'Streak',
            'unit'  => '',
            'icon'  => 'player-streak.svg',
        ],
        [
            'key'   => 'constructor_puzzles',
            'role'  => 'constructor',
            'label' => 'Puzzles Used',
            'short' => 'Puzzles',
            'unit'  => '',
            'icon'  => 'constructor-puzzles.svg',
        ],
        [
            'key'   => 'constructor_clues',
            'role'  => 'constructor',
            'label' => 'Clues Used',
            'short' => 'Clues',
            'unit'  => '',
            'icon'  => 'constructor-clues.svg',
        ],
        [
            'key'   => 'constructor_accuracy',
            'role'  => 'constructor',
            'label' => 'Constructor Accuracy',
            'short' => 'Accuracy',
            'unit'  => '%',
            'icon'  => 'player-accuracy.svg',
        ],
        [
            'key'   => 'editor_puzzles',
            'role'  => 'editor',
            'label' => 'Puzzles Edited',
            'short' => 'Edited',
            'unit'  => '',
            'icon'  => 'editor-puzzles.svg',
        ],
        [
            'key'   => 'editor_accuracy',
            'role'  => 'editor',
            'label' => 'Editor Accuracy',
            'short' => 'Accuracy',
            'unit'  => '%',
            'icon'  => 'player-accuracy.svg',
        ],
    ];

    /**
     * Compute badges for a person.
     *
     * Returns a badge for every definition whose key exists in the supplied
     * metrics, regardless of the metric value.
     *
     * @param array<string, float|int> $metrics Associative array keyed by badge
     *                                          definition 'key', valued with the
     *                                          person's current metric value.
     *                                          Missing keys are silently skipped.
     * @return array<int, array{key: string, role: string, label: string, short: string,
     *               unit: string, icon: string, value: float|int}>
     */
    public static function compute_badges(array $metrics): array {
        $badges = [];

        foreach (self::BADGE_DEFINITIONS as $def) {
            if (!array_key_exists($def['key'], $metrics)) {
                continue;
            }

            $badges[] = [
                'key'   => $def['key'],
                'role'  => $def['role'],
                'label' => $def['label'],
                'short' => $def['short'],
                'unit'  => $def['unit'],
                'icon'  => $def['icon'],
                'value' => $metrics[$def['key']],
            ];
        }

        return $badges;
    }

    /**
     * Render badge HTML.
     *
     * @param array $badges Array of badge data from compute_badges().
     * @return string HTML string, empty if no badges.
     */
    public static function render_badges(array $badges): string {
        if (empty($badges)) {
            return '';
        }

        $badges_url = KEALOA_PLUGIN_URL . 'assets/images/badges/';
        $asset_version = KEALOA_VERSION . '.' . get_option('kealoa_cache_version', '1');
        $html = '<div class="kealoa-person-badges">';

        // Group badges by role to render each role on its own line
        $grouped = [];
        foreach ($badges as $badge) {
            $grouped[$badge['role']][] = $badge;
        }

        foreach ($grouped as $role => $role_badges) {
            $html .= '<div class="kealoa-badge-row">';
            foreach ($role_badges as $badge) {
                $display_value = $badge['unit'] === '%'
                    ? number_format_i18n($badge['value'], 0) . '%'
                    : number_format_i18n((int) $badge['value']);

                $tooltip = sprintf(
                    /* translators: 1: badge label, 2: current value */
                    __('%1$s: %2$s', 'kealoa-reference'),
                    $badge['label'],
                    $display_value
                );

                $html .= sprintf(
                    '<div class="kealoa-badge kealoa-badge--%s" title="%s">'
                        . '<img src="%s" alt="%s" class="kealoa-badge__icon" />'
                        . '<span class="kealoa-badge__text">'
                            . '<span class="kealoa-badge__value">%s</span>'
                            . '<span class="kealoa-badge__label">%s</span>'
                        . '</span>'
                    . '</div>',
                    esc_attr($badge['role']),
                    esc_attr($tooltip),
                    esc_url($badges_url . $badge['icon'] . '?ver=' . $asset_version),
                    esc_attr($badge['label']),
                    esc_html($display_value),
                    esc_html($badge['short'])
                );
            }
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}
