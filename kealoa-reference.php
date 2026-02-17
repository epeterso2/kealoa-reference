<?php
/**
 * Plugin Name: KEALOA Reference
 * Plugin URI: https://epeterso2.com/kealoa-reference
 * Description: A comprehensive plugin for managing KEALOA quiz game data from the Fill Me In podcast, including rounds, clues, puzzles, and player statistics.
 * Version: 1.1.28
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
define('KEALOA_VERSION', '1.1.28');
define('KEALOA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KEALOA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KEALOA_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('KEALOA_DB_VERSION', '1.3.0');

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
    
    // Check for database upgrades
    $installed_db_version = get_option('kealoa_db_version', '0');
    if (version_compare($installed_db_version, KEALOA_DB_VERSION, '<')) {
        Kealoa_Activator::activate();
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
    add_filter('template_include', 'kealoa_template_include');
    
    // Add KEALOA results to WordPress search
    add_filter('the_posts', 'kealoa_inject_search_placeholder', 10, 2);
    add_action('loop_start', 'kealoa_search_loop_start');
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
        '^kealoa/person/([^/]+)/?$',
        'index.php?kealoa_person_name=$matches[1]',
        'top'
    );
    
    add_rewrite_rule(
        '^kealoa/round/([0-9]+)/?$',
        'index.php?kealoa_round_id=$matches[1]',
        'top'
    );
    
    add_rewrite_rule(
        '^kealoa/constructor/([^/]+)/?$',
        'index.php?kealoa_constructor_name=$matches[1]',
        'top'
    );
    
    add_rewrite_rule(
        '^kealoa/editor/([^/]+)/?$',
        'index.php?kealoa_editor_name=$matches[1]',
        'top'
    );
}

/**
 * Add custom query variables
 */
function kealoa_query_vars(array $vars): array {
    $vars[] = 'kealoa_person_name';
    $vars[] = 'kealoa_round_id';
    $vars[] = 'kealoa_constructor_name';
    $vars[] = 'kealoa_editor_name';
    return $vars;
}

/**
 * Handle custom template redirects for KEALOA virtual pages.
 *
 * Sets up a fake WP_Post so WordPress (and caching plugins like
 * WP Super Cache) treats the response as a normal 200 page.
 */
function kealoa_template_redirect(): void {
    $person_name = get_query_var('kealoa_person_name');
    $round_id = get_query_var('kealoa_round_id');
    $constructor_name = get_query_var('kealoa_constructor_name');
    $editor_name = get_query_var('kealoa_editor_name');

    $title = '';
    $content = '';
    $is_kealoa = false;

    if ($person_name) {
        $db = new Kealoa_DB();
        $person = $db->get_person_by_name(urldecode($person_name));
        if (!$person) {
            wp_die(__('Person not found.', 'kealoa-reference'), '', ['response' => 404]);
        }
        $shortcodes = new Kealoa_Shortcodes();
        $title = $person->full_name;
        $content = $shortcodes->render_person(['id' => $person->id]);
        $is_kealoa = true;
    } elseif ($round_id) {
        $db = new Kealoa_DB();
        $round = $db->get_round((int) $round_id);
        if (!$round) {
            wp_die(__('Round not found.', 'kealoa-reference'), '', ['response' => 404]);
        }
        $shortcodes = new Kealoa_Shortcodes();
        $title = sprintf(__('KEALOA Round %d', 'kealoa-reference'), (int) $round_id);
        $content = $shortcodes->render_round(['id' => (int) $round_id]);
        $is_kealoa = true;
    } elseif ($constructor_name) {
        $db = new Kealoa_DB();
        $constructor = $db->get_constructor_by_name(urldecode($constructor_name));
        if (!$constructor) {
            wp_die(__('Constructor not found.', 'kealoa-reference'), '', ['response' => 404]);
        }
        $shortcodes = new Kealoa_Shortcodes();
        $title = $constructor->full_name;
        $content = $shortcodes->render_constructor(['id' => (int) $constructor->id]);
        $is_kealoa = true;
    } elseif ($editor_name) {
        $decoded = urldecode($editor_name);
        $shortcodes = new Kealoa_Shortcodes();
        $title = $decoded;
        $content = $shortcodes->render_editor(['name' => $decoded]);
        $is_kealoa = true;
    }

    if (!$is_kealoa) {
        return;
    }

    // Build a virtual WP_Post so WordPress treats this as a real page
    global $wp_query, $post;

    $post = new WP_Post((object) [
        'ID'             => 0,
        'post_title'     => $title,
        'post_content'   => $content,
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'comment_status' => 'closed',
        'ping_status'    => 'closed',
        'filter'         => 'raw',
    ]);

    $wp_query->post  = $post;
    $wp_query->posts = [$post];
    $wp_query->found_posts   = 1;
    $wp_query->max_num_pages = 1;
    $wp_query->is_page     = true;
    $wp_query->is_singular = true;
    $wp_query->is_single   = false;
    $wp_query->is_404      = false;
    $wp_query->is_archive  = false;
    $wp_query->is_home     = false;

    status_header(200);

    // Store content for the template_include filter
    $GLOBALS['kealoa_virtual_content'] = $content;
}

