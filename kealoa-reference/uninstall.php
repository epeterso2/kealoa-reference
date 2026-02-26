<?php
/**
 * @copyright 2026 Eric Peterson (eric@puzzlehead.org)
 * @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
 *
 * Uninstall KEALOA Reference
 *
 * This file is executed when the plugin is deleted via the WordPress admin.
 * It removes all plugin data including database tables and options.
 *
 * @package KEALOA_Reference
 */

// Exit if uninstall not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check user permissions
if (!current_user_can('activate_plugins')) {
    return;
}

global $wpdb;

// Drop all custom tables (including legacy constructors table if still present)
$tables = [
    $wpdb->prefix . 'kealoa_guesses',
    $wpdb->prefix . 'kealoa_clues',
    $wpdb->prefix . 'kealoa_round_guessers',
    $wpdb->prefix . 'kealoa_round_solutions',
    $wpdb->prefix . 'kealoa_rounds',
    $wpdb->prefix . 'kealoa_puzzle_constructors',
    $wpdb->prefix . 'kealoa_puzzles',
    $wpdb->prefix . 'kealoa_persons',
    $wpdb->prefix . 'kealoa_constructors',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// Delete all options
$options = [
    'kealoa_db_version',
    'kealoa_items_per_page',
    'kealoa_date_format',
    'kealoa_debug_mode',
];

foreach ($options as $option) {
    delete_option($option);
}

// Delete all transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
    WHERE option_name LIKE '_transient_kealoa_%' 
    OR option_name LIKE '_transient_timeout_kealoa_%'"
);

// Delete any legacy editor media options
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'kealoa_editor_media_%'"
);

// Flush rewrite rules
flush_rewrite_rules();
