<?php
/**
 * Rounds Stats Block Render
 *
 * @package KEALOA_Reference
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$shortcodes = new Kealoa_Shortcodes();

echo $shortcodes->render_rounds_stats([]);
