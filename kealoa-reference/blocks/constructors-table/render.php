<?php
/**
 * @copyright 2026 Eric Peterson (eric@puzzlehead.org)
 * @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
 *
 * Constructors Table Block Render
 *
 * @package KEALOA_Reference
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Legacy block — delegates to persons table after v2.0.0 migration
$shortcodes = new Kealoa_Shortcodes();

echo $shortcodes->render_persons_table([]);
