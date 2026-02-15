<?php
/**
 * Plugin Activator
 *
 * Creates database tables and initializes plugin settings on activation.
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
 * Handles plugin activation tasks including database table creation.
 */
class Kealoa_Activator {

    /**
     * Activate the plugin
     *
     * Creates all necessary database tables and sets default options.
     */
    public static function activate(): void {
        self::create_tables();
        self::set_default_options();
        
        // Store the database version
        update_option('kealoa_db_version', KEALOA_DB_VERSION);
    }

    /**
     * Create database tables
     *
     * Uses dbDelta for safe table creation and updates.
     */
    private static function create_tables(): void {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table names with WordPress prefix
        $constructors_table = $wpdb->prefix . 'kealoa_constructors';
        $persons_table = $wpdb->prefix . 'kealoa_persons';
        $puzzles_table = $wpdb->prefix . 'kealoa_puzzles';
        $puzzle_constructors_table = $wpdb->prefix . 'kealoa_puzzle_constructors';
        $rounds_table = $wpdb->prefix . 'kealoa_rounds';
        $round_solutions_table = $wpdb->prefix . 'kealoa_round_solutions';
        $round_guessers_table = $wpdb->prefix . 'kealoa_round_guessers';
        $clues_table = $wpdb->prefix . 'kealoa_clues';
        $guesses_table = $wpdb->prefix . 'kealoa_guesses';
        
        // SQL for constructors table (crossword puzzle constructors with XWordInfo data)
        $sql_constructors = "CREATE TABLE {$constructors_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            full_name varchar(255) NOT NULL,
            xwordinfo_profile_name varchar(255) DEFAULT NULL,
            xwordinfo_image_url varchar(500) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_full_name (full_name),
            KEY idx_xwordinfo_profile (xwordinfo_profile_name)
        ) {$charset_collate};";
        
        // SQL for persons table (people like clue givers, guessers)
        $sql_persons = "CREATE TABLE {$persons_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            full_name varchar(255) NOT NULL,
            home_page_url varchar(500) DEFAULT NULL,
            image_url varchar(500) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_full_name (full_name)
        ) {$charset_collate};";
        
        // SQL for puzzles table
        $sql_puzzles = "CREATE TABLE {$puzzles_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            publication_date date NOT NULL,
            editor_name varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_publication_date (publication_date)
        ) {$charset_collate};";
        
        // SQL for puzzle constructors junction table (references constructors table)
        $sql_puzzle_constructors = "CREATE TABLE {$puzzle_constructors_table} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            puzzle_id bigint(20) UNSIGNED NOT NULL,
            constructor_id bigint(20) UNSIGNED NOT NULL,
            constructor_order tinyint(3) UNSIGNED DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_puzzle_constructor (puzzle_id, constructor_id),
            KEY idx_constructor_id (constructor_id)
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
        
        dbDelta($sql_constructors);
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
