<?php
/**
 * @copyright 2026 Eric Peterson (eric@puzzlehead.org)
 * @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
 *
 * Plugin Activator
 *
 * Creates database tables and initializes plugin settings on activation.
 * Handles schema migration from v1.x (separate constructors/editors) to
 * v2.0 (unified persons table).
 *
 * @package KEALOA_Reference
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Kealoa_Activator
 *
 * Handles plugin activation tasks including database table creation
 * and data migration for major version upgrades.
 */
class Kealoa_Activator {

    /**
     * Activate the plugin
     *
     * Creates all necessary database tables, runs any pending migrations,
     * and sets default options.
     */
    public static function activate(): void {
        $installed_version = get_option('kealoa_db_version', '0');

        // Run v1→v2 migration before creating target schema
        if (version_compare($installed_version, '2.0.0', '<') && version_compare($installed_version, '0', '>')) {
            self::migrate_from_v1();
        }

        self::create_tables();
        self::set_default_options();

        // Store the database version
        update_option('kealoa_db_version', KEALOA_DB_VERSION);
    }

    /**
     * Create database tables
     *
     * Uses dbDelta for safe table creation and updates.
     * Defines the v2.0 target schema (unified persons table,
     * no separate constructors table).
     */
    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Table names with WordPress prefix
        $persons_table = $wpdb->prefix . 'kealoa_persons';
        $puzzles_table = $wpdb->prefix . 'kealoa_puzzles';
        $puzzle_constructors_table = $wpdb->prefix . 'kealoa_puzzle_constructors';
        $rounds_table = $wpdb->prefix . 'kealoa_rounds';
        $round_solutions_table = $wpdb->prefix . 'kealoa_round_solutions';
        $round_guessers_table = $wpdb->prefix . 'kealoa_round_guessers';
        $clues_table = $wpdb->prefix . 'kealoa_clues';
        $guesses_table = $wpdb->prefix . 'kealoa_guesses';

