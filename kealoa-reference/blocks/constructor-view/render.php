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

// Legacy block — constructorId maps to person_id after v2.0.0 migration
$person_id = $attributes['constructorId'] ?? 0;

if (!$person_id) {
    echo '<p class="kealoa-block-placeholder">' .
        esc_html__('Please select a person from the block settings.', 'kealoa-reference') .
        '</p>';
    return;
}

$shortcodes = new Kealoa_Shortcodes();
echo $shortcodes->render_person(['id' => $person_id]);
