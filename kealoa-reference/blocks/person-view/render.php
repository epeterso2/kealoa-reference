<?php
/**
 * Person View Block Render
 *
 * @package KEALOA_Reference
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$person_id = $attributes['personId'] ?? 0;

if (!$person_id) {
    echo '<p class="kealoa-block-placeholder">' . 
        esc_html__('Please select a person from the block settings.', 'kealoa-reference') . 
        '</p>';
    return;
}

$shortcodes = new Kealoa_Shortcodes();
echo $shortcodes->render_person(['id' => $person_id]);
