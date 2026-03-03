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
     *   key        – unique identifier
     *   role       – host | player | constructor | editor
     *   label      – human-readable short name
     *   short      – brief word shown on the badge
     *   unit       – '' or '%' (appended to display values)
     *   icon       – SVG filename (without directory)
     *   min        – minimum value to earn the first badge
     *   increment  – value increase per additional tier
     *   max        – value at which no further tiers are earned
     */
    private const BADGE_DEFINITIONS = [
        [
            'key'       => 'host_rounds',
            'role'      => 'host',
            'label'     => 'Rounds Hosted',
            'short'     => 'Hosted',
            'unit'      => '',
            'icon'      => 'host-rounds.svg',
            'min'       => 25,
            'increment' => 25,
            'max'       => 200,
        ],
        [
            'key'       => 'host_players',
            'role'      => 'host',
            'label'     => 'Players in a Single Round',
            'short'     => 'Players',
            'unit'      => '',
            'icon'      => 'host-players.svg',
            'min'       => 2,
            'increment' => 1,
            'max'       => 10,
        ],
        [
            'key'       => 'host_streak',
            'role'      => 'host',
            'label'     => 'Host Correct Streak',
            'short'     => 'Streak',
            'unit'      => '',
            'icon'      => 'host-streak.svg',
            'min'       => 10,
            'increment' => 10,
            'max'       => 100,
        ],
        [
            'key'       => 'player_rounds',
            'role'      => 'player',
            'label'     => 'Rounds Played',
            'short'     => 'Played',
            'unit'      => '',
            'icon'      => 'player-rounds.svg',
            'min'       => 25,
            'increment' => 25,
            'max'       => 200,
        ],
        [
            'key'       => 'player_streak',
            'role'      => 'player',
            'label'     => 'Best Streak',
            'short'     => 'Streak',
            'unit'      => '',
            'icon'      => 'player-streak.svg',
            'min'       => 5,
            'increment' => 1,
            'max'       => 10,
        ],
        [
            'key'       => 'player_correct',
            'role'      => 'player',
            'label'     => 'Best Correct',
            'short'     => 'Correct',
            'unit'      => '',
            'icon'      => 'player-correct.svg',
            'min'       => 5,
            'increment' => 1,
            'max'       => 10,
        ],
        [
            'key'       => 'player_accuracy',
            'role'      => 'player',
            'label'     => 'Overall Accuracy',
            'short'     => 'Accuracy',
            'unit'      => '%',
            'icon'      => 'player-accuracy.svg',
            'min'       => 50,
            'increment' => 10,
            'max'       => 100,
        ],
        [
            'key'       => 'constructor_puzzles',
            'role'      => 'constructor',
            'label'     => 'Puzzles Used',
            'short'     => 'Puzzles',
            'unit'      => '',
            'icon'      => 'constructor-puzzles.svg',
            'min'       => 5,
            'increment' => 5,
            'max'       => 50,
        ],
        [
            'key'       => 'editor_puzzles',
            'role'      => 'editor',
            'label'     => 'Puzzles Edited',
            'short'     => 'Edited',
            'unit'      => '',
            'icon'      => 'editor-puzzles.svg',
            'min'       => 25,
            'increment' => 25,
            'max'       => 1000,
        ],
    ];

    /**
     * Tier class names ordered by progression percentage.
     */
    private const TIER_CLASSES = ['bronze', 'silver', 'gold', 'platinum', 'diamond'];

    /**
     * Compute earned badges for a person.
     *
     * @param array<string, float|int> $metrics Associative array keyed by badge
     *                                          definition 'key', valued with the
     *                                          person's current metric value.
     *                                          Missing keys are silently skipped.
     * @return array<int, array{key: string, role: string, label: string, short: string,
     *               unit: string, icon: string, value: float|int, tier: int,
     *               max_tier: int, tier_class: string, threshold: float|int}>
     */
    public static function compute_badges(array $metrics): array {
        $badges = [];

        foreach (self::BADGE_DEFINITIONS as $def) {
            if (!array_key_exists($def['key'], $metrics)) {
                continue;
            }

            $value = $metrics[$def['key']];
            if ($value < $def['min']) {
                continue;
            }

            // Calculate max tier count: ((max - min) / increment) + 1
            $max_tier = (int) (($def['max'] - $def['min']) / $def['increment']) + 1;

            // Calculate achieved tier (capped at max)
            $capped_value = min($value, $def['max']);
            $tier = (int) floor(($capped_value - $def['min']) / $def['increment']) + 1;
            $tier = min($tier, $max_tier);

            // Determine tier class based on progression percentage
            $tier_pct = ($tier - 1) / max(1, $max_tier - 1); // 0.0 to 1.0
            $class_index = (int) floor($tier_pct * (count(self::TIER_CLASSES) - 1));
            $class_index = min($class_index, count(self::TIER_CLASSES) - 1);
            $tier_class = self::TIER_CLASSES[$class_index];

            // The threshold value the person reached
            $threshold = $def['min'] + ($tier - 1) * $def['increment'];

            $badges[] = [
                'key'        => $def['key'],
                'role'       => $def['role'],
                'label'      => $def['label'],
                'short'      => $def['short'],
                'unit'       => $def['unit'],
                'icon'       => $def['icon'],
                'value'      => $value,
                'tier'       => $tier,
                'max_tier'   => $max_tier,
                'tier_class' => $tier_class,
                'threshold'  => $threshold,
            ];
        }

        return $badges;
    }

    /**
     * Render badge HTML.
     *
     * @param array $badges Array of badge data from compute_badges().
     * @return string HTML string, empty if no badges earned.
     */
    public static function render_badges(array $badges): string {
        if (empty($badges)) {
            return '';
        }

        $badges_url = KEALOA_PLUGIN_URL . 'assets/images/badges/';
        $html = '<div class="kealoa-person-badges">';

        foreach ($badges as $badge) {
            $display_value = $badge['unit'] === '%'
                ? number_format_i18n($badge['value'], 1) . '%'
                : number_format_i18n((int) $badge['value']);

            $threshold_value = $badge['unit'] === '%'
                ? number_format_i18n($badge['threshold'], 0) . '%'
                : number_format_i18n((int) $badge['threshold']);

            $tooltip = sprintf(
                /* translators: 1: badge label, 2: current value, 3: current tier, 4: max tier */
                __('%1$s: %2$s (Tier %3$d/%4$d)', 'kealoa-reference'),
                $badge['label'],
                $display_value,
                $badge['tier'],
                $badge['max_tier']
            );

            $html .= sprintf(
                '<div class="kealoa-badge kealoa-badge--%s kealoa-badge--%s" title="%s">'
                    . '<img src="%s" alt="%s" class="kealoa-badge__icon" />'
                    . '<span class="kealoa-badge__text">'
                        . '<span class="kealoa-badge__value">%s</span>'
                        . '<span class="kealoa-badge__label">%s</span>'
                    . '</span>'
                . '</div>',
                esc_attr($badge['tier_class']),
                esc_attr($badge['role']),
                esc_attr($tooltip),
                esc_url($badges_url . $badge['icon']),
                esc_attr($badge['label']),
                esc_html($threshold_value),
                esc_html($badge['short'])
            );
        }

        $html .= '</div>';

        return $html;
    }
}
