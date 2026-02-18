<?php
/**
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
