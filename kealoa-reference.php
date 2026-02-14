<?php
/**
 * Plugin Name: KEALOA Reference
 * Plugin URI: https://epeterso2.com/kealoa-reference
 * Description: A comprehensive plugin for managing KEALOA quiz game data from the Fill Me In podcast, including rounds, clues, puzzles, and player statistics.
 * Version: 1.0.56
 * Requires at least: 6.9
 * Requires PHP: 8.4
 * Author: Eric Peterson
 * Author URI: https://epeterso2.com
 * Author Email: eric@puzzlehead.org
 * License: CC BY-NC-SA 4.0
 * License URI: https://creativecommons.org/licenses/by-nc-sa/4.0/
 * Text Domain: kealoa-reference
 * Domain Path: /languages
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('KEALOA_VERSION', '1.0.56');
define('KEALOA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KEALOA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KEALOA_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('KEALOA_DB_VERSION', '1.0.0');

/**
 * Check PHP and WordPress version requirements
 */
function kealoa_check_requirements(): bool {
    $php_version = '8.4';
    $wp_version = '6.9';
    
    if (version_compare(PHP_VERSION, $php_version, '<')) {
        add_action('admin_notices', function() use ($php_version) {
            echo '<div class="notice notice-error"><p>';
            echo esc_html(sprintf(
                __('KEALOA Reference requires PHP %s or higher. You are running PHP %s.', 'kealoa-reference'),
                $php_version,
                PHP_VERSION
            ));
            echo '</p></div>';
        });
        return false;
    }
    
    if (version_compare(get_bloginfo('version'), $wp_version, '<')) {
        add_action('admin_notices', function() use ($wp_version) {
            echo '<div class="notice notice-error"><p>';
            echo esc_html(sprintf(
                __('KEALOA Reference requires WordPress %s or higher.', 'kealoa-reference'),
                $wp_version
            ));
            echo '</p></div>';
        });
        return false;
    }
    
    return true;
}

/**
 * Autoloader for plugin classes
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'Kealoa\\';
    $base_dir = KEALOA_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . strtolower(str_replace(['\\', '_'], ['-', '-'], $relative_class)) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load required files
require_once KEALOA_PLUGIN_DIR . 'includes/class-kealoa-activator.php';
require_once KEALOA_PLUGIN_DIR . 'includes/class-kealoa-deactivator.php';
require_once KEALOA_PLUGIN_DIR . 'includes/class-kealoa-db.php';
require_once KEALOA_PLUGIN_DIR . 'includes/class-kealoa-formatter.php';
require_once KEALOA_PLUGIN_DIR . 'includes/class-kealoa-shortcodes.php';
require_once KEALOA_PLUGIN_DIR . 'includes/class-kealoa-import.php';
require_once KEALOA_PLUGIN_DIR . 'includes/class-kealoa-export.php';
require_once KEALOA_PLUGIN_DIR . 'admin/class-kealoa-admin.php';
require_once KEALOA_PLUGIN_DIR . 'includes/class-kealoa-blocks.php';

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, function(): void {
    if (!kealoa_check_requirements()) {
        deactivate_plugins(KEALOA_PLUGIN_BASENAME);
        wp_die(
            esc_html__('KEALOA Reference cannot be activated. Please check the requirements.', 'kealoa-reference'),
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }
    
    Kealoa_Activator::activate();
});

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, function(): void {
    Kealoa_Deactivator::deactivate();
});

/**
 * Initialize the plugin
 */
function kealoa_init(): void {
    if (!kealoa_check_requirements()) {
        return;
    }
    
    // Load text domain for internationalization
    load_plugin_textdomain('kealoa-reference', false, dirname(KEALOA_PLUGIN_BASENAME) . '/languages');
    
    // Initialize admin
    if (is_admin()) {
        new Kealoa_Admin();
        new Kealoa_Export();
    }
    
    // Initialize shortcodes
    new Kealoa_Shortcodes();
    
    // Initialize blocks
    new Kealoa_Blocks();
    
    // Enqueue frontend assets
    add_action('wp_enqueue_scripts', 'kealoa_enqueue_frontend_assets');
    
    // Register rewrite rules for custom URLs
    add_action('init', 'kealoa_register_rewrite_rules');
    add_filter('query_vars', 'kealoa_query_vars');
    add_action('template_redirect', 'kealoa_template_redirect');
}
add_action('plugins_loaded', 'kealoa_init');

