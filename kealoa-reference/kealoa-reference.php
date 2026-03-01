<?php
/**
 * @copyright 2026 Eric Peterson (eric@puzzlehead.org)
 * @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
 *
 * Plugin Name: KEALOA Reference
 * Plugin URI: https://github.com/epeterso2/kealoa-reference
 * Description: A comprehensive plugin for managing KEALOA quiz game data from the Fill Me In podcast, including rounds, clues, puzzles, and player statistics.
 * Version: 2.0.86
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
define('KEALOA_VERSION', '2.0.86');
define('KEALOA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KEALOA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KEALOA_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('KEALOA_DB_VERSION', '2.1.0');

/**
 * Check whether KEALOA debug mode is enabled.
 *
 * Reads the `kealoa_debug_mode` option (set via KEALOA → Settings).
 * Safe to call from any context (frontend, admin, REST, CLI).
 */
function kealoa_is_debug(): bool {
    return (bool) get_option('kealoa_debug_mode', false);
}

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
require_once KEALOA_PLUGIN_DIR . 'includes/class-kealoa-rest-api.php';
require_once KEALOA_PLUGIN_DIR . 'includes/class-kealoa-sitemap.php';

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
        // Flush rewrite rules on next init to register new URL patterns
        add_action('init', 'flush_rewrite_rules', 99);
        // Clear stale KEALOA transient caches
        Kealoa_Shortcodes::flush_all_caches();
    }

    // Load text domain for internationalization
    load_plugin_textdomain('kealoa-reference', false, dirname(KEALOA_PLUGIN_BASENAME) . '/languages');

    // Initialize admin (loaded on both frontend and backend for admin bar functionality)
    new Kealoa_Admin();

    // Initialize export (admin only)
    if (is_admin()) {
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

    // Force no-sidebar layout for KEALOA virtual pages (GeneratePress compatibility)
    add_filter('generate_sidebar_layout', 'kealoa_force_no_sidebar');

    // Prevent WordPress from adding edit link for virtual KEALOA pages
    add_filter('get_edit_post_link', 'kealoa_filter_edit_post_link', 10, 2);

    // Register REST API routes
    add_action('rest_api_init', 'kealoa_register_rest_routes');

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
        'kealoa-palette',
        KEALOA_PLUGIN_URL . 'assets/css/kealoa-palette.css',
        [],
        KEALOA_VERSION
    );

    wp_enqueue_style(
        'kealoa-frontend',
        KEALOA_PLUGIN_URL . 'assets/css/kealoa-frontend.css',
        ['kealoa-palette'],
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

    wp_enqueue_style(
        'kealoa-game',
        KEALOA_PLUGIN_URL . 'assets/css/kealoa-game.css',
        ['kealoa-palette'],
        KEALOA_VERSION
    );

    wp_enqueue_script(
        'kealoa-game',
        KEALOA_PLUGIN_URL . 'assets/js/kealoa-game.js',
        [],
        KEALOA_VERSION,
        true
    );
}

/**
 * Register REST API routes for the interactive game
 */
function kealoa_register_rest_routes(): void {
    register_rest_route('kealoa/v1', '/game-round/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'kealoa_rest_game_round',
        'permission_callback' => '__return_true',
        'args'                => [
            'id' => [
                'required'          => true,
                'validate_callback' => function ($param) {
                    return is_numeric($param) && (int) $param > 0;
                },
                'sanitize_callback' => 'absint',
            ],
        ],
    ]);

    // Register comprehensive data API routes
    $api = new Kealoa_REST_API();
    $api->register_routes();
}

/**
 * REST callback: return game data for a single round
 */
