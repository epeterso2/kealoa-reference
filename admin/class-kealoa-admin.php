<?php
/**
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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'handle_form_submissions']);
        
        // AJAX handlers
        add_action('wp_ajax_kealoa_get_persons', [$this, 'ajax_get_persons']);
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
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets(string $hook): void {
        if (strpos($hook, 'kealoa') === false) {
            return;
        }
        
        wp_enqueue_style(
            'kealoa-admin',
            KEALOA_PLUGIN_URL . 'assets/css/kealoa-admin.css',
            [],
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
            default => null,
        };
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
                    <th><?php esc_html_e('XWordInfo Profile', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Home Page', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Actions', 'kealoa-reference'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($persons)): ?>
                    <tr>
                        <td colspan="5"><?php esc_html_e('No persons found.', 'kealoa-reference'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($persons as $person): ?>
                        <tr>
                            <td><?php echo esc_html($person->id); ?></td>
                            <td><?php echo esc_html($person->full_name); ?></td>
                            <td>
                                <?php if ($person->xwordinfo_profile_name): ?>
                                    <?php echo Kealoa_Formatter::format_xwordinfo_link($person->xwordinfo_profile_name); ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
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
                                <a href="<?php echo esc_url(home_url('/kealoa/person/' . $person->id . '/')); ?>" target="_blank">
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
                    </td>
                </tr>
                <tr>
                    <th><label for="xwordinfo_profile_name"><?php esc_html_e('XWordInfo Profile Name', 'kealoa-reference'); ?></label></th>
                    <td>
                        <input type="text" name="xwordinfo_profile_name" id="xwordinfo_profile_name" class="regular-text"
                               value="<?php echo esc_attr($person->xwordinfo_profile_name ?? ''); ?>" />
                        <p class="description">
                            <?php esc_html_e('The name as it appears in XWordInfo URLs (e.g., "John_Smith" for xwordinfo.com/Author/John_Smith)', 'kealoa-reference'); ?>
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
                    <th><label for="xwordinfo_image_url"><?php esc_html_e('XWordInfo Image URL', 'kealoa-reference'); ?></label></th>
                    <td>
                        <input type="url" name="xwordinfo_image_url" id="xwordinfo_image_url" class="regular-text"
                               value="<?php echo esc_attr($person->xwordinfo_image_url ?? ''); ?>" />
                        <p class="description">
                            <?php esc_html_e('Direct URL to the person\'s image from XWordInfo', 'kealoa-reference'); ?>
                        </p>
                        <?php if (!empty($person->xwordinfo_image_url)): ?>
                            <p><img src="<?php echo esc_url($person->xwordinfo_image_url); ?>" alt="" style="max-width: 150px; margin-top: 10px;" /></p>
                        <?php endif; ?>
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
        $paged = max(1, (int) ($_GET['paged'] ?? 1));
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;
        
        $puzzles = $this->db->get_puzzles([
            'limit' => $per_page,
            'offset' => $offset,
        ]);
        
        $total = $this->db->count_puzzles();
        $total_pages = ceil($total / $per_page);
        ?>
        <h1 class="wp-heading-inline"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></h1>
        <a href="<?php echo esc_url(admin_url('admin.php?page=kealoa-puzzles&action=add')); ?>" class="page-title-action">
            <?php esc_html_e('Add New', 'kealoa-reference'); ?>
        </a>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Publication Date', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Day of Week', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Constructors', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Actions', 'kealoa-reference'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($puzzles)): ?>
                    <tr>
                        <td colspan="5"><?php esc_html_e('No puzzles found.', 'kealoa-reference'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($puzzles as $puzzle): ?>
                        <?php $constructors = $this->db->get_puzzle_constructors((int) $puzzle->id); ?>
                        <tr>
                            <td><?php echo esc_html($puzzle->id); ?></td>
                            <td><?php echo esc_html(Kealoa_Formatter::format_date($puzzle->publication_date)); ?></td>
                            <td><?php echo esc_html(date('l', strtotime($puzzle->publication_date))); ?></td>
                            <td>
                                <?php if (!empty($constructors)): ?>
                                    <?php 
                                    $names = array_map(fn($c) => $c->full_name, $constructors);
                                    echo esc_html(Kealoa_Formatter::format_list_with_and($names));
                                    ?>
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
        $constructors = $is_edit ? $this->db->get_puzzle_constructors($id) : [];
        $constructor_ids = array_map(fn($c) => $c->id, $constructors);
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
        
        <form method="post" class="kealoa-form">
            <?php wp_nonce_field('kealoa_admin_action', 'kealoa_nonce'); ?>
            <input type="hidden" name="kealoa_action" value="<?php echo $is_edit ? 'update_puzzle' : 'create_puzzle'; ?>" />
            <?php if ($is_edit): ?>
                <input type="hidden" name="puzzle_id" value="<?php echo esc_attr($id); ?>" />
            <?php endif; ?>
            
            <table class="form-table">
                <tr>
                    <th><label for="publication_date"><?php esc_html_e('Publication Date', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <input type="date" name="publication_date" id="publication_date" required
                               value="<?php echo esc_attr($puzzle->publication_date ?? ''); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="constructors"><?php esc_html_e('Constructors', 'kealoa-reference'); ?></label></th>
                    <td>
                        <select name="constructors[]" id="constructors" multiple class="kealoa-multi-select" style="width: 100%; min-height: 150px;">
                            <?php foreach ($all_persons as $person): ?>
                                <option value="<?php echo esc_attr($person->id); ?>"
                                    <?php echo in_array($person->id, $constructor_ids) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($person->full_name); ?>
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
        $paged = max(1, (int) ($_GET['paged'] ?? 1));
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;
        
        $rounds = $this->db->get_rounds([
            'limit' => $per_page,
            'offset' => $offset,
        ]);
        
        $total = $this->db->count_rounds();
        $total_pages = ceil($total / $per_page);
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
                    <th><?php esc_html_e('Episode', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Clue Giver', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Clues', 'kealoa-reference'); ?></th>
                    <th><?php esc_html_e('Actions', 'kealoa-reference'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rounds)): ?>
                    <tr>
                        <td colspan="7"><?php esc_html_e('No rounds found.', 'kealoa-reference'); ?></td>
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
                            <td><?php echo Kealoa_Formatter::format_episode_link((int) $round->episode_number, (int) $round->episode_start_seconds); ?></td>
                            <td><?php echo esc_html(Kealoa_Formatter::format_solution_words($solutions)); ?></td>
                            <td><?php echo esc_html($round->clue_giver_name ?? '—'); ?></td>
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
                    <th><label for="episode_number"><?php esc_html_e('Episode Number', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <input type="number" name="episode_number" id="episode_number" min="1" required
                               value="<?php echo esc_attr($round->episode_number ?? ''); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="episode_start_seconds"><?php esc_html_e('Start Time (seconds)', 'kealoa-reference'); ?></label></th>
                    <td>
                        <input type="number" name="episode_start_seconds" id="episode_start_seconds" min="0"
                               value="<?php echo esc_attr($round->episode_start_seconds ?? 0); ?>" />
                        <p class="description">
                            <?php esc_html_e('Number of seconds after the start of the episode where the KEALOA round begins.', 'kealoa-reference'); ?>
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
                    <th><label for="guessers"><?php esc_html_e('Guessers', 'kealoa-reference'); ?></label></th>
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
                            <?php esc_html_e('Hold Ctrl/Cmd to select multiple guessers.', 'kealoa-reference'); ?>
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
                            <?php esc_html_e('A brief text description of the round.', 'kealoa-reference'); ?>
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
        $all_persons = $this->db->get_persons(['limit' => 1000]);
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
                <strong><?php esc_html_e('Guessers:', 'kealoa-reference'); ?></strong>
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
                            <td><?php echo esc_html(Kealoa_Formatter::format_date($clue->puzzle_date)); ?></td>
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
                    <th><label for="clue_number"><?php esc_html_e('Clue Number', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <input type="number" name="clue_number" id="clue_number" min="1" required
                               value="<?php echo count($clues) + 1; ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="puzzle_date"><?php esc_html_e('NYT Puzzle Date', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <input type="date" name="puzzle_date" id="puzzle_date" class="regular-text" required />
                        <p class="description">
                            <?php esc_html_e('If a puzzle with this date already exists, it will be used automatically.', 'kealoa-reference'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="puzzle_constructors"><?php esc_html_e('Constructors', 'kealoa-reference'); ?></label></th>
                    <td>
                        <select name="puzzle_constructors[]" id="puzzle_constructors" multiple class="kealoa-multi-select" style="width: 100%; min-height: 120px;">
                            <?php foreach ($all_persons as $person): ?>
                                <option value="<?php echo esc_attr($person->id); ?>">
                                    <?php echo esc_html($person->full_name); ?>
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
                    <th><label for="puzzle_clue_number"><?php esc_html_e('Puzzle Clue Number', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <input type="number" name="puzzle_clue_number" id="puzzle_clue_number" min="1" required />
                    </td>
                </tr>
                <tr>
                    <th><label for="puzzle_clue_direction"><?php esc_html_e('Direction', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <select name="puzzle_clue_direction" id="puzzle_clue_direction" required>
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
        $all_persons = $this->db->get_persons(['limit' => 1000]);
        $clue_guesses = $this->db->get_clue_guesses($clue_id);
        
        // Get the puzzle for this clue
        $puzzle = $this->db->get_puzzle((int) $clue->puzzle_id);
        $puzzle_constructors = $puzzle ? $this->db->get_puzzle_constructors((int) $puzzle->id) : [];
        $puzzle_constructor_ids = array_map(fn($c) => $c->id, $puzzle_constructors);
        
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
                    <th><label for="clue_number"><?php esc_html_e('Clue Number', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <input type="number" name="clue_number" id="clue_number" min="1" required
                               value="<?php echo esc_attr($clue->clue_number); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="puzzle_date"><?php esc_html_e('NYT Puzzle Date', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <input type="date" name="puzzle_date" id="puzzle_date" class="regular-text" required
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
                            <?php foreach ($all_persons as $person): ?>
                                <option value="<?php echo esc_attr($person->id); ?>"
                                    <?php echo in_array($person->id, $puzzle_constructor_ids) ? 'selected' : ''; ?>>
                                    <?php echo esc_html($person->full_name); ?>
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
                    <th><label for="puzzle_clue_number"><?php esc_html_e('Puzzle Clue Number', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <input type="number" name="puzzle_clue_number" id="puzzle_clue_number" min="1" required
                               value="<?php echo esc_attr($clue->puzzle_clue_number); ?>" />
                    </td>
                </tr>
                <tr>
                    <th><label for="puzzle_clue_direction"><?php esc_html_e('Direction', 'kealoa-reference'); ?> *</label></th>
                    <td>
                        <select name="puzzle_clue_direction" id="puzzle_clue_direction" required>
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
        $id = $this->db->create_person([
            'full_name' => $_POST['full_name'] ?? '',
            'xwordinfo_profile_name' => $_POST['xwordinfo_profile_name'] ?? null,
            'home_page_url' => $_POST['home_page_url'] ?? null,
            'xwordinfo_image_url' => $_POST['xwordinfo_image_url'] ?? null,
        ]);
        
        if ($id) {
            $this->redirect_with_message('kealoa-persons', 'Person created successfully.');
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
            'xwordinfo_profile_name' => $_POST['xwordinfo_profile_name'] ?? null,
            'home_page_url' => $_POST['home_page_url'] ?? null,
            'xwordinfo_image_url' => $_POST['xwordinfo_image_url'] ?? null,
        ]);
        
        if ($result) {
            $this->redirect_with_message('kealoa-persons', 'Person updated successfully.');
        } else {
            $this->redirect_with_message('kealoa-persons', 'Failed to update person.', 'error');
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
        ]);
        
        if ($id) {
            $constructors = $_POST['constructors'] ?? [];
            if (!empty($constructors)) {
                $this->db->set_puzzle_constructors($id, array_map('intval', $constructors));
            }
            $this->redirect_with_message('kealoa-puzzles', 'Puzzle created successfully.');
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
        ]);
        
        $constructors = $_POST['constructors'] ?? [];
        $this->db->set_puzzle_constructors($id, array_map('intval', $constructors));
        
        if ($result) {
            $this->redirect_with_message('kealoa-puzzles', 'Puzzle updated successfully.');
        } else {
            $this->redirect_with_message('kealoa-puzzles', 'Failed to update puzzle.', 'error');
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
            'episode_number' => $_POST['episode_number'] ?? 0,
            'episode_start_seconds' => $_POST['episode_start_seconds'] ?? 0,
            'clue_giver_id' => $_POST['clue_giver_id'] ?? 0,
            'description' => $_POST['description'] ?? null,
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
            
            $this->redirect_with_message('kealoa-rounds', 'Round created successfully.');
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
            'episode_number' => $_POST['episode_number'] ?? 0,
            'episode_start_seconds' => $_POST['episode_start_seconds'] ?? 0,
            'clue_giver_id' => $_POST['clue_giver_id'] ?? 0,
            'description' => $_POST['description'] ?? null,
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
            $this->redirect_with_message('kealoa-rounds', 'Round updated successfully.');
        } else {
            $this->redirect_with_message('kealoa-rounds', 'Failed to update round.', 'error');
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
        
        if (!$puzzle_id) {
            wp_redirect(admin_url('admin.php?page=kealoa-rounds&action=clues&id=' . $round_id . '&error=no_puzzle'));
            exit;
        }
        
        $clue_id = $this->db->create_clue([
            'round_id' => $round_id,
            'clue_number' => $_POST['clue_number'] ?? 1,
            'puzzle_id' => $puzzle_id,
            'puzzle_clue_number' => $_POST['puzzle_clue_number'] ?? 0,
            'puzzle_clue_direction' => $_POST['puzzle_clue_direction'] ?? 'A',
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
        
        if (!$puzzle_id) {
            wp_redirect(admin_url('admin.php?page=kealoa-rounds&action=edit_clue&id=' . $round_id . '&clue_id=' . $clue_id . '&error=no_puzzle'));
            exit;
        }
        
        $this->db->update_clue($clue_id, [
            'clue_number' => $_POST['clue_number'] ?? 1,
            'puzzle_id' => $puzzle_id,
            'puzzle_clue_number' => $_POST['puzzle_clue_number'] ?? 0,
            'puzzle_clue_direction' => $_POST['puzzle_clue_direction'] ?? 'A',
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
    private function redirect_with_message(string $page, string $message, string $type = 'success'): void {
        set_transient('kealoa_admin_message', [
            'message' => $message,
            'type' => $type,
        ], 30);
        
        wp_redirect(admin_url('admin.php?page=' . $page));
        exit;
    }
}