/**
 * Enqueue frontend assets
 */
function kealoa_enqueue_frontend_assets(): void {
    wp_enqueue_style(
        'kealoa-frontend',
        KEALOA_PLUGIN_URL . 'assets/css/kealoa-frontend.css',
        [],
        KEALOA_VERSION
    );
    
    wp_enqueue_script(
        'chartjs',
        'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js',
        [],
        '4.4.7',
        true
    );
    
    wp_enqueue_script(
        'kealoa-frontend',
        KEALOA_PLUGIN_URL . 'assets/js/kealoa-frontend.js',
        ['chartjs'],
        KEALOA_VERSION,
        true
    );
}

/**
 * Register custom rewrite rules for person and round views
 */
function kealoa_register_rewrite_rules(): void {
    add_rewrite_rule(
        '^kealoa/person/([0-9]+)/?$',
        'index.php?kealoa_person_id=$matches[1]',
        'top'
    );
    
    add_rewrite_rule(
        '^kealoa/round/([0-9]+)/?$',
        'index.php?kealoa_round_id=$matches[1]',
        'top'
    );
    
    add_rewrite_rule(
        '^kealoa/constructor/([0-9]+)/?$',
        'index.php?kealoa_constructor_id=$matches[1]',
        'top'
    );
}

/**
 * Add custom query variables
 */
function kealoa_query_vars(array $vars): array {
    $vars[] = 'kealoa_person_id';
    $vars[] = 'kealoa_round_id';
    $vars[] = 'kealoa_constructor_id';
    return $vars;
}

/**
 * Handle custom template redirects
 */
function kealoa_template_redirect(): void {
    $person_id = get_query_var('kealoa_person_id');
    $round_id = get_query_var('kealoa_round_id');
    $constructor_id = get_query_var('kealoa_constructor_id');
    
    if ($person_id) {
        kealoa_render_person_page((int) $person_id);
        exit;
    }
    
    if ($round_id) {
        kealoa_render_round_page((int) $round_id);
        exit;
    }
    
    if ($constructor_id) {
        kealoa_render_constructor_page((int) $constructor_id);
        exit;
    }
}

/**
 * Render person page
 */
function kealoa_render_person_page(int $person_id): void {
    $db = new Kealoa_DB();
    $person = $db->get_person($person_id);
    
    if (!$person) {
        wp_die(__('Person not found.', 'kealoa-reference'), '', ['response' => 404]);
    }
    
    get_header();
    echo '<div class="kealoa-page-container">';
    $shortcodes = new Kealoa_Shortcodes();
    echo $shortcodes->render_person(['id' => $person_id]);
    echo '</div>';
    get_footer();
}

/**
 * Render round page
 */
function kealoa_render_round_page(int $round_id): void {
    $db = new Kealoa_DB();
    $round = $db->get_round($round_id);
    
    if (!$round) {
        wp_die(__('Round not found.', 'kealoa-reference'), '', ['response' => 404]);
    }
    
    get_header();
    echo '<div class="kealoa-page-container">';
    $shortcodes = new Kealoa_Shortcodes();
    echo $shortcodes->render_round(['id' => $round_id]);
    echo '</div>';
    get_footer();
}

/**
 * Render constructor page
 */
function kealoa_render_constructor_page(int $constructor_id): void {
    $db = new Kealoa_DB();
    $constructor = $db->get_constructor($constructor_id);
    
    if (!$constructor) {
        wp_die(__('Constructor not found.', 'kealoa-reference'), '', ['response' => 404]);
    }
    
    get_header();
    echo '<div class="kealoa-page-container">';
    $shortcodes = new Kealoa_Shortcodes();
    echo $shortcodes->render_constructor(['id' => $constructor_id]);
    echo '</div>';
    get_footer();
}

/**
 * Flush rewrite rules on plugin activation
 */
register_activation_hook(__FILE__, function(): void {
    kealoa_register_rewrite_rules();
    flush_rewrite_rules();
});

/**
 * Flush rewrite rules on plugin deactivation
 */
register_deactivation_hook(__FILE__, function(): void {
    flush_rewrite_rules();
});
