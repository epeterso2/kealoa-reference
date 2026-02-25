<?php
/**
 * @copyright 2026 Eric Peterson (eric@puzzlehead.org)
 * @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
 *
 * Rounds Table Block Render
 *
 * @package KEALOA_Reference
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$shortcodes = new Kealoa_Shortcodes();

echo $shortcodes->render_rounds_table([
    'limit' => $attributes['limit'] ?? 50,
    'order' => $attributes['order'] ?? 'DESC',
]);
