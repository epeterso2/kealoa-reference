<?php
/**
 * @copyright 2026 Eric Peterson (eric@puzzlehead.org)
 * @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
 *
 * Admin Interface
 *
 * Handles all WordPress admin functionality for KEALOA Reference.
 *
 * @package KEALOA_Reference
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Kealoa_Admin
 *
 * Manages admin menus, pages, and data entry forms.
 */
class Kealoa_Admin {

    private Kealoa_DB $db;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Kealoa_DB();

        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_bar_menu', [$this, 'add_admin_bar_menu'], 100);
        add_action('admin_bar_menu', [$this, 'modify_edit_link'], 999);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'handle_form_submissions']);
        add_action('admin_notices', [$this, 'render_admin_notices']);

        // AJAX handlers
        add_action('wp_ajax_kealoa_get_persons', [$this, 'ajax_get_persons']);
        add_action('wp_ajax_kealoa_save_media', [$this, 'ajax_save_media']);
    }

    /**
     * Display any queued flash message stored in the kealoa_admin_message transient.
     */
    public function render_admin_notices(): void {
        $msg = get_transient('kealoa_admin_message');
        if (!$msg) {
            return;
        }
        delete_transient('kealoa_admin_message');
        $css_type = ($msg['type'] ?? 'success') === 'error' ? 'notice-error' : 'notice-success';
        echo '<div class="notice ' . esc_attr($css_type) . ' is-dismissible"><p>'
            . esc_html($msg['message'])
            . '</p></div>';
    }

    /**
     * Add admin menus
     */
    public function add_admin_menus(): void {
        // Main menu
        add_menu_page(
            __('KEALOA Reference', 'kealoa-reference'),
            __('KEALOA', 'kealoa-reference'),
            'manage_options',
            'kealoa-reference',
            [$this, 'render_dashboard_page'],
            'dashicons-games',
            30
        );

        // Submenus
        add_submenu_page(
            'kealoa-reference',
            __('Dashboard', 'kealoa-reference'),
            __('Dashboard', 'kealoa-reference'),
            'manage_options',
            'kealoa-reference',
            [$this, 'render_dashboard_page']
        );

        add_submenu_page(
            'kealoa-reference',
            __('Rounds', 'kealoa-reference'),
            __('Rounds', 'kealoa-reference'),
            'manage_options',
            'kealoa-rounds',
            [$this, 'render_rounds_page']
        );

        add_submenu_page(
            'kealoa-reference',
            __('Persons', 'kealoa-reference'),
            __('Persons', 'kealoa-reference'),
            'manage_options',
            'kealoa-persons',
            [$this, 'render_persons_page']
        );

        add_submenu_page(
            'kealoa-reference',
            __('Puzzles', 'kealoa-reference'),
            __('Puzzles', 'kealoa-reference'),
            'manage_options',
            'kealoa-puzzles',
            [$this, 'render_puzzles_page']
        );

        add_submenu_page(
            'kealoa-reference',
            __('Import Data', 'kealoa-reference'),
            __('Import', 'kealoa-reference'),
            'manage_options',
            'kealoa-import',
            [$this, 'render_import_page']
        );

        add_submenu_page(
            'kealoa-reference',
            __('Export Data', 'kealoa-reference'),
            __('Export', 'kealoa-reference'),
            'manage_options',
            'kealoa-export',
            [$this, 'render_export_page']
        );

        add_submenu_page(
            'kealoa-reference',
            __('Data Check', 'kealoa-reference'),
            __('Data Check', 'kealoa-reference'),
            'manage_options',
            'kealoa-data-check',
            [$this, 'render_data_check_page']
        );

        add_submenu_page(
            'kealoa-reference',
            __('Settings', 'kealoa-reference'),
            __('Settings', 'kealoa-reference'),
            'manage_options',
            'kealoa-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Add KEALOA menu to admin bar (toolbar) - frontend only
     */
    public function add_admin_bar_menu($wp_admin_bar): void {
        // Only show on frontend, not in admin backend
        if (is_admin() || !current_user_can('manage_options')) {
            return;
        }

        // Add parent menu item
        $wp_admin_bar->add_node([
            'id'    => 'kealoa-reference',
            'title' => __('KEALOA', 'kealoa-reference'),
            'href'  => admin_url('admin.php?page=kealoa-reference'),
            'meta'  => [
                'title' => __('KEALOA Reference', 'kealoa-reference'),
            ],
        ]);

        // Add submenu items
        $wp_admin_bar->add_node([
            'id'     => 'kealoa-dashboard',
            'parent' => 'kealoa-reference',
            'title'  => __('Dashboard', 'kealoa-reference'),
            'href'   => admin_url('admin.php?page=kealoa-reference'),
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'kealoa-rounds',
            'parent' => 'kealoa-reference',
            'title'  => __('Rounds', 'kealoa-reference'),
            'href'   => admin_url('admin.php?page=kealoa-rounds'),
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'kealoa-persons',
            'parent' => 'kealoa-reference',
            'title'  => __('Persons', 'kealoa-reference'),
            'href'   => admin_url('admin.php?page=kealoa-persons'),
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'kealoa-puzzles',
            'parent' => 'kealoa-reference',
            'title'  => __('Puzzles', 'kealoa-reference'),
            'href'   => admin_url('admin.php?page=kealoa-puzzles'),
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'kealoa-import',
            'parent' => 'kealoa-reference',
            'title'  => __('Import', 'kealoa-reference'),
            'href'   => admin_url('admin.php?page=kealoa-import'),
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'kealoa-export',
            'parent' => 'kealoa-reference',
            'title'  => __('Export', 'kealoa-reference'),
            'href'   => admin_url('admin.php?page=kealoa-export'),
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'kealoa-data-check',
            'parent' => 'kealoa-reference',
            'title'  => __('Data Check', 'kealoa-reference'),
            'href'   => admin_url('admin.php?page=kealoa-data-check'),
        ]);

        $wp_admin_bar->add_node([
            'id'     => 'kealoa-settings',
            'parent' => 'kealoa-reference',
            'title'  => __('Settings', 'kealoa-reference'),
            'href'   => admin_url('admin.php?page=kealoa-settings'),
        ]);
    }

    /**
     * Modify the Edit link in admin bar when viewing KEALOA objects
     */
    public function modify_edit_link($wp_admin_bar): void {
        // Only run on frontend
        if (is_admin() || !current_user_can('manage_options')) {
            return;
        }

        // Check if we're viewing a KEALOA object (stored by kealoa_template_redirect)
        $object_type = $GLOBALS['kealoa_object_type'] ?? null;
        $object_id = $GLOBALS['kealoa_object_id'] ?? null;

        if (!$object_type || !$object_id) {
            return;
        }

        // Remove any existing edit links that WordPress might have added
        $wp_admin_bar->remove_node('edit');
        $wp_admin_bar->remove_node('edit-post');
        $wp_admin_bar->remove_node('edit-page');

        // Add appropriate edit link based on object type
        switch ($object_type) {
            case 'person':
                $wp_admin_bar->add_node([
                    'id'    => 'edit',
                    'title' => __('Edit Person', 'kealoa-reference'),
                    'href'  => admin_url('admin.php?page=kealoa-persons&action=edit&id=' . $object_id),
                    'meta'  => ['title' => __('Edit this person', 'kealoa-reference')],
                ]);
                break;

            case 'round':
                $wp_admin_bar->add_node([
                    'id'    => 'edit',
                    'title' => __('Edit Round', 'kealoa-reference'),
                    'href'  => admin_url('admin.php?page=kealoa-rounds&action=edit&id=' . $object_id),
                    'meta'  => ['title' => __('Edit this round', 'kealoa-reference')],
                ]);
                break;

        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets(string $hook): void {
        if (strpos($hook, 'kealoa') === false) {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'kealoa-palette',
            KEALOA_PLUGIN_URL . 'assets/css/kealoa-palette.css',
            [],
            KEALOA_VERSION
        );

        wp_enqueue_style(
            'kealoa-admin',
            KEALOA_PLUGIN_URL . 'assets/css/kealoa-admin.css',
            ['kealoa-palette'],
            KEALOA_VERSION
        );

        wp_enqueue_script(
            'kealoa-admin',
            KEALOA_PLUGIN_URL . 'assets/js/kealoa-admin.js',
            ['jquery'],
            KEALOA_VERSION,
            true
        );

        wp_localize_script('kealoa-admin', 'kealoaAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kealoa_admin_nonce'),
        ]);
    }

    /**
     * Handle form submissions
     */
    public function handle_form_submissions(): void {
        if (!isset($_POST['kealoa_action']) || !current_user_can('manage_options')) {
            return;
        }

        if (!wp_verify_nonce($_POST['kealoa_nonce'] ?? '', 'kealoa_admin_action')) {
            wp_die(__('Security check failed.', 'kealoa-reference'));
        }

        // Remove magic quotes added by WordPress
        $_POST = wp_unslash($_POST);

        $action = sanitize_text_field($_POST['kealoa_action']);

        match($action) {
            'create_person' => $this->handle_create_person(),
            'update_person' => $this->handle_update_person(),
            'delete_person' => $this->handle_delete_person(),
            'create_puzzle' => $this->handle_create_puzzle(),
            'update_puzzle' => $this->handle_update_puzzle(),
            'delete_puzzle' => $this->handle_delete_puzzle(),
            'create_round' => $this->handle_create_round(),
            'update_round' => $this->handle_update_round(),
            'delete_round' => $this->handle_delete_round(),
            'create_clue' => $this->handle_create_clue(),
            'update_clue' => $this->handle_update_clue(),
            'delete_clue' => $this->handle_delete_clue(),
            'save_guesses' => $this->handle_save_guesses(),
            'repair_delete_puzzles' => $this->handle_repair_delete_puzzles(),
            'repair_delete_rounds' => $this->handle_repair_delete_rounds(),
            'repair_delete_orphans' => $this->handle_repair_delete_orphans(),
            'save_settings' => $this->handle_save_settings(),
            default => null,
        };

        // Flush transient caches after any data mutation
        Kealoa_Shortcodes::flush_all_caches();
    }

    // =========================================================================
    // PAGE RENDERERS
    // =========================================================================

    /**
     * Render dashboard page
     */
    public function render_dashboard_page(): void {
        $rounds_count = $this->db->count_rounds();
        $persons_count = $this->db->count_persons();
        $puzzles_count = $this->db->count_puzzles();
        ?>
        <div class="wrap kealoa-admin-wrap">
            <h1><?php esc_html_e('KEALOA Reference Dashboard', 'kealoa-reference'); ?></h1>

            <div class="kealoa-dashboard-cards">
                <div class="kealoa-card">
                    <h2><?php echo esc_html($rounds_count); ?></h2>
                    <p><?php esc_html_e('Rounds', 'kealoa-reference'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-rounds')); ?>" class="button">
                        <?php esc_html_e('Manage Rounds', 'kealoa-reference'); ?>
                    </a>
                </div>

                <div class="kealoa-card">
                    <h2><?php echo esc_html($persons_count); ?></h2>
                    <p><?php esc_html_e('Persons', 'kealoa-reference'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-persons')); ?>" class="button">
                        <?php esc_html_e('Manage Persons', 'kealoa-reference'); ?>
                    </a>
                </div>

                <div class="kealoa-card">
                    <h2><?php echo esc_html($puzzles_count); ?></h2>
                    <p><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-puzzles')); ?>" class="button">
                        <?php esc_html_e('Manage Puzzles', 'kealoa-reference'); ?>
                    </a>
                </div>
            </div>

            <div class="kealoa-shortcodes-info">
                <h2><?php esc_html_e('Available Shortcodes', 'kealoa-reference'); ?></h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Shortcode', 'kealoa-reference'); ?></th>
                            <th><?php esc_html_e('Description', 'kealoa-reference'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>[kealoa_rounds_table]</code></td>
                            <td><?php esc_html_e('Displays a table of all KEALOA rounds', 'kealoa-reference'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[kealoa_round id="X"]</code></td>
                            <td><?php esc_html_e('Displays a single KEALOA round with full details', 'kealoa-reference'); ?></td>
                        </tr>
                        <tr>
                            <td><code>[kealoa_person id="X"]</code></td>
                            <td><?php esc_html_e('Displays a person\'s profile and statistics', 'kealoa-reference'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="kealoa-blocks-info">
                <h2><?php esc_html_e('Available Blocks', 'kealoa-reference'); ?></h2>
                <p><?php esc_html_e('The following Gutenberg blocks are available in the block editor:', 'kealoa-reference'); ?></p>
                <ul>
                    <li><strong><?php esc_html_e('KEALOA Rounds Table', 'kealoa-reference'); ?></strong> - <?php esc_html_e('Displays a table of all rounds', 'kealoa-reference'); ?></li>
                    <li><strong><?php esc_html_e('KEALOA Round View', 'kealoa-reference'); ?></strong> - <?php esc_html_e('Displays a single round', 'kealoa-reference'); ?></li>
                    <li><strong><?php esc_html_e('KEALOA Person View', 'kealoa-reference'); ?></strong> - <?php esc_html_e('Displays a person\'s profile', 'kealoa-reference'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Render export page
     */
    public function render_export_page(): void {
        $types = [
            'persons' => __('Persons', 'kealoa-reference'),
            'puzzles' => __('Puzzles', 'kealoa-reference'),
            'rounds' => __('Rounds', 'kealoa-reference'),
            'clues' => __('Clues', 'kealoa-reference'),
            'guesses' => __('Guesses', 'kealoa-reference'),
        ];

        $descriptions = [
            'persons' => __('All persons: podcast hosts, players, constructors, and editors with profile info', 'kealoa-reference'),
            'puzzles' => __('NYT crossword puzzles with constructors', 'kealoa-reference'),
            'rounds' => __('KEALOA game rounds with episode info, solution words, and players', 'kealoa-reference'),
            'clues' => __('All clues with puzzle info and correct answers', 'kealoa-reference'),
            'guesses' => __('All guesses for every clue', 'kealoa-reference'),
        ];
        ?>
        <div class="wrap kealoa-admin-wrap">
            <h1><?php esc_html_e('Export Data', 'kealoa-reference'); ?></h1>
            <p><?php esc_html_e('Download your KEALOA data as CSV files. Exported files are compatible with the import format.', 'kealoa-reference'); ?></p>

            <table class="widefat" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Data Type', 'kealoa-reference'); ?></th>
                        <th><?php esc_html_e('Description', 'kealoa-reference'); ?></th>
                        <th><?php esc_html_e('Export', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($types as $type => $label): ?>
                        <tr>
                            <td><strong><?php echo esc_html($label); ?></strong></td>
                            <td><?php echo esc_html($descriptions[$type]); ?></td>
                            <td>
                                <a href="<?php echo esc_url(Kealoa_Export::get_export_url($type)); ?>" class="button button-small">
                                    <?php esc_html_e('Download CSV', 'kealoa-reference'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top: 30px;">
                <h2><?php esc_html_e('Export All Data', 'kealoa-reference'); ?></h2>
                <p><?php esc_html_e('Download all data types as a single ZIP archive.', 'kealoa-reference'); ?></p>
                <a href="<?php echo esc_url(Kealoa_Export::get_export_url('all')); ?>" class="button button-primary">
                    <?php esc_html_e('Download All (ZIP)', 'kealoa-reference'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render import page
     */
    public function render_import_page(): void {
        $templates = Kealoa_Import::get_templates();
        $import_result = null;

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kealoa_import_nonce'])) {
            if (wp_verify_nonce($_POST['kealoa_import_nonce'], 'kealoa_import_csv')) {
                $import_result = $this->handle_csv_import();
                Kealoa_Shortcodes::flush_all_caches();
            }
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kealoa_import_zip_nonce'])) {
            if (wp_verify_nonce($_POST['kealoa_import_zip_nonce'], 'kealoa_import_zip')) {
                $import_result = $this->handle_zip_import();
                Kealoa_Shortcodes::flush_all_caches();
            }
        }
        ?>
        <div class="wrap kealoa-admin-wrap">
            <h1><?php esc_html_e('Import Data', 'kealoa-reference'); ?></h1>

            <?php if ($import_result): ?>
                <div class="notice notice-<?php echo $import_result['success'] ? 'success' : 'error'; ?> is-dismissible">
                    <p>
                        <?php
                        printf(
                            esc_html__('Import complete: %d imported, %d skipped.', 'kealoa-reference'),
                            $import_result['imported'],
                            $import_result['skipped']
                        );
                        ?>
                    </p>
                    <?php if (!empty($import_result['details'])): ?>
                        <table class="widefat" style="margin-top: 10px; max-width: 500px;">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Data Type', 'kealoa-reference'); ?></th>
                                    <th><?php esc_html_e('Imported', 'kealoa-reference'); ?></th>
                                    <th><?php esc_html_e('Skipped', 'kealoa-reference'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($import_result['details'] as $detail): ?>
                                    <tr>
                                        <td><?php echo esc_html($detail['label']); ?></td>
                                        <td><?php echo esc_html($detail['imported']); ?></td>
                                        <td>
                                            <?php
                                            echo esc_html($detail['skipped']);
                                            if (!empty($detail['message'])) {
                                                echo ' <em>(' . esc_html($detail['message']) . ')</em>';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    <?php if (!empty($import_result['errors'])): ?>
                        <details style="margin-top: 10px;">
                            <summary><?php esc_html_e('View errors', 'kealoa-reference'); ?></summary>
                            <ul>
                                <?php foreach ($import_result['errors'] as $error): ?>
                                    <li><?php echo esc_html($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="kealoa-import-section">
                <h2><?php esc_html_e('Puzzle Editors', 'kealoa-reference'); ?></h2>
                <p><?php esc_html_e('Auto-populate puzzle editor names based on puzzle dates using historical NYT editor information.', 'kealoa-reference'); ?></p>

                <form method="post" style="margin-top: 10px;">
                    <?php wp_nonce_field('kealoa_admin_action', 'kealoa_nonce'); ?>
                    <input type="hidden" name="kealoa_action" value="auto_populate_editors" />
                    <input type="submit" class="button button-secondary" value="<?php esc_attr_e('Auto-Populate Editors', 'kealoa-reference'); ?>"
                           onclick="return confirm('<?php esc_attr_e('This will overwrite all existing editor names based on puzzle dates. Continue?', 'kealoa-reference'); ?>');" />
                    <p class="description" style="margin-top: 10px;">
                        <?php esc_html_e('This will update editor names for all puzzles based on their puzzle dates. Existing editor names will be overwritten.', 'kealoa-reference'); ?>
                    </p>
                </form>
            </div>

            <div class="kealoa-import-section" style="margin-top: 30px;">
                <h2><?php esc_html_e('Import CSV File', 'kealoa-reference'); ?></h2>
                <p><?php esc_html_e('Select a data type and upload your CSV file. Import order matters: Constructors/Persons first, then Puzzles, then Rounds, then Clues, then Guesses.', 'kealoa-reference'); ?></p>

                <form method="post" enctype="multipart/form-data" class="kealoa-form">
                    <?php wp_nonce_field('kealoa_import_csv', 'kealoa_import_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th><label for="import_type"><?php esc_html_e('Data Type', 'kealoa-reference'); ?></label></th>
                            <td>
                                <select name="import_type" id="import_type" required>
                                    <option value=""><?php esc_html_e('— Select —', 'kealoa-reference'); ?></option>
                                    <option value="constructors"><?php esc_html_e('Constructors', 'kealoa-reference'); ?></option>
                                    <option value="persons"><?php esc_html_e('Persons', 'kealoa-reference'); ?></option>
                                    <option value="puzzles"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></option>
                                    <option value="rounds"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></option>
                                    <option value="clues"><?php esc_html_e('Clues', 'kealoa-reference'); ?></option>
                                    <option value="guesses"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></option>
                                    <option value="round_data"><?php esc_html_e('Round Data', 'kealoa-reference'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="csv_file"><?php esc_html_e('CSV File', 'kealoa-reference'); ?></label></th>
                            <td>
                                <input type="file" name="csv_file" id="csv_file" accept=".csv" required />
                                <p class="description">
                                    <?php esc_html_e('Select a CSV file to import. The first row must contain column headers.', 'kealoa-reference'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Options', 'kealoa-reference'); ?></th>
                            <td>
                                <label for="csv_overwrite">
                                    <input type="checkbox" name="overwrite" id="csv_overwrite" value="1" />
                                    <?php esc_html_e('Overwrite existing data', 'kealoa-reference'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('When checked, imported data will overwrite existing records. When unchecked, existing records will be skipped.', 'kealoa-reference'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php esc_attr_e('Import', 'kealoa-reference'); ?>" />
                    </p>
                </form>
            </div>

            <div class="kealoa-import-section" style="margin-top: 30px;">
                <h2><?php esc_html_e('Import All Data from ZIP', 'kealoa-reference'); ?></h2>
                <p><?php esc_html_e('Upload a ZIP file created by "Export All Data" to import all data types at once. The ZIP file should contain CSV files (constructors.csv, persons.csv, puzzles.csv, rounds.csv, clues.csv, guesses.csv). Files are imported in the correct dependency order automatically.', 'kealoa-reference'); ?></p>

                <form method="post" enctype="multipart/form-data" class="kealoa-form">
                    <?php wp_nonce_field('kealoa_import_zip', 'kealoa_import_zip_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th><label for="zip_file"><?php esc_html_e('ZIP File', 'kealoa-reference'); ?></label></th>
                            <td>
                                <input type="file" name="zip_file" id="zip_file" accept=".zip" required />
                                <p class="description">
                                    <?php esc_html_e('Select a ZIP file exported from KEALOA Reference.', 'kealoa-reference'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Options', 'kealoa-reference'); ?></th>
                            <td>
                                <label for="zip_overwrite">
                                    <input type="checkbox" name="overwrite" id="zip_overwrite" value="1" />
                                    <?php esc_html_e('Overwrite existing data', 'kealoa-reference'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('When checked, imported data will overwrite existing records. When unchecked, existing records will be skipped.', 'kealoa-reference'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" class="button button-primary" value="<?php esc_attr_e('Import ZIP', 'kealoa-reference'); ?>" />
                    </p>
                </form>
            </div>

            <div class="kealoa-import-section" style="margin-top: 30px;">
                <h2><?php esc_html_e('Download Templates', 'kealoa-reference'); ?></h2>
                <p><?php esc_html_e('Download CSV templates to see the expected format for each data type. Fill in your data and upload to import.', 'kealoa-reference'); ?></p>

                <table class="widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Data Type', 'kealoa-reference'); ?></th>
                            <th><?php esc_html_e('Description', 'kealoa-reference'); ?></th>
                            <th><?php esc_html_e('Template', 'kealoa-reference'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e('Constructors', 'kealoa-reference'); ?></strong></td>
                            <td><?php esc_html_e('Crossword puzzle constructors with XWordInfo profile info', 'kealoa-reference'); ?></td>
                            <td>
                                <?php if (isset($templates['constructors'])): ?>
                                    <a href="<?php echo esc_url($templates['constructors']['url']); ?>" class="button button-small" download>
                                        <?php esc_html_e('Download', 'kealoa-reference'); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Persons', 'kealoa-reference'); ?></strong></td>
                            <td><?php esc_html_e('Podcast hosts, guests, and clue givers/players', 'kealoa-reference'); ?></td>
                            <td>
                                <?php if (isset($templates['persons'])): ?>
                                    <a href="<?php echo esc_url($templates['persons']['url']); ?>" class="button button-small" download>
                                        <?php esc_html_e('Download', 'kealoa-reference'); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></strong></td>
                            <td><?php esc_html_e('NYT crossword puzzles with constructors (creates constructors if needed)', 'kealoa-reference'); ?></td>
                            <td>
                                <?php if (isset($templates['puzzles'])): ?>
                                    <a href="<?php echo esc_url($templates['puzzles']['url']); ?>" class="button button-small" download>
                                        <?php esc_html_e('Download', 'kealoa-reference'); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Rounds', 'kealoa-reference'); ?></strong></td>
                            <td><?php esc_html_e('KEALOA game rounds with episode info, solution words, players (creates persons if needed)', 'kealoa-reference'); ?></td>
                            <td>
                                <?php if (isset($templates['rounds'])): ?>
                                    <a href="<?php echo esc_url($templates['rounds']['url']); ?>" class="button button-small" download>
                                        <?php esc_html_e('Download', 'kealoa-reference'); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Clues', 'kealoa-reference'); ?></strong></td>
                            <td><?php esc_html_e('Clues for each round (requires rounds and puzzles to exist)', 'kealoa-reference'); ?></td>
                            <td>
                                <?php if (isset($templates['clues'])): ?>
                                    <a href="<?php echo esc_url($templates['clues']['url']); ?>" class="button button-small" download>
                                        <?php esc_html_e('Download', 'kealoa-reference'); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Guesses', 'kealoa-reference'); ?></strong></td>
                            <td><?php esc_html_e('Guesses for each clue (requires rounds, clues, and persons to exist)', 'kealoa-reference'); ?></td>
                            <td>
                                <?php if (isset($templates['guesses'])): ?>
                                    <a href="<?php echo esc_url($templates['guesses']['url']); ?>" class="button button-small" download>
                                        <?php esc_html_e('Download', 'kealoa-reference'); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Round Data', 'kealoa-reference'); ?></strong></td>
                            <td><?php esc_html_e('Human-friendly format for importing complete round data (one row per guess). Puzzle date, constructor name, clue number, and clue direction may be left blank.', 'kealoa-reference'); ?></td>
                            <td>
                                <?php if (isset($templates['round_data'])): ?>
                                    <a href="<?php echo esc_url($templates['round_data']['url']); ?>" class="button button-small" download>
                                        <?php esc_html_e('Download', 'kealoa-reference'); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="kealoa-import-section" style="margin-top: 30px;">
                <h2><?php esc_html_e('Import Notes', 'kealoa-reference'); ?></h2>
                <ul>
                    <li><?php esc_html_e('When "Overwrite existing data" is unchecked, duplicate records are skipped based on unique identifiers (names, dates). When checked, existing records are updated with the imported data.', 'kealoa-reference'); ?></li>
                    <li><?php esc_html_e('For Puzzles: Constructors are looked up by name. If not found, they are created automatically with XWordInfo fields populated.', 'kealoa-reference'); ?></li>
                    <li><?php esc_html_e('For Rounds: Clue givers and players are looked up by name. If not found, they are created automatically.', 'kealoa-reference'); ?></li>
                    <li><?php esc_html_e('For Clues: Rounds must exist (matched by round_date). Puzzles are created if not found.', 'kealoa-reference'); ?></li>
                    <li><?php esc_html_e('For Guesses: is_correct is automatically calculated by comparing guessed_word to the clue\'s correct_answer.', 'kealoa-reference'); ?></li>
                    <li><?php esc_html_e('All text is trimmed. Solution words and answers are automatically uppercased.', 'kealoa-reference'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Handle ZIP import form submission
     */
    private function handle_zip_import(): array {
        if (!isset($_FILES['zip_file']) || $_FILES['zip_file']['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['Failed to upload ZIP file.'],
            ];
        }

        $file_name = $_FILES['zip_file']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_ext !== 'zip') {
            return [
                'success' => false,
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['Please upload a .zip file.'],
            ];
        }

        $file_path = $_FILES['zip_file']['tmp_name'];
        $overwrite = !empty($_POST['overwrite']);
        $importer = new Kealoa_Import($this->db);

        return $importer->import_zip($file_path, $overwrite);
    }

    /**
     * Handle CSV import form submission
     */
    private function handle_csv_import(): array {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['Failed to upload file.'],
            ];
        }

        $import_type = sanitize_text_field($_POST['import_type'] ?? '');
        $allowed_types = ['constructors', 'persons', 'puzzles', 'rounds', 'clues', 'guesses', 'round_data'];

        if (!in_array($import_type, $allowed_types)) {
            return [
                'success' => false,
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['Invalid import type selected.'],
            ];
        }

        $file_path = $_FILES['csv_file']['tmp_name'];
        $overwrite = !empty($_POST['overwrite']);
        $importer = new Kealoa_Import($this->db);

        $method = 'import_' . $import_type;
        $result = $importer->$method($file_path, $overwrite);

        $result['success'] = $result['imported'] > 0 || empty($result['errors']);

        return $result;
    }

    /**
     * Render persons page
     */
    public function render_persons_page(): void {
        $action = $_GET['action'] ?? 'list';
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        echo '<div class="wrap kealoa-admin-wrap">';

        match($action) {
            'add' => $this->render_person_form(),
            'edit' => $this->render_person_form($id),
            default => $this->render_persons_list(),
        };

        echo '</div>';
    }

    /**
     * Render persons list
     */
    private function render_persons_list(): void {
        $search = sanitize_text_field($_GET['s'] ?? '');
        $paged = max(1, (int) ($_GET['paged'] ?? 1));
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;

        $persons = $this->db->get_persons([
            'search' => $search,
            'limit' => $per_page,
            'offset' => $offset,
        ]);

        $total = $this->db->count_persons($search);
        $total_pages = ceil($total / $per_page);
        ?>
        <h1 class="wp-heading-inline"><?php esc_html_e('Persons', 'kealoa-reference'); ?></h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-persons&action=add')); ?>" class="page-title-action">
            <?php esc_html_e('Add New', 'kealoa-reference'); ?>
        </a>

        <form method="get" class="kealoa-search-form">
            <input type="hidden" name="page" value="kealoa-persons" />
            <p class="search-box">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search persons...', 'kealoa-reference'); ?>" />
                <input type="submit" class="button" value="<?php esc_attr_e('Search', 'kealoa-reference'); ?>" />
            </p>
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Full Name', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Home Page', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Actions', 'kealoa-reference'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($persons)): ?>
                    <tr>
                        <td colspan="4"><?php esc_html_e('No persons found.', 'kealoa-reference'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($persons as $person): ?>
                        <tr>
                            <td><?php echo esc_html($person->id); ?></td>
                            <td><?php echo esc_html($person->full_name); ?></td>
                            <td>
                                <?php if ($person->home_page_url): ?>
                                    <?php echo Kealoa_Formatter::format_home_page_link($person->home_page_url); ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-persons&action=edit&id=' . $person->id)); ?>">
                                    <?php esc_html_e('Edit', 'kealoa-reference'); ?>
                                </a> |
                                <a href="<?php echo esc_url(home_url('/kealoa/person/' . urlencode(str_replace(' ', '_', $person->full_name)) . '/')); ?>" target="_blank">
                                    <?php esc_html_e('View', 'kealoa-reference'); ?>
                                </a> |
                                <a href="#" class="kealoa-delete-link"
                                   data-type="person"
                                   data-id="<?php echo esc_attr($person->id); ?>"
                                   data-nonce="<?php echo wp_create_nonce('kealoa_admin_action'); ?>">
                                    <?php esc_html_e('Delete', 'kealoa-reference'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $paged,
                    ]);
                    ?>
                </div>
            </div>
        <?php endif;
    }

    /**
     * Render person form (add/edit)
     */
    private function render_person_form(int $id = 0): void {
        $person = $id ? $this->db->get_person($id) : null;
        $is_edit = $person !== null;
        ?>
        <h1>
            <?php echo $is_edit
                ? esc_html__('Edit Person', 'kealoa-reference')
                : esc_html__('Add New Person', 'kealoa-reference'); ?>
        </h1>

        <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-persons')); ?>" class="button">
            &larr; <?php esc_html_e('Back to Persons', 'kealoa-reference'); ?>
        </a>
        <?php if ($is_edit): ?>
            <a href="<?php echo esc_url(home_url('/kealoa/person/' . urlencode(str_replace(' ', '_', $person->full_name)) . '/')); ?>" class="button" target="_blank" rel="noopener">
                <?php esc_html_e('View', 'kealoa-reference'); ?> &nearr;
            </a>
        <?php endif; ?>

        <form method="post" class="kealoa-form">
            <?php wp_nonce_field('kealoa_admin_action', 'kealoa_nonce'); ?>
            <input type="hidden" name="kealoa_action" value="<?php echo $is_edit ? 'update_person' : 'create_person'; ?>" />
            <?php if ($is_edit): ?>
                <input type="hidden" name="person_id" value="<?php echo esc_attr($id); ?>" />
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th><label for="full_name"><?php esc_html_e('Full Name', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <input type="text" name="full_name" id="full_name" class="regular-text" required
                               value="<?php echo esc_attr($person->full_name ?? ''); ?>" />
                        <p class="description">
                            <?php esc_html_e('Full name of this person (player, constructor, editor, or host).', 'kealoa-reference'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="nicknames"><?php esc_html_e('Nicknames', 'kealoa-reference'); ?></label></th>
                    <td>
                        <input type="text" name="nicknames" id="nicknames" class="regular-text"
                               value="<?php echo esc_attr($person->nicknames ?? ''); ?>" />
                        <p class="description">
                            <?php esc_html_e('Comma-separated list of nicknames or aliases for this person.', 'kealoa-reference'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="home_page_url"><?php esc_html_e('Home Page URL', 'kealoa-reference'); ?></label></th>
                    <td>
                        <input type="url" name="home_page_url" id="home_page_url" class="regular-text"
                               value="<?php echo esc_attr($person->home_page_url ?? ''); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="xwordinfo_profile_name"><?php esc_html_e('XWordInfo Profile Name', 'kealoa-reference'); ?></label></th>
                    <td>
                        <input type="text" name="xwordinfo_profile_name" id="xwordinfo_profile_name" class="regular-text"
                               value="<?php echo esc_attr($person->xwordinfo_profile_name ?? ''); ?>" />
                        <p class="description">
                            <?php esc_html_e('Used for xwordinfo.com/Author/{name} link. Only needed for constructors.', 'kealoa-reference'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="xwordinfo_image_url"><?php esc_html_e('XWordInfo Image URL', 'kealoa-reference'); ?></label></th>
                    <td>
                        <input type="url" name="xwordinfo_image_url" id="xwordinfo_image_url" class="regular-text"
                               value="<?php echo esc_attr($person->xwordinfo_image_url ?? ''); ?>" />
                        <p class="description">
                            <?php esc_html_e('XWordInfo constructor photo URL. Only needed for constructors.', 'kealoa-reference'); ?>
                        </p>
                        <?php if (!empty($person->xwordinfo_image_url)): ?>
                            <p><img src="<?php echo esc_url($person->xwordinfo_image_url); ?>" alt="" style="max-width: 150px; margin-top: 10px;" /></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Media Library Photo', 'kealoa-reference'); ?></th>
                    <td>
                        <input type="hidden" name="media_id" id="person_media_id" value="<?php echo esc_attr($person->media_id ?? ''); ?>" />
                        <div id="person-media-preview" style="margin-bottom: 10px;">
                            <?php if (!empty($person->media_id)): ?>
                                <?php echo wp_get_attachment_image((int) $person->media_id, 'thumbnail'); ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button kealoa-select-media" data-target="#person_media_id" data-preview="#person-media-preview">
                            <?php esc_html_e('Select Photo', 'kealoa-reference'); ?>
                        </button>
                        <button type="button" class="button kealoa-remove-media" data-target="#person_media_id" data-preview="#person-media-preview"
                                style="<?php echo empty($person->media_id) ? 'display:none;' : ''; ?>">
                            <?php esc_html_e('Remove Photo', 'kealoa-reference'); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e('Select a photo from the WordPress media library. This takes priority over Image URL and XWordInfo images.', 'kealoa-reference'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" class="button button-primary"
                       value="<?php echo $is_edit ? esc_attr__('Update Person', 'kealoa-reference') : esc_attr__('Add Person', 'kealoa-reference'); ?>" />
            </p>
        </form>
        <?php
    }

    /**
     * Render constructors page
     */
    /**
     * Render puzzles page
     */
    public function render_puzzles_page(): void {
        $action = $_GET['action'] ?? 'list';
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        echo '<div class="wrap kealoa-admin-wrap">';

        match($action) {
            'add' => $this->render_puzzle_form(),
            'edit' => $this->render_puzzle_form($id),
            default => $this->render_puzzles_list(),
        };

        echo '</div>';
    }

    /**
     * Render puzzles list
     */
    private function render_puzzles_list(): void {
        $search = sanitize_text_field($_GET['s'] ?? '');
        $paged = max(1, (int) ($_GET['paged'] ?? 1));
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;

        $puzzles = $this->db->get_puzzles([
            'constructor_search' => $search,
            'limit' => $per_page,
            'offset' => $offset,
        ]);

        $total = $this->db->count_puzzles($search);
        $total_pages = ceil($total / $per_page);
        ?>
        <h1 class="wp-heading-inline"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-puzzles&action=add')); ?>" class="page-title-action">
            <?php esc_html_e('Add New', 'kealoa-reference'); ?>
        </a>

        <form method="get" class="kealoa-search-form">
            <input type="hidden" name="page" value="kealoa-puzzles" />
            <p class="search-box">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search by constructor...', 'kealoa-reference'); ?>" />
                <input type="submit" class="button" value="<?php esc_attr_e('Search', 'kealoa-reference'); ?>" />
            </p>
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Puzzle Date', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Day of Week', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Constructors', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Actions', 'kealoa-reference'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($puzzles)): ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e('No puzzles found.', 'kealoa-reference'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($puzzles as $puzzle): ?>
                        <?php $constructors = $this->db->get_puzzle_constructors((int) $puzzle->id); ?>
                        <tr>
                            <td><?php echo esc_html($puzzle->id); ?></td>
                            <td><?php echo Kealoa_Formatter::format_puzzle_date_link($puzzle->publication_date); ?></td>
                            <td><?php echo esc_html(date('l', strtotime($puzzle->publication_date))); ?></td>
                            <td><?php echo esc_html($puzzle->editor_name ?? '—'); ?></td>
                            <td>
                                <?php if (!empty($constructors)): ?>
                                    <?php echo Kealoa_Formatter::format_constructor_list($constructors); ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-puzzles&action=edit&id=' . $puzzle->id)); ?>">
                                    <?php esc_html_e('Edit', 'kealoa-reference'); ?>
                                </a> |
                                <a href="#" class="kealoa-delete-link"
                                   data-type="puzzle"
                                   data-id="<?php echo esc_attr($puzzle->id); ?>"
                                   data-nonce="<?php echo wp_create_nonce('kealoa_admin_action'); ?>">
                                    <?php esc_html_e('Delete', 'kealoa-reference'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $paged,
                    ]);
                    ?>
                </div>
            </div>
        <?php endif;
    }

    /**
     * Render puzzle form (add/edit)
     */
    private function render_puzzle_form(int $id = 0): void {
        $puzzle = $id ? $this->db->get_puzzle($id) : null;
        $is_edit = $puzzle !== null;
        $puzzle_constructors = $is_edit ? $this->db->get_puzzle_constructors($id) : [];
        $puzzle_constructor_ids = array_map(fn($c) => (int) $c->id, $puzzle_constructors);
        $all_persons = $this->db->get_persons(['limit' => 1000]);
        ?>
        <h1>
            <?php echo $is_edit
                ? esc_html__('Edit Puzzle', 'kealoa-reference')
                : esc_html__('Add New Puzzle', 'kealoa-reference'); ?>
        </h1>

        <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-puzzles')); ?>" class="button">
            &larr; <?php esc_html_e('Back to Puzzles', 'kealoa-reference'); ?>
        </a>
        <?php if ($is_edit): ?>
            <a href="<?php echo esc_url(home_url('/kealoa/puzzle/' . urlencode($puzzle->publication_date) . '/')); ?>" class="button" target="_blank" rel="noopener">
                <?php esc_html_e('View', 'kealoa-reference'); ?> &nearr;
            </a>
        <?php endif; ?>

        <form method="post" class="kealoa-form">
            <?php wp_nonce_field('kealoa_admin_action', 'kealoa_nonce'); ?>
            <input type="hidden" name="kealoa_action" value="<?php echo $is_edit ? 'update_puzzle' : 'create_puzzle'; ?>" />
            <?php if ($is_edit): ?>
                <input type="hidden" name="puzzle_id" value="<?php echo esc_attr($id); ?>" />
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th><label for="publication_date"><?php esc_html_e('Puzzle Date', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <input type="date" name="publication_date" id="publication_date" required
                               value="<?php echo esc_attr($puzzle->publication_date ?? ''); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="editor_id"><?php esc_html_e('Editor', 'kealoa-reference'); ?></label></th>
                    <td>
                        <select name="editor_id" id="editor_id" class="regular-text">
                            <option value=""><?php esc_html_e('— Select Editor —', 'kealoa-reference'); ?></option>
                            <?php foreach ($all_persons as $p): ?>
                                <option value="<?php echo esc_attr($p->id); ?>"
                                    <?php selected((int) ($puzzle->editor_id ?? 0), (int) $p->id); ?>>
                                    <?php echo esc_html($p->full_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('The crossword editor for this puzzle.', 'kealoa-reference'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="constructors"><?php esc_html_e('Constructors', 'kealoa-reference'); ?></label></th>
                    <td>
                        <select name="constructors[]" id="constructors" multiple class="kealoa-multi-select" style="width: 100%; min-height: 150px;">
                            <?php foreach ($all_persons as $p): ?>
                                <option value="<?php echo esc_attr($p->id); ?>"
                                    <?php echo in_array((int) $p->id, $puzzle_constructor_ids, true) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($p->full_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Hold Ctrl/Cmd to select multiple constructors.', 'kealoa-reference'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" class="button button-primary"
                       value="<?php echo $is_edit ? esc_attr__('Update Puzzle', 'kealoa-reference') : esc_attr__('Add Puzzle', 'kealoa-reference'); ?>" />
            </p>
        </form>
        <?php
    }

    /**
     * Render rounds page
     */
    public function render_rounds_page(): void {
        $action = $_GET['action'] ?? 'list';
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        echo '<div class="wrap kealoa-admin-wrap">';

        $clue_id = isset($_GET['clue_id']) ? (int) $_GET['clue_id'] : 0;

        match($action) {
            'add' => $this->render_round_form(),
            'edit' => $this->render_round_form($id),
            'clues' => $this->render_round_clues_page($id),
            'edit_clue' => $this->render_edit_clue_form($id, $clue_id),
            default => $this->render_rounds_list(),
        };

        echo '</div>';
    }

    /**
     * Render rounds list
     */
    private function render_rounds_list(): void {
        $rounds = $this->db->get_rounds([
            'limit' => 0,
        ]);
        ?>
        <h1 class="wp-heading-inline"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-rounds&action=add')); ?>" class="page-title-action">
            <?php esc_html_e('Add New', 'kealoa-reference'); ?>
        </a>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Date', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Round #', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Episode', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Description', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Clues', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Actions', 'kealoa-reference'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rounds)): ?>
                    <tr>
                        <td colspan="8"><?php esc_html_e('No rounds found.', 'kealoa-reference'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rounds as $round): ?>
                        <?php
                        $solutions = $this->db->get_round_solutions((int) $round->id);
                        $clue_count = $this->db->get_round_clue_count((int) $round->id);
                        ?>
                        <tr>
                            <td><?php echo esc_html($round->id); ?></td>
                            <td><?php echo esc_html(Kealoa_Formatter::format_date($round->round_date)); ?></td>
                            <td><?php echo esc_html($round->round_number ?? 1); ?></td>
                            <td><?php echo Kealoa_Formatter::format_episode_link((int) $round->episode_number, $round->episode_url ?? null); ?></td>
                            <td><?php echo esc_html(Kealoa_Formatter::format_solution_words($solutions)); ?></td>
                            <td><?php echo esc_html($round->description ?? ''); ?></td>
                            <td><?php echo esc_html($clue_count); ?></td>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-rounds&action=edit&id=' . $round->id)); ?>">
                                    <?php esc_html_e('Edit', 'kealoa-reference'); ?>
                                </a> |
                                <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-rounds&action=clues&id=' . $round->id)); ?>">
                                    <?php esc_html_e('Clues', 'kealoa-reference'); ?>
                                </a> |
                                <a href="<?php echo esc_url(home_url('/kealoa/round/' . $round->id . '/')); ?>" target="_blank">
                                    <?php esc_html_e('View', 'kealoa-reference'); ?>
                                </a> |
                                <a href="#" class="kealoa-delete-link"
                                   data-type="round"
                                   data-id="<?php echo esc_attr($round->id); ?>"
                                   data-nonce="<?php echo wp_create_nonce('kealoa_admin_action'); ?>">
                                    <?php esc_html_e('Delete', 'kealoa-reference'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $paged,
                    ]);
                    ?>
                </div>
            </div>
        <?php endif;
    }

    /**
     * Render round form (add/edit)
     */
    private function render_round_form(int $id = 0): void {
        $round = $id ? $this->db->get_round($id) : null;
        $is_edit = $round !== null;
        $solutions = $is_edit ? $this->db->get_round_solutions($id) : [];
        $guessers = $is_edit ? $this->db->get_round_guessers($id) : [];
        $guesser_ids = array_map(fn($g) => $g->id, $guessers);
        $all_persons = $this->db->get_persons(['limit' => 1000]);
        ?>
        <h1>
            <?php echo $is_edit
                ? esc_html__('Edit Round', 'kealoa-reference')
                : esc_html__('Add New Round', 'kealoa-reference'); ?>
        </h1>

        <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-rounds')); ?>" class="button">
            &larr; <?php esc_html_e('Back to Rounds', 'kealoa-reference'); ?>
        </a>

        <?php if ($is_edit): ?>
            <a href="<?php echo esc_url(home_url('/kealoa/round/' . $id . '/')); ?>" class="button" target="_blank" rel="noopener">
                <?php esc_html_e('View', 'kealoa-reference'); ?> &nearr;
            </a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-rounds&action=clues&id=' . $id)); ?>" class="button">
                <?php esc_html_e('Manage Clues', 'kealoa-reference'); ?>
            </a>
        <?php endif; ?>

        <form method="post" class="kealoa-form">
            <?php wp_nonce_field('kealoa_admin_action', 'kealoa_nonce'); ?>
            <input type="hidden" name="kealoa_action" value="<?php echo $is_edit ? 'update_round' : 'create_round'; ?>" />
            <?php if ($is_edit): ?>
                <input type="hidden" name="round_id" value="<?php echo esc_attr($id); ?>" />
            <?php endif; ?>

            <table class="form-table">
                <tr>
                    <th><label for="round_date"><?php esc_html_e('Round Date', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <input type="date" name="round_date" id="round_date" required
                               value="<?php echo esc_attr($round->round_date ?? ''); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="round_number"><?php esc_html_e('Round Number', 'kealoa-reference'); ?></label></th>
                    <td>
                        <input type="number" name="round_number" id="round_number" min="1"
                               value="<?php echo esc_attr($round->round_number ?? 1); ?>" />
                        <p class="description">
                            <?php esc_html_e('For episodes with multiple KEALOA rounds, specify the round number (1, 2, 3...). Defaults to 1.', 'kealoa-reference'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="episode_number"><?php esc_html_e('Episode Number', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <input type="number" name="episode_number" id="episode_number" min="1" required
                               value="<?php echo esc_attr($round->episode_number ?? ''); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="episode_id"><?php esc_html_e('Episode ID', 'kealoa-reference'); ?></label></th>
                    <td>
                        <input type="number" name="episode_id" id="episode_id" min="1"
                               value="<?php echo esc_attr($round->episode_id ?? ''); ?>" />
                        <p class="description">
                            <?php esc_html_e('Optional internal episode ID for reference.', 'kealoa-reference'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="episode_url"><?php esc_html_e('Episode URL', 'kealoa-reference'); ?></label></th>
                    <td>
                        <input type="url" name="episode_url" id="episode_url" class="regular-text"
                               value="<?php echo esc_attr($round->episode_url ?? ''); ?>" />
                        <p class="description">
                            <?php esc_html_e('The raw URL of the podcast episode.', 'kealoa-reference'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="episode_start_time"><?php esc_html_e('Start Time', 'kealoa-reference'); ?></label></th>
                    <td>
                        <input type="text" name="episode_start_time" id="episode_start_time"
                               placeholder="HH:MM:SS" pattern="[0-9]{1,2}:[0-9]{2}:[0-9]{2}"
                               value="<?php echo esc_attr(Kealoa_Formatter::seconds_to_time((int) ($round->episode_start_seconds ?? 0))); ?>" />
                        <p class="description">
                            <?php esc_html_e('Time after the start of the episode where the KEALOA round begins (HH:MM:SS format, e.g., 01:23:45).', 'kealoa-reference'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="clue_giver_id"><?php esc_html_e('Clue Giver', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <select name="clue_giver_id" id="clue_giver_id" required>
                            <option value=""><?php esc_html_e('— Select —', 'kealoa-reference'); ?></option>
                            <?php foreach ($all_persons as $person): ?>
                                <option value="<?php echo esc_attr($person->id); ?>"
                                    <?php echo isset($round->clue_giver_id) && $round->clue_giver_id == $person->id ? 'selected' : ''; ?>>
                                    <?php echo esc_html($person->full_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="guessers"><?php esc_html_e('Players', 'kealoa-reference'); ?></label></th>
                    <td>
                        <select name="guessers[]" id="guessers" multiple class="kealoa-multi-select" style="width: 100%; min-height: 150px;">
                            <?php foreach ($all_persons as $person): ?>
                                <option value="<?php echo esc_attr($person->id); ?>"
                                    <?php echo in_array($person->id, $guesser_ids) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($person->full_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Hold Ctrl/Cmd to select multiple players.', 'kealoa-reference'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="solution_words"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></label></th>
                    <td>
                        <input type="text" name="solution_words" id="solution_words" class="regular-text"
                               value="<?php echo esc_attr(implode(', ', array_map(fn($s) => $s->word, $solutions))); ?>" />
                        <p class="description">
                            <?php esc_html_e('Enter solution words separated by commas (e.g., "KEA, LOA"). Words will be automatically capitalized.', 'kealoa-reference'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="description"><?php esc_html_e('Description', 'kealoa-reference'); ?></label></th>
                    <td>
                        <textarea name="description" id="description" rows="4" class="large-text"><?php echo esc_textarea($round->description ?? ''); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('A brief text description of the round. Shown in the game header before playing.', 'kealoa-reference'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="description2"><?php esc_html_e('Spoiler Description', 'kealoa-reference'); ?></label></th>
                    <td>
                        <textarea name="description2" id="description2" rows="4" class="large-text"><?php echo esc_textarea($round->description2 ?? ''); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('Additional description shown after the game is complete (may contain spoilers).', 'kealoa-reference'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" class="button button-primary"
                       value="<?php echo $is_edit ? esc_attr__('Update Round', 'kealoa-reference') : esc_attr__('Add Round', 'kealoa-reference'); ?>" />
            </p>
        </form>
        <?php
    }

    /**
     * Render round clues management page
     */
    private function render_round_clues_page(int $round_id): void {
        $round = $this->db->get_round($round_id);
        if (!$round) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Round not found.', 'kealoa-reference') . '</p></div>';
            return;
        }

        $clues = $this->db->get_round_clues($round_id);
        $solutions = $this->db->get_round_solutions($round_id);
        $guessers = $this->db->get_round_guessers($round_id);
        $puzzles = $this->db->get_puzzles(['limit' => 1000, 'order' => 'DESC']);
        $all_constructors = $this->db->get_persons_who_are_constructors();
        ?>
        <h1><?php printf(esc_html__('Clues for Round %s', 'kealoa-reference'), Kealoa_Formatter::format_date($round->round_date)); ?></h1>

        <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-rounds')); ?>" class="button">
            &larr; <?php esc_html_e('Back to Rounds', 'kealoa-reference'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-rounds&action=edit&id=' . $round_id)); ?>" class="button">
            <?php esc_html_e('Edit Round', 'kealoa-reference'); ?>
        </a>

        <div class="kealoa-round-info">
            <p>
                <strong><?php esc_html_e('Solution Words:', 'kealoa-reference'); ?></strong>
                <?php echo esc_html(Kealoa_Formatter::format_solution_words($solutions)); ?>
            </p>
            <p>
                <strong><?php esc_html_e('Players:', 'kealoa-reference'); ?></strong>
                <?php
                $guesser_names = array_map(fn($g) => $g->full_name, $guessers);
                echo esc_html(Kealoa_Formatter::format_list_with_and($guesser_names));
                ?>
            </p>
        </div>

        <h2><?php esc_html_e('Existing Clues', 'kealoa-reference'); ?></h2>

        <?php if (empty($clues)): ?>
            <p><?php esc_html_e('No clues added yet.', 'kealoa-reference'); ?></p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th><?php esc_html_e('Puzzle Date', 'kealoa-reference'); ?></th>
                        <th><?php esc_html_e('Clue #/Dir', 'kealoa-reference'); ?></th>
                        <th><?php esc_html_e('Clue Text', 'kealoa-reference'); ?></th>
                        <th><?php esc_html_e('Correct Answer', 'kealoa-reference'); ?></th>
                        <?php foreach ($guessers as $guesser): ?>
                            <th><?php echo esc_html($guesser->full_name); ?></th>
                        <?php endforeach; ?>
                        <th><?php esc_html_e('Actions', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clues as $clue): ?>
                        <?php $clue_guesses = $this->db->get_clue_guesses((int) $clue->id); ?>
                        <tr>
                            <td><?php echo esc_html($clue->clue_number); ?></td>
                            <td><?php echo Kealoa_Formatter::format_puzzle_date_link($clue->puzzle_date); ?></td>
                            <td><?php echo esc_html(Kealoa_Formatter::format_clue_direction((int) $clue->puzzle_clue_number, $clue->puzzle_clue_direction)); ?></td>
                            <td><?php echo esc_html($clue->clue_text); ?></td>
                            <td><strong><?php echo esc_html($clue->correct_answer); ?></strong></td>
                            <?php foreach ($guessers as $guesser): ?>
                                <td>
                                    <?php
                                    $guess = null;
                                    foreach ($clue_guesses as $g) {
                                        if ($g->guesser_person_id == $guesser->id) {
                                            $guess = $g;
                                            break;
                                        }
                                    }
                                    if ($guess) {
                                        echo Kealoa_Formatter::format_guess_display($guess->guessed_word, (bool) $guess->is_correct);
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                            <td>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-rounds&action=edit_clue&id=' . $round_id . '&clue_id=' . $clue->id)); ?>">
                                    <?php esc_html_e('Edit', 'kealoa-reference'); ?>
                                </a> |
                                <a href="#" class="kealoa-delete-link"
                                   data-type="clue"
                                   data-id="<?php echo esc_attr($clue->id); ?>"
                                   data-nonce="<?php echo wp_create_nonce('kealoa_admin_action'); ?>">
                                    <?php esc_html_e('Delete', 'kealoa-reference'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2><?php esc_html_e('Add New Clue', 'kealoa-reference'); ?></h2>

        <form method="post" class="kealoa-form">
            <?php wp_nonce_field('kealoa_admin_action', 'kealoa_nonce'); ?>
            <input type="hidden" name="kealoa_action" value="create_clue" />
            <input type="hidden" name="round_id" value="<?php echo esc_attr($round_id); ?>" />

            <table class="form-table">
                <tr>
                    <th><label for="clue_number"><?php esc_html_e('Clue Number', 'kealoa-reference'); ?></label></th>
                    <td>
                        <input type="number" name="clue_number" id="clue_number" min="1"
                               value="<?php echo count($clues) + 1; ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="puzzle_date"><?php esc_html_e('NYT Puzzle Date', 'kealoa-reference'); ?></label></th>
                    <td>
                        <input type="date" name="puzzle_date" id="puzzle_date" class="regular-text" />
                        <p class="description">
                            <?php esc_html_e('If a puzzle with this date already exists, it will be used automatically.', 'kealoa-reference'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="puzzle_constructors"><?php esc_html_e('Constructors', 'kealoa-reference'); ?></label></th>
                    <td>
                        <select name="puzzle_constructors[]" id="puzzle_constructors" multiple class="kealoa-multi-select" style="width: 100%; min-height: 120px;">
                            <?php foreach ($all_constructors as $constructor): ?>
                                <option value="<?php echo esc_attr($constructor->id); ?>">
                                    <?php echo esc_html($constructor->full_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Hold Ctrl/Cmd to select multiple. Only used when creating a new puzzle.', 'kealoa-reference'); ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-persons&action=add')); ?>" target="_blank">
                                <?php esc_html_e('Add new person', 'kealoa-reference'); ?> &rarr;
                            </a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="puzzle_clue_number"><?php esc_html_e('Puzzle Clue Number', 'kealoa-reference'); ?></label></th>
                    <td>
                        <input type="number" name="puzzle_clue_number" id="puzzle_clue_number" min="1" />
                    </td>
                </tr>
                <tr>
                    <th><label for="puzzle_clue_direction"><?php esc_html_e('Direction', 'kealoa-reference'); ?></label></th>
                    <td>
                        <select name="puzzle_clue_direction" id="puzzle_clue_direction">
                            <option value=""><?php esc_html_e('— None —', 'kealoa-reference'); ?></option>
                            <option value="A"><?php esc_html_e('Across', 'kealoa-reference'); ?></option>
                            <option value="D"><?php esc_html_e('Down', 'kealoa-reference'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="clue_text"><?php esc_html_e('Clue Text', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <textarea name="clue_text" id="clue_text" rows="2" class="large-text" required></textarea>
                    </td>
                </tr>
                <tr>
                    <th><label for="correct_answer"><?php esc_html_e('Correct Answer', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <select name="correct_answer" id="correct_answer" required>
                            <option value=""><?php esc_html_e('— Select —', 'kealoa-reference'); ?></option>
                            <?php foreach ($solutions as $solution): ?>
                                <option value="<?php echo esc_attr($solution->word); ?>">
                                    <?php echo esc_html($solution->word); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e('Guesser Answers', 'kealoa-reference'); ?></h3>

            <table class="form-table">
                <?php foreach ($guessers as $guesser): ?>
                    <tr>
                        <th><label for="guess_<?php echo esc_attr($guesser->id); ?>"><?php echo esc_html($guesser->full_name); ?></label></th>
                        <td>
                            <select name="guesses[<?php echo esc_attr($guesser->id); ?>]" id="guess_<?php echo esc_attr($guesser->id); ?>">
                                <option value=""><?php esc_html_e('— No guess —', 'kealoa-reference'); ?></option>
                                <?php foreach ($solutions as $solution): ?>
                                    <option value="<?php echo esc_attr($solution->word); ?>">
                                        <?php echo esc_html($solution->word); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php esc_attr_e('Add Clue', 'kealoa-reference'); ?>" />
            </p>
        </form>
        <?php
    }

    /**
     * Render edit clue form
     */
    private function render_edit_clue_form(int $round_id, int $clue_id): void {
        $round = $this->db->get_round($round_id);
        $clue = $this->db->get_clue($clue_id);

        if (!$round || !$clue || $clue->round_id != $round_id) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Clue not found.', 'kealoa-reference') . '</p></div>';
            return;
        }

        $solutions = $this->db->get_round_solutions($round_id);
        $guessers = $this->db->get_round_guessers($round_id);
        $all_constructors = $this->db->get_persons_who_are_constructors();
        $clue_guesses = $this->db->get_clue_guesses($clue_id);

        // Get the puzzle for this clue
        $puzzle = $this->db->get_puzzle((int) $clue->puzzle_id);
        $puzzle_constructors = $puzzle ? $this->db->get_puzzle_constructors((int) $puzzle->id) : [];
        $puzzle_constructor_ids = array_map(fn($c) => (int) $c->id, $puzzle_constructors);

        // Build a map of guesser_id => guessed_word
        $guess_map = [];
        foreach ($clue_guesses as $g) {
            $guess_map[$g->guesser_person_id] = $g->guessed_word;
        }
        ?>
        <h1><?php printf(esc_html__('Edit Clue #%d', 'kealoa-reference'), $clue->clue_number); ?></h1>

        <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-rounds&action=clues&id=' . $round_id)); ?>" class="button">
            &larr; <?php esc_html_e('Back to Clues', 'kealoa-reference'); ?>
        </a>

        <div class="kealoa-round-info">
            <p>
                <strong><?php esc_html_e('Round:', 'kealoa-reference'); ?></strong>
                <?php echo esc_html(Kealoa_Formatter::format_date($round->round_date)); ?>
                (<?php esc_html_e('Episode', 'kealoa-reference'); ?> <?php echo esc_html($round->episode_number); ?>)
            </p>
            <p>
                <strong><?php esc_html_e('Solution Words:', 'kealoa-reference'); ?></strong>
                <?php echo esc_html(Kealoa_Formatter::format_solution_words($solutions)); ?>
            </p>
        </div>

        <form method="post" class="kealoa-form">
            <?php wp_nonce_field('kealoa_admin_action', 'kealoa_nonce'); ?>
            <input type="hidden" name="kealoa_action" value="update_clue" />
            <input type="hidden" name="clue_id" value="<?php echo esc_attr($clue_id); ?>" />
            <input type="hidden" name="round_id" value="<?php echo esc_attr($round_id); ?>" />

            <table class="form-table">
                <tr>
                    <th><label for="clue_number"><?php esc_html_e('Clue Number', 'kealoa-reference'); ?></label></th>
                    <td>
                        <input type="number" name="clue_number" id="clue_number" min="1"
                               value="<?php echo esc_attr($clue->clue_number); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Puzzle Details', 'kealoa-reference'); ?></th>
                    <td>
                        <button type="button" class="button kealoa-clear-puzzle-details">
                            <?php esc_html_e('Clear Puzzle Details', 'kealoa-reference'); ?>
                        </button>
                        <p class="description">
                            <?php esc_html_e('Clears the date, constructors, clue number, and direction below.', 'kealoa-reference'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="puzzle_date"><?php esc_html_e('NYT Puzzle Date', 'kealoa-reference'); ?></label></th>
                    <td>
                        <input type="date" name="puzzle_date" id="puzzle_date" class="regular-text"
                               value="<?php echo esc_attr($puzzle->publication_date ?? ''); ?>" />
                        <p class="description">
                            <?php esc_html_e('If a puzzle with this date already exists, it will be used automatically.', 'kealoa-reference'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="puzzle_constructors"><?php esc_html_e('Constructors', 'kealoa-reference'); ?></label></th>
                    <td>
                        <select name="puzzle_constructors[]" id="puzzle_constructors" multiple class="kealoa-multi-select" style="width: 100%; min-height: 120px;">
                            <?php foreach ($all_constructors as $constructor): ?>
                                <option value="<?php echo esc_attr($constructor->id); ?>"
                                    <?php echo in_array((int) $constructor->id, $puzzle_constructor_ids, true) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($constructor->full_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Hold Ctrl/Cmd to select multiple. Only used when creating a new puzzle.', 'kealoa-reference'); ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-persons&action=add')); ?>" target="_blank">
                                <?php esc_html_e('Add new person', 'kealoa-reference'); ?> &rarr;
                            </a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="puzzle_clue_number"><?php esc_html_e('Puzzle Clue Number', 'kealoa-reference'); ?></label></th>
                    <td>
                        <input type="number" name="puzzle_clue_number" id="puzzle_clue_number" min="1"
                               value="<?php echo esc_attr($clue->puzzle_clue_number); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="puzzle_clue_direction"><?php esc_html_e('Direction', 'kealoa-reference'); ?></label></th>
                    <td>
                        <select name="puzzle_clue_direction" id="puzzle_clue_direction">
                            <option value=""><?php esc_html_e('— None —', 'kealoa-reference'); ?></option>
                            <option value="A" <?php selected($clue->puzzle_clue_direction, 'A'); ?>><?php esc_html_e('Across', 'kealoa-reference'); ?></option>
                            <option value="D" <?php selected($clue->puzzle_clue_direction, 'D'); ?>><?php esc_html_e('Down', 'kealoa-reference'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="clue_text"><?php esc_html_e('Clue Text', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <textarea name="clue_text" id="clue_text" rows="2" class="large-text" required><?php echo esc_textarea($clue->clue_text); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th><label for="correct_answer"><?php esc_html_e('Correct Answer', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <select name="correct_answer" id="correct_answer" required>
                            <option value=""><?php esc_html_e('— Select —', 'kealoa-reference'); ?></option>
                            <?php foreach ($solutions as $solution): ?>
                                <option value="<?php echo esc_attr($solution->word); ?>"
                                    <?php selected($clue->correct_answer, $solution->word); ?>>
                                    <?php echo esc_html($solution->word); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <h3><?php esc_html_e('Guesser Answers', 'kealoa-reference'); ?></h3>

            <table class="form-table">
                <?php foreach ($guessers as $guesser): ?>
                    <?php $current_guess = $guess_map[$guesser->id] ?? ''; ?>
                    <tr>
                        <th><label for="guess_<?php echo esc_attr($guesser->id); ?>"><?php echo esc_html($guesser->full_name); ?></label></th>
                        <td>
                            <select name="guesses[<?php echo esc_attr($guesser->id); ?>]" id="guess_<?php echo esc_attr($guesser->id); ?>">
                                <option value=""><?php esc_html_e('— No guess —', 'kealoa-reference'); ?></option>
                                <?php foreach ($solutions as $solution): ?>
                                    <option value="<?php echo esc_attr($solution->word); ?>"
                                        <?php selected($current_guess, $solution->word); ?>>
                                        <?php echo esc_html($solution->word); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php esc_attr_e('Update Clue', 'kealoa-reference'); ?>" />
                <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-rounds&action=clues&id=' . $round_id)); ?>" class="button">
                    <?php esc_html_e('Cancel', 'kealoa-reference'); ?>
                </a>
            </p>
        </form>
        <?php
    }

    // =========================================================================
    // AJAX HANDLERS
    // =========================================================================

    /**
     * AJAX handler to get persons list
     */
    public function ajax_get_persons(): void {
        check_ajax_referer('kealoa_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $persons = $this->db->get_persons(['limit' => 1000]);

        $data = array_map(fn($p) => [
            'id' => $p->id,
            'full_name' => $p->full_name,
        ], $persons);

        wp_send_json_success($data);
    }

    // =========================================================================
    // FORM HANDLERS
    // =========================================================================

    /**
     * Handle create person
     */
    private function handle_create_person(): void {
        $full_name = $_POST['full_name'] ?? '';
        $id = $this->db->create_person([
            'full_name' => $full_name,
            'nicknames' => $_POST['nicknames'] ?? null,
            'home_page_url' => $_POST['home_page_url'] ?? null,
            'media_id' => !empty($_POST['media_id']) ? (int) $_POST['media_id'] : null,
            'xwordinfo_profile_name' => $_POST['xwordinfo_profile_name'] ?? null,
            'xwordinfo_image_url' => !empty($_POST['xwordinfo_image_url'])
                ? $_POST['xwordinfo_image_url']
                : Kealoa_Formatter::xwordinfo_image_url_from_name($full_name),
        ]);

        if ($id) {
            $this->redirect_with_message('kealoa-persons', 'Person created successfully.', 'success', ['action' => 'edit', 'id' => $id]);
        } else {
            $this->redirect_with_message('kealoa-persons', 'Failed to create person.', 'error');
        }
    }

    /**
     * Handle update person
     */
    private function handle_update_person(): void {
        $id = (int) ($_POST['person_id'] ?? 0);

        $result = $this->db->update_person($id, [
            'full_name' => $_POST['full_name'] ?? '',
            'nicknames' => $_POST['nicknames'] ?? null,
            'home_page_url' => $_POST['home_page_url'] ?? null,
            'media_id' => !empty($_POST['media_id']) ? (int) $_POST['media_id'] : null,
            'xwordinfo_profile_name' => $_POST['xwordinfo_profile_name'] ?? null,
            'xwordinfo_image_url' => $_POST['xwordinfo_image_url'] ?? null,
        ]);

        if ($result) {
            $this->redirect_with_message('kealoa-persons', 'Person updated successfully.', 'success', ['action' => 'edit', 'id' => $id]);
        } else {
            $this->redirect_with_message('kealoa-persons', 'Failed to update person.', 'error', ['action' => 'edit', 'id' => $id]);
        }
    }

    /**
     * Handle delete person
     */
    private function handle_delete_person(): void {
        $id = (int) ($_POST['id'] ?? 0);
        $this->db->delete_person($id);
        $this->redirect_with_message('kealoa-persons', 'Person deleted.');
    }

    /**
     * Handle create puzzle
     */
    private function handle_create_puzzle(): void {
        $id = $this->db->create_puzzle([
            'publication_date' => $_POST['publication_date'] ?? '',
            'editor_id' => !empty($_POST['editor_id']) ? (int) $_POST['editor_id'] : null,
        ]);

        if ($id) {
            $constructors = $_POST['constructors'] ?? [];
            if (!empty($constructors)) {
                $this->db->set_puzzle_constructors($id, array_map('intval', $constructors));
            }
            $this->redirect_with_message('kealoa-puzzles', 'Puzzle created successfully.', 'success', ['action' => 'edit', 'id' => $id]);
        } else {
            $this->redirect_with_message('kealoa-puzzles', 'Failed to create puzzle.', 'error');
        }
    }

    /**
     * Handle update puzzle
     */
    private function handle_update_puzzle(): void {
        $id = (int) ($_POST['puzzle_id'] ?? 0);

        $result = $this->db->update_puzzle($id, [
            'publication_date' => $_POST['publication_date'] ?? '',
            'editor_id' => !empty($_POST['editor_id']) ? (int) $_POST['editor_id'] : null,
        ]);

        $constructors = $_POST['constructors'] ?? [];
        $this->db->set_puzzle_constructors($id, array_map('intval', $constructors));

        if ($result) {
            $this->redirect_with_message('kealoa-puzzles', 'Puzzle updated successfully.', 'success', ['action' => 'edit', 'id' => $id]);
        } else {
            $this->redirect_with_message('kealoa-puzzles', 'Failed to update puzzle.', 'error', ['action' => 'edit', 'id' => $id]);
        }
    }

    /**
     * Handle delete puzzle
     */
    private function handle_delete_puzzle(): void {
        $id = (int) ($_POST['id'] ?? 0);
        $this->db->delete_puzzle($id);
        $this->redirect_with_message('kealoa-puzzles', 'Puzzle deleted.');
    }

    /**
     * Handle create round
     */
    private function handle_create_round(): void {
        $id = $this->db->create_round([
            'round_date' => $_POST['round_date'] ?? '',
            'round_number' => $_POST['round_number'] ?? 1,
            'episode_number' => $_POST['episode_number'] ?? 0,
            'episode_id' => $_POST['episode_id'] ?? null,
            'episode_url' => $_POST['episode_url'] ?? null,
            'episode_start_seconds' => Kealoa_Formatter::time_to_seconds($_POST['episode_start_time'] ?? ''),
            'clue_giver_id' => $_POST['clue_giver_id'] ?? 0,
            'description' => $_POST['description'] ?? null,
            'description2' => $_POST['description2'] ?? null,
        ]);

        if ($id) {
            // Set solution words
            $solution_words = $_POST['solution_words'] ?? '';
            if (!empty($solution_words)) {
                $words = array_map('trim', explode(',', $solution_words));
                $words = array_filter($words);
                $this->db->set_round_solutions($id, $words);
            }

            // Set guessers
            $guessers = $_POST['guessers'] ?? [];
            if (!empty($guessers)) {
                $this->db->set_round_guessers($id, array_map('intval', $guessers));
            }

            $this->redirect_with_message('kealoa-rounds', 'Round created successfully.', 'success', ['action' => 'edit', 'id' => $id]);
        } else {
            $this->redirect_with_message('kealoa-rounds', 'Failed to create round.', 'error');
        }
    }

    /**
     * Handle update round
     */
    private function handle_update_round(): void {
        $id = (int) ($_POST['round_id'] ?? 0);

        $result = $this->db->update_round($id, [
            'round_date' => $_POST['round_date'] ?? '',
            'round_number' => $_POST['round_number'] ?? 1,
            'episode_number' => $_POST['episode_number'] ?? 0,
            'episode_id' => $_POST['episode_id'] ?? null,
            'episode_url' => $_POST['episode_url'] ?? null,
            'episode_start_seconds' => Kealoa_Formatter::time_to_seconds($_POST['episode_start_time'] ?? ''),
            'clue_giver_id' => $_POST['clue_giver_id'] ?? 0,
            'description' => $_POST['description'] ?? null,
            'description2' => $_POST['description2'] ?? null,
        ]);

        // Set solution words
        $solution_words = $_POST['solution_words'] ?? '';
        if (!empty($solution_words)) {
            $words = array_map('trim', explode(',', $solution_words));
            $words = array_filter($words);
            $this->db->set_round_solutions($id, $words);
        } else {
            $this->db->set_round_solutions($id, []);
        }

        // Set guessers
        $guessers = $_POST['guessers'] ?? [];
        $this->db->set_round_guessers($id, array_map('intval', $guessers));

        if ($result) {
            $this->redirect_with_message('kealoa-rounds', 'Round updated successfully.', 'success', ['action' => 'edit', 'id' => $id]);
        } else {
            $this->redirect_with_message('kealoa-rounds', 'Failed to update round.', 'error', ['action' => 'edit', 'id' => $id]);
        }
    }

    /**
     * Handle delete round
     */
    private function handle_delete_round(): void {
        $id = (int) ($_POST['id'] ?? 0);
        $this->db->delete_round($id);
        $this->redirect_with_message('kealoa-rounds', 'Round deleted.');
    }

    /**
     * Handle create clue
     */
    private function handle_create_clue(): void {
        $round_id = (int) ($_POST['round_id'] ?? 0);

        // Get or create puzzle based on date
        $puzzle_date = sanitize_text_field($_POST['puzzle_date'] ?? '');
        $puzzle_id = 0;

        if (!empty($puzzle_date)) {
            // Check if puzzle already exists for this date
            $existing_puzzle = $this->db->get_puzzle_by_date($puzzle_date);
            if ($existing_puzzle) {
                $puzzle_id = (int) $existing_puzzle->id;
            } else {
                // Create new puzzle
                $puzzle_id = $this->db->create_puzzle([
                    'publication_date' => $puzzle_date,
                ]);

                // Set constructors if provided
                if ($puzzle_id && !empty($_POST['puzzle_constructors'])) {
                    $constructor_ids = array_map('intval', $_POST['puzzle_constructors']);
                    $this->db->set_puzzle_constructors($puzzle_id, $constructor_ids);
                }
            }
        }

        $clue_id = $this->db->create_clue([
            'round_id' => $round_id,
            'clue_number' => $_POST['clue_number'] ?? 1,
            'puzzle_id' => $puzzle_id ?: null,
            'puzzle_clue_number' => !empty($_POST['puzzle_clue_number']) ? $_POST['puzzle_clue_number'] : null,
            'puzzle_clue_direction' => !empty($_POST['puzzle_clue_direction']) ? $_POST['puzzle_clue_direction'] : null,
            'clue_text' => $_POST['clue_text'] ?? '',
            'correct_answer' => $_POST['correct_answer'] ?? '',
        ]);

        if ($clue_id) {
            // Save guesses
            $guesses = $_POST['guesses'] ?? [];
            foreach ($guesses as $guesser_id => $guessed_word) {
                if (!empty($guessed_word)) {
                    $this->db->set_guess($clue_id, (int) $guesser_id, $guessed_word);
                }
            }

            wp_redirect(admin_url('admin.php?page=kealoa-rounds&action=clues&id=' . $round_id . '&message=clue_created'));
            exit;
        } else {
            wp_redirect(admin_url('admin.php?page=kealoa-rounds&action=clues&id=' . $round_id . '&error=clue_failed'));
            exit;
        }
    }

    /**
     * Handle update clue
     */
    private function handle_update_clue(): void {
        $clue_id = (int) ($_POST['clue_id'] ?? 0);
        $round_id = (int) ($_POST['round_id'] ?? 0);
        $clue = $this->db->get_clue($clue_id);

        if (!$clue) {
            return;
        }

        // Get or create puzzle based on date (same logic as create_clue)
        $puzzle_date = sanitize_text_field($_POST['puzzle_date'] ?? '');
        $puzzle_id = 0;

        if (!empty($puzzle_date)) {
            // Check if puzzle already exists for this date
            $existing_puzzle = $this->db->get_puzzle_by_date($puzzle_date);
            if ($existing_puzzle) {
                $puzzle_id = (int) $existing_puzzle->id;
            } else {
                // Create new puzzle
                $puzzle_id = $this->db->create_puzzle([
                    'publication_date' => $puzzle_date,
                ]);

                // Set constructors if provided
                if ($puzzle_id && !empty($_POST['puzzle_constructors'])) {
                    $constructor_ids = array_map('intval', $_POST['puzzle_constructors']);
                    $this->db->set_puzzle_constructors($puzzle_id, $constructor_ids);
                }
            }
        }

        $this->db->update_clue($clue_id, [
            'clue_number' => $_POST['clue_number'] ?? 1,
            'puzzle_id' => $puzzle_id ?: null,
            'puzzle_clue_number' => !empty($_POST['puzzle_clue_number']) ? $_POST['puzzle_clue_number'] : null,
            'puzzle_clue_direction' => !empty($_POST['puzzle_clue_direction']) ? $_POST['puzzle_clue_direction'] : null,
            'clue_text' => $_POST['clue_text'] ?? '',
            'correct_answer' => $_POST['correct_answer'] ?? '',
        ]);

        // Update guesses
        $guesses = $_POST['guesses'] ?? [];
        foreach ($guesses as $guesser_id => $guessed_word) {
            if (!empty($guessed_word)) {
                $this->db->set_guess($clue_id, (int) $guesser_id, $guessed_word);
            } else {
                $this->db->delete_guess($clue_id, (int) $guesser_id);
            }
        }

        wp_redirect(admin_url('admin.php?page=kealoa-rounds&action=clues&id=' . $clue->round_id . '&message=clue_updated'));
        exit;
    }

    /**
     * Handle delete clue
     */
    private function handle_delete_clue(): void {
        $id = (int) ($_POST['id'] ?? 0);
        $clue = $this->db->get_clue($id);

        if ($clue) {
            $round_id = $clue->round_id;
            $this->db->delete_clue($id);
            wp_redirect(admin_url('admin.php?page=kealoa-rounds&action=clues&id=' . $round_id . '&message=clue_deleted'));
            exit;
        }
    }

    /**
     * Handle save guesses
     */
    private function handle_save_guesses(): void {
        $clue_id = (int) ($_POST['clue_id'] ?? 0);
        $guesses = $_POST['guesses'] ?? [];

        foreach ($guesses as $guesser_id => $guessed_word) {
            if (!empty($guessed_word)) {
                $this->db->set_guess($clue_id, (int) $guesser_id, $guessed_word);
            } else {
                $this->db->delete_guess($clue_id, (int) $guesser_id);
            }
        }
    }

    /**
     * Redirect with message
     */
    private function redirect_with_message(string $page, string $message, string $type = 'success', array $extra_args = []): void {
        // Flush transient caches before redirecting (must happen before exit)
        Kealoa_Shortcodes::flush_all_caches();

        set_transient('kealoa_admin_message', [
            'message' => $message,
            'type' => $type,
        ], 30);

        $url = admin_url('admin.php?page=' . $page);
        if (!empty($extra_args)) {
            $url = add_query_arg($extra_args, $url);
        }

        wp_redirect($url);
        exit;
    }

    /**
     * AJAX handler to save/remove media for a person, constructor, or editor
     */
    public function ajax_save_media(): void {
        check_ajax_referer('kealoa_media_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        $entity_type = sanitize_text_field($_POST['entity_type'] ?? '');
        $entity_id = sanitize_text_field($_POST['entity_id'] ?? '');
        $media_id = (int) ($_POST['media_id'] ?? 0);

        if ($entity_type !== 'person') {
            wp_send_json_error('Invalid entity type');
        }

        $id = (int) $entity_id;
        if (!$id) {
            wp_send_json_error('Invalid entity ID');
        }
        $this->db->update_person($id, ['media_id' => $media_id ?: null]);

        $image_url = '';
        if ($media_id > 0) {
            $image = wp_get_attachment_image_src($media_id, 'medium');
            $image_url = $image ? $image[0] : '';
        }

        wp_send_json_success(['media_id' => $media_id, 'image_url' => $image_url]);
    }

    // =========================================================================
    // DATA CHECK PAGE
    // =========================================================================

    /**
     * Render the data consistency check page.
     */
    public function render_data_check_page(): void {
        $issues = $this->db->run_consistency_checks();
        $total_issues = 0;
        foreach ($issues as $rows) {
            $total_issues += count($rows);
        }

        // Human-readable labels and descriptions for each check
        $check_meta = [
            'orphan_puzzles' => [
                'label'       => 'Orphan Puzzles',
                'description' => 'Puzzles not referenced by any clue (unused puzzles).',
                'action'      => 'repair_delete_puzzles',
                'columns'     => ['ID', 'Puzzle Date', 'Editor ID'],
                'fields'      => ['id', 'publication_date', 'editor_id'],
            ],
            'rounds_no_clues' => [
                'label'       => 'Rounds With No Clues',
                'description' => 'Rounds that have zero clues associated with them.',
                'action'      => 'repair_delete_rounds',
                'columns'     => ['ID', 'Date', 'Round #', 'Description'],
                'fields'      => ['id', 'round_date', 'round_number', 'description'],
            ],
            'rounds_no_solutions' => [
                'label'       => 'Rounds With No Solution Words',
                'description' => 'Rounds that have no solution words defined.',
                'action'      => 'repair_delete_rounds',
                'columns'     => ['ID', 'Date', 'Round #', 'Description'],
                'fields'      => ['id', 'round_date', 'round_number', 'description'],
            ],
            'rounds_no_guessers' => [
                'label'       => 'Rounds With No Guessers',
                'description' => 'Rounds that have no guessers assigned.',
                'action'      => 'repair_delete_rounds',
                'columns'     => ['ID', 'Date', 'Round #', 'Description'],
                'fields'      => ['id', 'round_date', 'round_number', 'description'],
            ],
            'orphan_puzzle_constructor_puzzles' => [
                'label'       => 'Puzzle-Constructor Links to Missing Puzzles',
                'description' => 'Puzzle-constructor junction records referencing puzzles that no longer exist.',
                'action'      => 'repair_delete_orphans',
                'table_key'   => 'puzzle_constructors',
                'columns'     => ['Link ID', 'Puzzle ID', 'Person ID'],
                'fields'      => ['id', 'puzzle_id', 'person_id'],
            ],
            'orphan_puzzle_constructor_persons' => [
                'label'       => 'Puzzle-Constructor Links to Missing Persons',
                'description' => 'Puzzle-constructor junction records referencing persons that no longer exist.',
                'action'      => 'repair_delete_orphans',
                'table_key'   => 'puzzle_constructors',
                'columns'     => ['Link ID', 'Puzzle ID', 'Person ID'],
                'fields'      => ['id', 'puzzle_id', 'person_id'],
            ],
            'orphan_clue_rounds' => [
                'label'       => 'Clues Referencing Missing Rounds',
                'description' => 'Clues that reference rounds which no longer exist.',
                'action'      => 'repair_delete_orphans',
                'table_key'   => 'clues',
                'columns'     => ['Clue ID', 'Round ID', 'Clue #', 'Clue Text'],
                'fields'      => ['id', 'round_id', 'clue_number', 'clue_text'],
            ],
            'orphan_clue_puzzles' => [
                'label'       => 'Clues Referencing Missing Puzzles',
                'description' => 'Clues that reference puzzles which no longer exist.',
                'action'      => 'repair_delete_orphans',
                'table_key'   => 'clues',
                'columns'     => ['Clue ID', 'Round ID', 'Clue #', 'Puzzle ID'],
                'fields'      => ['id', 'round_id', 'clue_number', 'puzzle_id'],
            ],
            'orphan_guess_clues' => [
                'label'       => 'Guesses Referencing Missing Clues',
                'description' => 'Guesses that reference clues which no longer exist.',
                'action'      => 'repair_delete_orphans',
                'table_key'   => 'guesses',
                'columns'     => ['Guess ID', 'Clue ID', 'Person ID', 'Word'],
                'fields'      => ['id', 'clue_id', 'guesser_person_id', 'guessed_word'],
            ],
            'orphan_guess_persons' => [
                'label'       => 'Guesses Referencing Missing Persons',
                'description' => 'Guesses that reference persons who no longer exist.',
                'action'      => 'repair_delete_orphans',
                'table_key'   => 'guesses',
                'columns'     => ['Guess ID', 'Clue ID', 'Person ID', 'Word'],
                'fields'      => ['id', 'clue_id', 'guesser_person_id', 'guessed_word'],
            ],
            'orphan_round_guesser_rounds' => [
                'label'       => 'Round-Guesser Links to Missing Rounds',
                'description' => 'Round-guesser records referencing rounds that no longer exist.',
                'action'      => 'repair_delete_orphans',
                'table_key'   => 'round_guessers',
                'columns'     => ['Link ID', 'Round ID', 'Person ID'],
                'fields'      => ['id', 'round_id', 'person_id'],
            ],
            'orphan_round_guesser_persons' => [
                'label'       => 'Round-Guesser Links to Missing Persons',
                'description' => 'Round-guesser records referencing persons who no longer exist.',
                'action'      => 'repair_delete_orphans',
                'table_key'   => 'round_guessers',
                'columns'     => ['Link ID', 'Round ID', 'Person ID'],
                'fields'      => ['id', 'round_id', 'person_id'],
            ],
            'orphan_round_solution_rounds' => [
                'label'       => 'Round Solutions Referencing Missing Rounds',
                'description' => 'Round solution records referencing rounds that no longer exist.',
                'action'      => 'repair_delete_orphans',
                'table_key'   => 'round_solutions',
                'columns'     => ['ID', 'Round ID', 'Word'],
                'fields'      => ['id', 'round_id', 'word'],
            ],
            'orphan_round_clue_givers' => [
                'label'       => 'Rounds With Missing Clue Givers',
                'description' => 'Rounds whose clue_giver_id references a person that no longer exists.',
                'action'      => null,
                'columns'     => ['Round ID', 'Date', 'Round #', 'Clue Giver ID'],
                'fields'      => ['id', 'round_date', 'round_number', 'clue_giver_id'],
            ],
            'orphan_puzzle_editors' => [
                'label'       => 'Puzzles With Missing Editor',
                'description' => 'Puzzles whose editor_id references a person that no longer exists.',
                'action'      => null,
                'columns'     => ['Puzzle ID', 'Puzzle Date', 'Editor ID'],
                'fields'      => ['id', 'publication_date', 'editor_id'],
            ],
            'orphan_persons' => [
                'label'       => 'Orphan Persons',
                'description' => 'Persons not referenced by any role (player, constructor, or editor).',
                'action'      => null,
                'columns'     => ['ID', 'Full Name'],
                'fields'      => ['id', 'full_name'],
            ],
        ];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Data Consistency Check', 'kealoa-reference') . '</h1>';

        // Show success message if redirected after repair
        if (isset($_GET['kealoa_repaired'])) {
            $count = (int) $_GET['kealoa_repaired'];
            echo '<div class="notice notice-success is-dismissible"><p>'
                . sprintf(esc_html__('Repair complete: %d record(s) removed.', 'kealoa-reference'), $count)
                . '</p></div>';
        }

        if ($total_issues === 0) {
            echo '<div class="notice notice-success"><p>'
                . esc_html__('No consistency issues found. All records look good!', 'kealoa-reference')
                . '</p></div>';
            echo '</div>';
            return;
        }

        echo '<div class="notice notice-warning"><p>'
            . sprintf(
                esc_html__('Found %d issue(s) across %d check(s).', 'kealoa-reference'),
                $total_issues,
                count($issues)
            )
            . '</p></div>';

        foreach ($issues as $check_key => $rows) {
            $meta = $check_meta[$check_key] ?? null;
            if (!$meta) {
                continue;
            }

            echo '<div class="kealoa-data-check-section" style="margin-top:20px;">';
            echo '<h2>' . esc_html($meta['label']) . ' (' . count($rows) . ')</h2>';
            echo '<p class="description">' . esc_html($meta['description']) . '</p>';

            // Results table
            echo '<table class="widefat striped" style="max-width:800px;">';
            echo '<thead><tr>';
            echo '<th style="width:30px;"><input type="checkbox" class="kealoa-check-all" data-group="' . esc_attr($check_key) . '"></th>';
            foreach ($meta['columns'] as $col) {
                echo '<th>' . esc_html($col) . '</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($rows as $row) {
                $row_id = $row->{$meta['fields'][0]};
                echo '<tr>';
                echo '<td><input type="checkbox" name="ids[]" value="' . esc_attr($row_id) . '" class="kealoa-check-item" data-group="' . esc_attr($check_key) . '"></td>';
                foreach ($meta['fields'] as $field) {
                    echo '<td>' . esc_html($row->$field ?? '') . '</td>';
                }
                echo '</tr>';
            }

            echo '</tbody></table>';

            // Repair button (wrapped in its own form)
            if ($meta['action']) {
                echo '<form method="post" style="margin-top:8px;">';
                wp_nonce_field('kealoa_admin_action', 'kealoa_nonce');
                echo '<input type="hidden" name="kealoa_action" value="' . esc_attr($meta['action']) . '">';
                echo '<input type="hidden" name="check_key" value="' . esc_attr($check_key) . '">';
                if (isset($meta['table_key'])) {
                    echo '<input type="hidden" name="table_key" value="' . esc_attr($meta['table_key']) . '">';
                }
                // Hidden field populated by JS with selected IDs
                echo '<input type="hidden" name="selected_ids" value="" class="kealoa-selected-ids" data-group="' . esc_attr($check_key) . '">';
                echo '<button type="submit" class="button button-secondary kealoa-repair-btn" data-group="' . esc_attr($check_key) . '">'
                    . esc_html__('Delete Selected', 'kealoa-reference')
                    . '</button>';
                echo '</form>';
            }

            echo '</div>';
        }

        // Inline JS for checkbox handling
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            // Check-all toggles
            document.querySelectorAll(".kealoa-check-all").forEach(function(cb) {
                cb.addEventListener("change", function() {
                    var group = this.dataset.group;
                    document.querySelectorAll(".kealoa-check-item[data-group=\"" + group + "\"]").forEach(function(item) {
                        item.checked = cb.checked;
                    });
                });
            });

            // On form submit, gather selected IDs into hidden field
            document.querySelectorAll(".kealoa-repair-btn").forEach(function(btn) {
                btn.closest("form").addEventListener("submit", function(e) {
                    var group = btn.dataset.group;
                    var ids = [];
                    document.querySelectorAll(".kealoa-check-item[data-group=\"" + group + "\"]:checked").forEach(function(cb) {
                        ids.push(cb.value);
                    });
                    if (ids.length === 0) {
                        e.preventDefault();
                        alert("No rows selected.");
                        return;
                    }
                    this.querySelector(".kealoa-selected-ids[data-group=\"" + group + "\"]").value = ids.join(",");
                    if (!confirm("Delete " + ids.length + " selected record(s)? This cannot be undone.")) {
                        e.preventDefault();
                    }
                });
            });
        });
        </script>';

        echo '</div>';
    }

    // =========================================================================
    // DATA CHECK REPAIR HANDLERS
    // =========================================================================

    /**
     * Handle deletion of orphan puzzles (and their puzzle_constructors).
     */
    private function handle_repair_delete_puzzles(): void {
        $ids = $this->parse_selected_ids();
        $deleted = 0;

        foreach ($ids as $id) {
            if ($this->db->delete_puzzle($id)) {
                $deleted++;
            }
        }

        Kealoa_Shortcodes::flush_all_caches();
        wp_redirect(admin_url('admin.php?page=kealoa-data-check&kealoa_repaired=' . $deleted));
        exit;
    }

    /**
     * Handle deletion of problematic rounds (and their child records).
     */
    private function handle_repair_delete_rounds(): void {
        $ids = $this->parse_selected_ids();
        $deleted = 0;

        foreach ($ids as $id) {
            if ($this->db->delete_round($id)) {
                $deleted++;
            }
        }

        Kealoa_Shortcodes::flush_all_caches();
        wp_redirect(admin_url('admin.php?page=kealoa-data-check&kealoa_repaired=' . $deleted));
        exit;
    }

    /**
     * Handle deletion of orphan junction/child records.
     */
    private function handle_repair_delete_orphans(): void {
        $table_key = sanitize_text_field($_POST['table_key'] ?? '');
        $ids = $this->parse_selected_ids();
        $deleted = $this->db->delete_orphan_records($table_key, $ids);

        Kealoa_Shortcodes::flush_all_caches();
        wp_redirect(admin_url('admin.php?page=kealoa-data-check&kealoa_repaired=' . $deleted));
        exit;
    }

    /**
     * Parse comma-separated IDs from the form POST data.
     *
     * @return int[]
     */
    private function parse_selected_ids(): array {
        $raw = sanitize_text_field($_POST['selected_ids'] ?? '');
        if (empty($raw)) {
            return [];
        }
        return array_map('intval', explode(',', $raw));
    }

    // =========================================================================
    // SETTINGS
    // =========================================================================

    /**
     * Render the Settings page.
     */
    public function render_settings_page(): void {
        $debug_mode = get_option('kealoa_debug_mode', false);
        $saved = isset($_GET['kealoa_saved']);
        ?>
        <div class="wrap kealoa-admin-wrap">
            <h1><?php esc_html_e('KEALOA Settings', 'kealoa-reference'); ?></h1>

            <?php if ($saved): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Settings saved.', 'kealoa-reference'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="kealoa_action" value="save_settings">
                <?php wp_nonce_field('kealoa_admin_action', 'kealoa_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="kealoa_debug_mode">
                                    <?php esc_html_e('Debug Mode', 'kealoa-reference'); ?>
                                </label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" id="kealoa_debug_mode" name="debug_mode" value="1"
                                        <?php checked($debug_mode); ?>>
                                    <?php esc_html_e('Enable debug mode', 'kealoa-reference'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('When enabled, additional diagnostic information may be output for troubleshooting.', 'kealoa-reference'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Save Settings', 'kealoa-reference')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle saving settings.
     */
    private function handle_save_settings(): void {
        $debug_mode = !empty($_POST['debug_mode']);
        update_option('kealoa_debug_mode', $debug_mode);

        Kealoa_Shortcodes::flush_all_caches();
        wp_redirect(admin_url('admin.php?page=kealoa-settings&kealoa_saved=1'));
        exit;
    }
}
