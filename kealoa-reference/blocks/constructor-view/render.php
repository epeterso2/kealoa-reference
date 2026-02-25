<?php
/**
 * @copyright 2026 Eric Peterson (eric@puzzlehead.org)
 * @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
 *
 * Constructor View Block Render
 *
 * @package KEALOA_Reference
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$constructor_id = $attributes['constructorId'] ?? 0;

if (!$constructor_id) {
    echo '<p class="kealoa-block-placeholder">' . 
        esc_html__('Please select a constructor from the block settings.', 'kealoa-reference') . 
        '</p>';
    return;
}

$shortcodes = new Kealoa_Shortcodes();
echo $shortcodes->render_constructor(['id' => $constructor_id]);
