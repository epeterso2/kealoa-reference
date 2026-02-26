<?php
/**
 * @copyright 2026 Eric Peterson (eric@puzzlehead.org)
 * @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
 *
 * Gutenberg Blocks
 *
 * Registers and handles Gutenberg blocks for KEALOA data display.
 *
 * @package KEALOA_Reference
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Kealoa_Blocks
 *
 * Registers Gutenberg blocks for KEALOA data display.
 */
class Kealoa_Blocks {

    private Kealoa_Shortcodes $shortcodes;

    /**
     * Constructor
     */
    public function __construct() {
        $this->shortcodes = new Kealoa_Shortcodes();

        add_action('init', [$this, 'register_blocks']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_editor_assets']);
    }

    /**
     * Register all blocks
     */
    public function register_blocks(): void {
        // Register blocks directory
        if (file_exists(KEALOA_PLUGIN_DIR . 'blocks/rounds-table/block.json')) {
            register_block_type(KEALOA_PLUGIN_DIR . 'blocks/rounds-table');
        }

        if (file_exists(KEALOA_PLUGIN_DIR . 'blocks/round-view/block.json')) {
            register_block_type(KEALOA_PLUGIN_DIR . 'blocks/round-view');
        }

        if (file_exists(KEALOA_PLUGIN_DIR . 'blocks/person-view/block.json')) {
            register_block_type(KEALOA_PLUGIN_DIR . 'blocks/person-view');
        }

        if (file_exists(KEALOA_PLUGIN_DIR . 'blocks/constructors-table/block.json')) {
            register_block_type(KEALOA_PLUGIN_DIR . 'blocks/constructors-table');
        }

        if (file_exists(KEALOA_PLUGIN_DIR . 'blocks/constructor-view/block.json')) {
            register_block_type(KEALOA_PLUGIN_DIR . 'blocks/constructor-view');
        }

        if (file_exists(KEALOA_PLUGIN_DIR . 'blocks/persons-table/block.json')) {
            register_block_type(KEALOA_PLUGIN_DIR . 'blocks/persons-table');
        }

        if (file_exists(KEALOA_PLUGIN_DIR . 'blocks/editors-table/block.json')) {
            register_block_type(KEALOA_PLUGIN_DIR . 'blocks/editors-table');
        }

        if (file_exists(KEALOA_PLUGIN_DIR . 'blocks/clue-givers-table/block.json')) {
            register_block_type(KEALOA_PLUGIN_DIR . 'blocks/clue-givers-table');
        }

        if (file_exists(KEALOA_PLUGIN_DIR . 'blocks/editor-view/block.json')) {
            register_block_type(KEALOA_PLUGIN_DIR . 'blocks/editor-view');
        }

        if (file_exists(KEALOA_PLUGIN_DIR . 'blocks/version-info/block.json')) {
            register_block_type(KEALOA_PLUGIN_DIR . 'blocks/version-info');
        }

        if (file_exists(KEALOA_PLUGIN_DIR . 'blocks/play-game/block.json')) {
            register_block_type(KEALOA_PLUGIN_DIR . 'blocks/play-game');
        }

        if (file_exists(KEALOA_PLUGIN_DIR . 'blocks/rounds-stats/block.json')) {
            register_block_type(KEALOA_PLUGIN_DIR . 'blocks/rounds-stats');
        }

        if (file_exists(KEALOA_PLUGIN_DIR . 'blocks/puzzles-table/block.json')) {
            register_block_type(KEALOA_PLUGIN_DIR . 'blocks/puzzles-table');
        }

        if (file_exists(KEALOA_PLUGIN_DIR . 'blocks/puzzle-view/block.json')) {
            register_block_type(KEALOA_PLUGIN_DIR . 'blocks/puzzle-view');
        }

        // Fallback registration if block.json files don't exist yet
        if (!file_exists(KEALOA_PLUGIN_DIR . 'blocks/rounds-table/block.json')) {
            register_block_type('kealoa/rounds-table', [
                'render_callback' => [$this, 'render_rounds_table_block'],
                'attributes' => [
                    'limit' => [
                        'type' => 'number',
                        'default' => 0,
                    ],
                    'order' => [
                        'type' => 'string',
                        'default' => 'DESC',
                    ],
                ],
            ]);
        }

        if (!file_exists(KEALOA_PLUGIN_DIR . 'blocks/round-view/block.json')) {
            register_block_type('kealoa/round-view', [
                'render_callback' => [$this, 'render_round_view_block'],
                'attributes' => [
                    'roundId' => [
                        'type' => 'number',
                        'default' => 0,
                    ],
                ],
            ]);
        }

        if (!file_exists(KEALOA_PLUGIN_DIR . 'blocks/person-view/block.json')) {
            register_block_type('kealoa/person-view', [
                'render_callback' => [$this, 'render_person_view_block'],
                'attributes' => [
                    'personId' => [
                        'type' => 'number',
                        'default' => 0,
                    ],
                ],
            ]);
        }
    }

    /**
     * Enqueue editor assets
     */
    public function enqueue_editor_assets(): void {
        wp_enqueue_script(
            'kealoa-blocks-editor',
            KEALOA_PLUGIN_URL . 'assets/js/blocks-editor.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n', 'wp-block-editor', 'wp-server-side-render'],
            KEALOA_VERSION,
            true
        );

        // Get data for the editor
        $db = new Kealoa_DB();
        $rounds = $db->get_rounds(['limit' => 100]);
        $persons = $db->get_persons(['limit' => 100]);

        wp_localize_script('kealoa-blocks-editor', 'kealoaBlocksData', [
            'rounds' => array_map(function($round) {
                return [
                    'id' => (int) $round->id,
                    'date' => Kealoa_Formatter::format_date($round->round_date),
                    'episode' => (int) $round->episode_number,
                ];
            }, $rounds),
            'persons' => array_map(function($person) {
                return [
                    'id' => (int) $person->id,
                    'name' => $person->full_name,
                ];
            }, $persons),
        ]);

        wp_enqueue_style(
            'kealoa-blocks-editor',
            KEALOA_PLUGIN_URL . 'assets/css/blocks-editor.css',
            ['wp-edit-blocks'],
            KEALOA_VERSION
        );
    }