function kealoa_rest_game_round(WP_REST_Request $request): WP_REST_Response {
    $round_id = (int) $request->get_param('id');
    $db = new Kealoa_DB();

    $round = $db->get_round($round_id);
    if (!$round) {
        return new WP_REST_Response(['message' => 'Round not found.'], 404);
    }

    // Solution words
    $solutions = $db->get_round_solutions($round_id);
    $solution_words = array_map(function ($s) {
        return $s->word;
    }, $solutions);

    // Clues with per-clue guesses and constructor info
    $clues_raw = $db->get_round_clues($round_id);
    $guessers = $db->get_round_guessers($round_id);
    $guesser_ids = array_map(function ($g) {
        return (int) $g->id;
    }, $guessers);
    $clues = [];

    foreach ($clues_raw as $clue) {
        // Build constructor string for the puzzle
        $constructors = '';
        if (!empty($clue->puzzle_id)) {
            $puzzle_constructors = $db->get_puzzle_constructors((int) $clue->puzzle_id);
            $names = array_map(function ($c) {
                return $c->full_name;
            }, $puzzle_constructors);
            $constructors = implode(' & ', $names);
        }

        // Get guesses for this clue, filtered to round guessers
        $guesses_raw = $db->get_clue_guesses((int) $clue->id);
        $guesses = [];
        foreach ($guesses_raw as $g) {
            if (in_array((int) $g->guesser_person_id, $guesser_ids, true)) {
                $guesses[] = [
                    'guesser_name' => $g->guesser_name,
                    'guessed_word' => $g->guessed_word,
                    'is_correct'   => (bool) $g->is_correct,
                ];
            }
        }

        $clues[] = [
            'clue_number'            => (int) $clue->clue_number,
            'puzzle_date'            => $clue->puzzle_date ?? '',
            'constructors'           => $constructors,
            'editor'                 => $clue->editor_name ?? '',
            'puzzle_clue_number'     => $clue->puzzle_clue_number ? (int) $clue->puzzle_clue_number : null,
            'puzzle_clue_direction'  => $clue->puzzle_clue_direction ?? null,
            'clue_text'              => $clue->clue_text,
            'correct_answer'         => $clue->correct_answer,
            'guesses'                => $guesses,
        ];
    }

    // Guesser results (overall scores for the round)
    $guesser_results_raw = $db->get_round_guesser_results($round_id);
    $guesser_results = array_map(function ($gr) {
        return [
            'full_name'       => $gr->full_name,
            'total_guesses'   => (int) $gr->total_guesses,
            'correct_guesses' => (int) $gr->correct_guesses,
        ];
    }, $guesser_results_raw);

    // Player names (guessers for this round)
    $guessers = $db->get_round_guessers($round_id);
    $players = array_map(function ($g) {
        return $g->full_name;
    }, $guessers);

    $data = [
        'round_id'        => $round_id,
        'round_url'       => home_url('/kealoa/round/' . $round_id),
        'description'     => $round->description ?? '',
        'description2'    => $round->description2 ?? '',
        'clue_giver'      => $round->clue_giver_name ?? '',
        'players'         => $players,
        'solution_words'  => $solution_words,
        'clues'           => $clues,
        'guesser_results' => $guesser_results,
    ];

    return new WP_REST_Response($data, 200);
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

    // Legacy constructor/editor routes — kept as rewrite rules for 301 redirects
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

    add_rewrite_rule(
        '^kealoa/puzzle/([0-9]{4}-[0-9]{2}-[0-9]{2})/?$',
        'index.php?kealoa_puzzle_date=$matches[1]',
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
    $vars[] = 'kealoa_puzzle_date';
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
    $puzzle_date = get_query_var('kealoa_puzzle_date');

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
        // Build title with role labels
        $roles = $db->get_person_roles((int) $person->id);
        $role_display_names = [
            'player'      => __('Player', 'kealoa-reference'),
            'constructor' => __('Constructor', 'kealoa-reference'),
            'editor'      => __('Editor', 'kealoa-reference'),
            'clue_giver'  => __('Host', 'kealoa-reference'),
        ];
        $role_label = !empty($roles)
            ? implode(' / ', array_map(fn($r) => $role_display_names[$r] ?? ucfirst($r), $roles))
            : __('Person', 'kealoa-reference');
        $title = sprintf(__('%s - %s', 'kealoa-reference'), $person->full_name, $role_label);
        $content = $shortcodes->render_person(['id' => $person->id]);
        $is_kealoa = true;
        // Store object info for admin bar
        $GLOBALS['kealoa_object_type'] = 'person';
        $GLOBALS['kealoa_object_id'] = $person->id;
    } elseif ($round_id) {
        $db = new Kealoa_DB();
        $round = $db->get_round((int) $round_id);
        if (!$round) {
            wp_die(__('Round not found.', 'kealoa-reference'), '', ['response' => 404]);
        }
        $shortcodes = new Kealoa_Shortcodes();
        $solutions = $db->get_round_solutions((int) $round_id);
        $solution_text = Kealoa_Formatter::format_solution_words($solutions);
        $title = sprintf(__('KEALOA #%d - %s - Round', 'kealoa-reference'), (int) $round_id, $solution_text);
        $content = $shortcodes->render_round(['id' => (int) $round_id]);
        $is_kealoa = true;
        // Store object info for admin bar
        $GLOBALS['kealoa_object_type'] = 'round';
        $GLOBALS['kealoa_object_id'] = (int) $round_id;
    } elseif ($constructor_name) {
        // 301 redirect legacy constructor URL to unified person URL
        $decoded = urldecode($constructor_name);
        $person_slug = str_replace(' ', '_', $decoded);
        wp_redirect(home_url('/kealoa/person/' . urlencode($person_slug) . '/'), 301);
        exit;
    } elseif ($editor_name) {
        // 301 redirect legacy editor URL to unified person URL
        $decoded = urldecode($editor_name);
        $person_slug = str_replace(' ', '_', $decoded);
        wp_redirect(home_url('/kealoa/person/' . urlencode($person_slug) . '/'), 301);
        exit;
    } elseif ($puzzle_date) {
        $db = new Kealoa_DB();
        $puzzle = $db->get_puzzle_by_date($puzzle_date);
        if (!$puzzle) {
            wp_die(__('Puzzle not found.', 'kealoa-reference'), '', ['response' => 404]);
        }
        $shortcodes = new Kealoa_Shortcodes();
        $formatted_date = Kealoa_Formatter::format_date($puzzle->publication_date);
        $day_name = date('l', strtotime($puzzle->publication_date));
        $title = sprintf(__('%s, %s - Puzzle', 'kealoa-reference'), $day_name, $formatted_date);
        $content = $shortcodes->render_puzzle(['date' => $puzzle->publication_date]);
        $is_kealoa = true;
        $GLOBALS['kealoa_object_type'] = 'puzzle';
        $GLOBALS['kealoa_object_id'] = $puzzle->id;
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
 * Force no-sidebar layout for KEALOA virtual pages.
 *
 * GeneratePress determines sidebar layout from post meta. Since virtual
 * pages have no meta, GP uses its default (which may include sidebars).
 * This filter ensures KEALOA pages match the static pages' "No Sidebars" setting.
 */
function kealoa_force_no_sidebar(string $layout): string {
    if (!empty($GLOBALS['kealoa_virtual_content'])) {
        return 'no-sidebar';
    }
    return $layout;
}

/**
 * Filter edit post link to prevent WordPress from adding edit link for virtual KEALOA pages.
 */
function kealoa_filter_edit_post_link($link, $post_id) {
    // If this is our fake post (ID = 0) for a KEALOA virtual page, return empty to prevent default edit link
    if ($post_id === 0 && !empty($GLOBALS['kealoa_object_type'])) {
        return '';
    }
    return $link;
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

    // If exactly one result, redirect directly to its page
    if (count($kealoa_results) === 1) {
        wp_redirect($kealoa_results[0]->url);
        exit;
    }

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
        'person' => __('Person', 'kealoa-reference'),
        'round' => __('Round', 'kealoa-reference'),
        'puzzle' => __('Puzzle', 'kealoa-reference'),
    ];

    $html = '<div class="kealoa-search-results">';
    $html .= '<h3 class="kealoa-search-heading">' . esc_html__('KEALOA Results', 'kealoa-reference') . '</h3>';
    $html .= '<table class="kealoa-search-table">';
    $html .= '<thead><tr>';
    $html .= '<th>' . esc_html__('Type', 'kealoa-reference') . '</th>';
    $html .= '<th>' . esc_html__('Result', 'kealoa-reference') . '</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    foreach ($results as $result) {
        $label = $type_labels[$result->type] ?? $result->type;
        $html .= '<tr class="kealoa-search-item">';
        $html .= '<td class="kealoa-search-type">' . esc_html($label) . '</td>';
        $html .= '<td><a href="' . esc_url($result->url) . '">' . esc_html($result->name) . '</a></td>';
        $html .= '</tr>';
    }
    $html .= '</tbody>';
    $html .= '</table>';
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
