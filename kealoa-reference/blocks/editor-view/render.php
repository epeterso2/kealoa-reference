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

// Legacy block — look up person by editor name after v2.0.0 migration
$editor_name = $attributes['editorName'] ?? '';

if (!$editor_name) {
    echo '<p class="kealoa-block-placeholder">' .
        esc_html__('Please select a person from the block settings.', 'kealoa-reference') .
        '</p>';
    return;
}

$db = new Kealoa_DB();
$person = $db->get_person_by_name($editor_name);
if (!$person) {
    echo '<p class="kealoa-block-placeholder">' .
        esc_html__('Person not found.', 'kealoa-reference') .
        '</p>';
    return;
}

$shortcodes = new Kealoa_Shortcodes();
echo $shortcodes->render_person(['id' => (int) $person->id]);
