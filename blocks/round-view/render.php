<?php
/**
 * Round View Block Render
 *
 * @package KEALOA_Reference
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$round_id = $attributes['roundId'] ?? 0;

if (!$round_id) {
    echo '<p class="kealoa-block-placeholder">' . 
        esc_html__('Please select a round from the block settings.', 'kealoa-reference') . 
        '</p>';
    return;
}

$shortcodes = new Kealoa_Shortcodes();
echo $shortcodes->render_round(['id' => $round_id]);