        // SQL for persons table (unified: players, constructors, editors)
        $sql_persons = "CREATE TABLE {$persons_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            full_name varchar(255) NOT NULL,
            nicknames varchar(500) DEFAULT NULL,
            home_page_url varchar(500) DEFAULT NULL,
            image_url varchar(500) DEFAULT NULL,
            media_id bigint(20) UNSIGNED DEFAULT NULL,
            hide_xwordinfo tinyint(1) NOT NULL DEFAULT 0,
            xwordinfo_profile_name varchar(255) DEFAULT NULL,
            xwordinfo_image_url varchar(500) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_full_name (full_name),
            KEY idx_xwordinfo_profile (xwordinfo_profile_name)
        ) {$charset_collate};";

        // SQL for puzzles table (editor_id FK to persons)
        $sql_puzzles = "CREATE TABLE {$puzzles_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            publication_date date NOT NULL,
            editor_id bigint(20) UNSIGNED DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_publication_date (publication_date),
            KEY idx_editor_id (editor_id)
        ) {$charset_collate};";

        // SQL for puzzle constructors junction table (references persons table)
        $sql_puzzle_constructors = "CREATE TABLE {$puzzle_constructors_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            puzzle_id bigint(20) UNSIGNED NOT NULL,
            person_id bigint(20) UNSIGNED NOT NULL,
            constructor_order tinyint(3) UNSIGNED DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_puzzle_person (puzzle_id, person_id),
            KEY idx_person_id (person_id)
        ) {$charset_collate};";

        // SQL for rounds table
        $sql_rounds = "CREATE TABLE {$rounds_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            round_date date NOT NULL,
            round_number tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
            episode_number int(10) UNSIGNED NOT NULL,
            episode_id int(10) UNSIGNED DEFAULT NULL,
            episode_url varchar(500) DEFAULT NULL,
            episode_start_seconds int(10) UNSIGNED DEFAULT 0,
            clue_giver_id bigint(20) UNSIGNED NOT NULL,
            description text DEFAULT NULL,
            description2 text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_round_date_number (round_date, round_number),
            KEY idx_episode_number (episode_number),
            KEY idx_episode_id (episode_id),
            KEY idx_clue_giver (clue_giver_id)
        ) {$charset_collate};";

        // SQL for round solutions table (words in the solution set)
        $sql_round_solutions = "CREATE TABLE {$round_solutions_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            round_id bigint(20) UNSIGNED NOT NULL,
            word varchar(100) NOT NULL,
            word_order tinyint(3) UNSIGNED DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_round_id (round_id),
            KEY idx_word (word)
        ) {$charset_collate};";

        // SQL for round guessers junction table
        $sql_round_guessers = "CREATE TABLE {$round_guessers_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            round_id bigint(20) UNSIGNED NOT NULL,
            person_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_round_guesser (round_id, person_id),
            KEY idx_person_id (person_id)
        ) {$charset_collate};";

        // SQL for clues table
        $sql_clues = "CREATE TABLE {$clues_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            round_id bigint(20) UNSIGNED NOT NULL,
            clue_number tinyint(3) UNSIGNED NOT NULL,
            puzzle_id bigint(20) UNSIGNED DEFAULT NULL,
            puzzle_clue_number smallint(5) UNSIGNED DEFAULT NULL,
            puzzle_clue_direction enum('A','D') DEFAULT NULL,
            clue_text text NOT NULL,
            correct_answer varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_round_id (round_id),
            KEY idx_puzzle_id (puzzle_id),
            KEY idx_clue_number (clue_number)
        ) {$charset_collate};";

        // SQL for guesses table
        $sql_guesses = "CREATE TABLE {$guesses_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            clue_id bigint(20) UNSIGNED NOT NULL,
            guesser_person_id bigint(20) UNSIGNED NOT NULL,
            guessed_word varchar(100) NOT NULL,
            is_correct tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_clue_guesser (clue_id, guesser_person_id),
            KEY idx_guesser_person_id (guesser_person_id),
            KEY idx_is_correct (is_correct)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql_persons);
        dbDelta($sql_puzzles);
        dbDelta($sql_puzzle_constructors);
        dbDelta($sql_rounds);
        dbDelta($sql_round_solutions);
        dbDelta($sql_round_guessers);
        dbDelta($sql_clues);
        dbDelta($sql_guesses);
    }

    /**
     * Migrate data from v1.x schema to v2.0 unified persons schema.
     *
     * Steps:
     * 1. Add new columns to persons and puzzles tables
     * 2. Merge constructors into persons (match by full_name)
     * 3. Update puzzle_constructors to reference persons
     * 4. Populate puzzles.editor_id from editor_name → persons
     * 5. Migrate editor media from wp_options to persons.media_id
     * 6. Drop old columns and constructors table
     */
    private static function migrate_from_v1(): void {
        global $wpdb;

        $persons_table = $wpdb->prefix . 'kealoa_persons';
        $constructors_table = $wpdb->prefix . 'kealoa_constructors';
        $puzzles_table = $wpdb->prefix . 'kealoa_puzzles';
        $puzzle_constructors_table = $wpdb->prefix . 'kealoa_puzzle_constructors';

        // --- Step 1: Add new columns ---
        // Add xwordinfo columns to persons if missing
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$persons_table}", 0);
        if (!in_array('xwordinfo_profile_name', $cols, true)) {
            $wpdb->query("ALTER TABLE {$persons_table} ADD COLUMN xwordinfo_profile_name varchar(255) DEFAULT NULL AFTER hide_xwordinfo");
        }
        if (!in_array('xwordinfo_image_url', $cols, true)) {
            $wpdb->query("ALTER TABLE {$persons_table} ADD COLUMN xwordinfo_image_url varchar(500) DEFAULT NULL AFTER xwordinfo_profile_name");
        }

        // Add editor_id to puzzles if missing
        $puzzle_cols = $wpdb->get_col("SHOW COLUMNS FROM {$puzzles_table}", 0);
        if (!in_array('editor_id', $puzzle_cols, true)) {
            $wpdb->query("ALTER TABLE {$puzzles_table} ADD COLUMN editor_id bigint(20) UNSIGNED DEFAULT NULL AFTER publication_date");
        }

        // Add person_id to puzzle_constructors if missing
        $pc_cols = $wpdb->get_col("SHOW COLUMNS FROM {$puzzle_constructors_table}", 0);
        if (!in_array('person_id', $pc_cols, true)) {
            $wpdb->query("ALTER TABLE {$puzzle_constructors_table} ADD COLUMN person_id bigint(20) UNSIGNED DEFAULT NULL AFTER puzzle_id");
        }

        // --- Step 2: Merge constructors into persons ---
        $constructors_exists = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s", DB_NAME, $constructors_table)
        );

        $constructor_to_person = [];
        if ($constructors_exists) {
            $constructors = $wpdb->get_results("SELECT * FROM {$constructors_table}");
            foreach ($constructors as $con) {
                // Try to find matching person by name (case-insensitive)
                $person = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, media_id FROM {$persons_table} WHERE LOWER(full_name) = LOWER(%s)",
                    $con->full_name
                ));

                if ($person) {
                    // Update existing person with constructor fields
                    $update_data = [];
                    $update_format = [];
                    if (!empty($con->xwordinfo_profile_name)) {
                        $update_data['xwordinfo_profile_name'] = $con->xwordinfo_profile_name;
                        $update_format[] = '%s';
                    }
                    if (!empty($con->xwordinfo_image_url)) {
                        $update_data['xwordinfo_image_url'] = $con->xwordinfo_image_url;
                        $update_format[] = '%s';
                    }
                    if (empty($person->media_id) && !empty($con->media_id)) {
                        $update_data['media_id'] = (int) $con->media_id;
                        $update_format[] = '%d';
                    }
                    if (!empty($update_data)) {
                        $wpdb->update($persons_table, $update_data, ['id' => (int) $person->id], $update_format, ['%d']);
                    }
                    $constructor_to_person[(int) $con->id] = (int) $person->id;
                } else {
                    // Create new person from constructor
                    $wpdb->insert($persons_table, [
                        'full_name'              => $con->full_name,
                        'xwordinfo_profile_name' => $con->xwordinfo_profile_name,
                        'xwordinfo_image_url'    => $con->xwordinfo_image_url,
                        'media_id'               => $con->media_id ? (int) $con->media_id : null,
                    ], ['%s', '%s', '%s', '%d']);
                    $constructor_to_person[(int) $con->id] = (int) $wpdb->insert_id;
                }
            }
        }

        // --- Step 3: Update puzzle_constructors to reference persons ---
        if (in_array('constructor_id', $pc_cols, true)) {
            foreach ($constructor_to_person as $old_id => $new_id) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$puzzle_constructors_table} SET person_id = %d WHERE constructor_id = %d",
                    $new_id,
                    $old_id
                ));
            }
            // Drop old indexes, column, and add new indexes
            $indexes = $wpdb->get_results("SHOW INDEX FROM {$puzzle_constructors_table}", ARRAY_A);
            $index_names = array_unique(array_column($indexes, 'Key_name'));
            if (in_array('idx_puzzle_constructor', $index_names, true)) {
                $wpdb->query("ALTER TABLE {$puzzle_constructors_table} DROP INDEX idx_puzzle_constructor");
            }
            if (in_array('idx_constructor_id', $index_names, true)) {
                $wpdb->query("ALTER TABLE {$puzzle_constructors_table} DROP INDEX idx_constructor_id");
            }
            $wpdb->query("ALTER TABLE {$puzzle_constructors_table} DROP COLUMN constructor_id");
        }

        // --- Step 4: Populate puzzles.editor_id ---
        if (in_array('editor_name', $puzzle_cols, true)) {
            $editor_names = $wpdb->get_col(
                "SELECT DISTINCT editor_name FROM {$puzzles_table} WHERE editor_name IS NOT NULL AND editor_name != ''"
            );
            foreach ($editor_names as $name) {
                $person = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$persons_table} WHERE LOWER(full_name) = LOWER(%s)",
                    $name
                ));
                if (!$person) {
                    $wpdb->insert($persons_table, ['full_name' => $name], ['%s']);
                    $person_id = (int) $wpdb->insert_id;
                } else {
                    $person_id = (int) $person->id;
                }
                $wpdb->query($wpdb->prepare(
                    "UPDATE {$puzzles_table} SET editor_id = %d WHERE editor_name = %s",
                    $person_id,
                    $name
                ));
            }

            // --- Step 5: Migrate editor media from wp_options ---
            $editor_media_options = $wpdb->get_results(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'kealoa_editor_media_%'"
            );
            foreach ($editor_media_options as $opt) {
                $editor_slug = str_replace('kealoa_editor_media_', '', $opt->option_name);
                $media_id = (int) $opt->option_value;
                if ($media_id > 0) {
                    foreach ($editor_names as $name) {
                        if (sanitize_title($name) === $editor_slug) {
                            $person = $wpdb->get_row($wpdb->prepare(
                                "SELECT id, media_id FROM {$persons_table} WHERE LOWER(full_name) = LOWER(%s)",
                                $name
                            ));
                            if ($person && empty($person->media_id)) {
                                $wpdb->update($persons_table, ['media_id' => $media_id], ['id' => (int) $person->id], ['%d'], ['%d']);
                            }
                            break;
                        }
                    }
                }
                delete_option($opt->option_name);
            }

            // Drop editor_name column
            $wpdb->query("ALTER TABLE {$puzzles_table} DROP COLUMN editor_name");
        }

        // --- Step 6: Drop constructors table ---
        if ($constructors_exists) {
            $wpdb->query("DROP TABLE IF EXISTS {$constructors_table}");
        }
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options(): void {
        $defaults = [
            'kealoa_items_per_page' => 20,
            'kealoa_date_format' => 'n/j/Y',
        ];

        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
}
