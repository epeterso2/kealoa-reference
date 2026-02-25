<?php
/**
 * @copyright 2026 Eric Peterson (eric@puzzlehead.org)
 * @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
 *
 * Editor View Block Render
 *
 * @package KEALOA_Reference
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$editor_name = $attributes['editorName'] ?? '';

if (!$editor_name) {
    echo '<p class="kealoa-block-placeholder">' . 
        esc_html__('Please select an editor from the block settings.', 'kealoa-reference') . 
        '</p>';
    return;
}

$shortcodes = new Kealoa_Shortcodes();
echo $shortcodes->render_editor(['name' => $editor_name]);