/**
 * Use the theme's page template for KEALOA virtual pages.
 *
 * By letting WordPress load the page template normally (instead of
 * calling get_header/get_footer + exit), caching plugins like
 * WP Super Cache can capture and cache the full response.
 */
function kealoa_template_include(string $template): string {
    if (empty($GLOBALS['kealoa_virtual_content'])) {
        return $template;
    }

    // Hook into the_content to output our rendered HTML
    add_filter('the_content', function () {
        return '<div class="kealoa-page-container">' . $GLOBALS['kealoa_virtual_content'] . '</div>';
    });

    // Use the theme's page template
    $page_template = locate_template(['page.php', 'singular.php', 'index.php']);
    return $page_template ?: $template;
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

/**
 * Inject a placeholder post into empty search results so the loop runs
 * and our content filter can output KEALOA results.
 */
function kealoa_inject_search_placeholder(array $posts, WP_Query $query): array {
    if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
        return $posts;
    }
    
    $search_term = $query->get('s');
    if (empty($search_term)) {
        return $posts;
    }
    
    $db = new Kealoa_DB();
    $kealoa_results = $db->search_all($search_term);
    
    if (empty($kealoa_results)) {
        return $posts;
    }
    
    // Store results for the content filter
    $GLOBALS['kealoa_search_results'] = $kealoa_results;
    
    // If there are no WP posts, inject a placeholder so the loop runs
    if (empty($posts)) {
        $placeholder = new WP_Post((object) [
            'ID' => 0,
            'post_title' => '',
            'post_content' => '',
            'post_type' => 'kealoa_placeholder',
            'filter' => 'raw',
        ]);
        $posts = [$placeholder];
        $query->found_posts = 1;
        $query->max_num_pages = 1;
    }
    
    return $posts;
}

/**
 * Build KEALOA search results HTML
 */
function kealoa_build_search_results_html(array $results): string {
    $type_labels = [
        'player' => __('Player', 'kealoa-reference'),
        'constructor' => __('Constructor', 'kealoa-reference'),
        'editor' => __('Editor', 'kealoa-reference'),
        'round' => __('Round', 'kealoa-reference'),
    ];
    
    $html = '<div class="kealoa-search-results">';
    $html .= '<h3 class="kealoa-search-heading">' . esc_html__('KEALOA Results', 'kealoa-reference') . '</h3>';
    $html .= '<ul class="kealoa-search-list">';
    foreach ($results as $result) {
        $label = $type_labels[$result->type] ?? $result->type;
        $html .= '<li class="kealoa-search-item">';
        $html .= '<span class="kealoa-search-type">' . esc_html($label) . '</span> ';
        $html .= '<a href="' . esc_url($result->url) . '">' . esc_html($result->name) . '</a>';
        $html .= '</li>';
    }
    $html .= '</ul>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Output KEALOA search results at the start of the main search loop
 */
function kealoa_search_loop_start(WP_Query $query): void {
    if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
        return;
    }
    
    if (empty($GLOBALS['kealoa_search_results'])) {
        return;
    }
    
    $results = $GLOBALS['kealoa_search_results'];
    // Clear so we only output once
    unset($GLOBALS['kealoa_search_results']);
    
    echo kealoa_build_search_results_html($results);
}
