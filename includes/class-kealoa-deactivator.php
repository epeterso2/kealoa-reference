<?php
/**
 * Plugin Deactivator
 *
 * Handles cleanup tasks on plugin deactivation.
 *
 * @package KEALOA_Reference
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Kealoa_Deactivator
 *
 * Handles plugin deactivation tasks.
 */
class Kealoa_Deactivator {

    /**
     * Deactivate the plugin
     *
     * Performs cleanup tasks. Note: Database tables are NOT dropped
     * on deactivation to preserve data. Use uninstall.php for complete removal.
     */
    public static function deactivate(): void {
        // Clear any scheduled cron jobs
        self::clear_scheduled_events();
        
        // Clear transients
        self::clear_transients();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Clear scheduled cron events
     */
    private static function clear_scheduled_events(): void {
        $cron_hooks = [
            'kealoa_daily_cleanup',
            'kealoa_weekly_stats',
        ];
        
        foreach ($cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }

    /**
     * Clear plugin transients
     */
    private static function clear_transients(): void {
        global $wpdb;
        
        // Delete all transients with kealoa_ prefix
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_kealoa_%' 
            OR option_name LIKE '_transient_timeout_kealoa_%'"
        );
    }
}