    /**
     * Render rounds table block
     */
    public function render_rounds_table_block(array $attributes): string {
        return $this->shortcodes->render_rounds_table([
            'limit' => $attributes['limit'] ?? 0,
            'order' => $attributes['order'] ?? 'DESC',
        ]);
    }

    /**
     * Render round view block
     */
    public function render_round_view_block(array $attributes): string {
        $round_id = $attributes['roundId'] ?? 0;

        if (!$round_id) {
            return '<p class="kealoa-block-placeholder">' .
                esc_html__('Please select a round from the block settings.', 'kealoa-reference') .
                '</p>';
        }

        return $this->shortcodes->render_round(['id' => $round_id]);
    }

    /**
     * Render person view block
     */
    public function render_person_view_block(array $attributes): string {
        $person_id = $attributes['personId'] ?? 0;

        if (!$person_id) {
            return '<p class="kealoa-block-placeholder">' .
                esc_html__('Please select a person from the block settings.', 'kealoa-reference') .
                '</p>';
        }

        return $this->shortcodes->render_person(['id' => $person_id]);
    }

    /**
     * Render constructors table block
     */
    public function render_constructors_table_block(array $attributes): string {
        return $this->shortcodes->render_constructors_table([]);
    }

    /**
     * Render persons table block
     */
    public function render_persons_table_block(array $attributes): string {
        return $this->shortcodes->render_persons_table([]);
    }

    /**
     * Render constructor view block
     */
    public function render_constructor_view_block(array $attributes): string {
        $constructor_id = $attributes['constructorId'] ?? 0;

        if (!$constructor_id) {
            return '<p class="kealoa-block-placeholder">' .
                esc_html__('Please select a constructor from the block settings.', 'kealoa-reference') .
                '</p>';
        }

        return $this->shortcodes->render_constructor(['id' => $constructor_id]);
    }

    /**
     * Render editors table block
     */
    public function render_editors_table_block(array $attributes): string {
        return $this->shortcodes->render_editors_table([]);
    }

    /**
     * Render editor view block
     */
    public function render_editor_view_block(array $attributes): string {
        $editor_name = $attributes['editorName'] ?? '';

        if (!$editor_name) {
            return '<p class="kealoa-block-placeholder">' .
                esc_html__('Please select an editor from the block settings.', 'kealoa-reference') .
                '</p>';
        }

        return $this->shortcodes->render_editor(['name' => $editor_name]);
    }

    /**
     * Render version info block
     */
    public function render_version_info_block(array $attributes): string {
        return $this->shortcodes->render_version([]);
    }
}
