<?php
/**
 * Puzzle View Block Render
 *
 * @package KEALOA_Reference
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$puzzle_date = $attributes['puzzleDate'] ?? '';

if (!$puzzle_date) {
    echo '<p class="kealoa-block-placeholder">' .
        esc_html__('Please enter a puzzle date in the block settings.', 'kealoa-reference') .
        '</p>';
    return;
}

$shortcodes = new Kealoa_Shortcodes();
echo $shortcodes->render_puzzle(['date' => $puzzle_date]);
