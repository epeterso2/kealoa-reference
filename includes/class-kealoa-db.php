<?php
/**
 * Database Access Layer
 *
 * Provides CRUD operations and query methods for all KEALOA entities.
 *
 * @package KEALOA_Reference
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Kealoa_DB
 *
 * Handles all database operations for the KEALOA Reference plugin.
 */
class Kealoa_DB {

    private \wpdb $wpdb;
    private string $constructors_table;
    private string $persons_table;
    private string $puzzles_table;
    private string $puzzle_constructors_table;
    private string $rounds_table;
    private string $round_solutions_table;
    private string $round_guessers_table;
    private string $clues_table;
    private string $guesses_table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        $this->constructors_table = $wpdb->prefix . 'kealoa_constructors';
        $this->persons_table = $wpdb->prefix . 'kealoa_persons';
        $this->puzzles_table = $wpdb->prefix . 'kealoa_puzzles';
        $this->puzzle_constructors_table = $wpdb->prefix . 'kealoa_puzzle_constructors';
        $this->rounds_table = $wpdb->prefix . 'kealoa_rounds';
        $this->round_solutions_table = $wpdb->prefix . 'kealoa_round_solutions';
        $this->round_guessers_table = $wpdb->prefix . 'kealoa_round_guessers';
        $this->clues_table = $wpdb->prefix . 'kealoa_clues';
        $this->guesses_table = $wpdb->prefix . 'kealoa_guesses';
    }

    // =========================================================================
    // CONSTRUCTORS CRUD
    // =========================================================================

    /**
     * Get a constructor by ID
     */
    public function get_constructor(int $id): ?object {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->constructors_table} WHERE id = %d",
            $id
        );
        $result = $this->wpdb->get_row($sql);
        return $result ?: null;
    }

    /**
     * Get all constructors
     */
    public function get_constructors(array $args = []): array {
        $defaults = [
            'orderby' => 'full_name',
            'order' => 'ASC',
            'limit' => 100,
            'offset' => 0,
            'search' => '',
        ];
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM {$this->constructors_table}";
        $where = [];
        $values = [];
        
        if (!empty($args['search'])) {
            $where[] = "full_name LIKE %s";
            $values[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $allowed_orderby = ['id', 'full_name', 'created_at'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'full_name';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        
        $sql .= " ORDER BY {$orderby} {$order}";
        $sql .= " LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];
        
        if (!empty($values)) {
            $sql = $this->wpdb->prepare($sql, ...$values);
        }
        
        return $this->wpdb->get_results($sql);
    }

    /**
     * Count total constructors
     */
    public function count_constructors(string $search = ''): int {
        $sql = "SELECT COUNT(*) FROM {$this->constructors_table}";
        
        if (!empty($search)) {
            $sql .= $this->wpdb->prepare(
                " WHERE full_name LIKE %s",
                '%' . $this->wpdb->esc_like($search) . '%'
            );
        }
        
        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Create a constructor
     */
    public function create_constructor(array $data): int|false {
        $result = $this->wpdb->insert(
            $this->constructors_table,
            [
                'full_name' => sanitize_text_field($data['full_name']),
                'xwordinfo_profile_name' => isset($data['xwordinfo_profile_name']) 
                    ? sanitize_text_field($data['xwordinfo_profile_name']) 
                    : null,
                'xwordinfo_image_url' => isset($data['xwordinfo_image_url']) 
                    ? esc_url_raw($data['xwordinfo_image_url']) 
                    : null,
            ],
            ['%s', '%s', '%s']
        );
        
        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update a constructor
     */
    public function update_constructor(int $id, array $data): bool {
        $update_data = [];
        $format = [];
        
        if (isset($data['full_name'])) {
            $update_data['full_name'] = sanitize_text_field($data['full_name']);
            $format[] = '%s';
        }
        if (array_key_exists('xwordinfo_profile_name', $data)) {
            $update_data['xwordinfo_profile_name'] = $data['xwordinfo_profile_name'] 
                ? sanitize_text_field($data['xwordinfo_profile_name']) 
                : null;
            $format[] = '%s';
        }
        if (array_key_exists('xwordinfo_image_url', $data)) {
            $update_data['xwordinfo_image_url'] = $data['xwordinfo_image_url'] 
                ? esc_url_raw($data['xwordinfo_image_url']) 
                : null;
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $this->wpdb->update(
            $this->constructors_table,
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Delete a constructor
     */
    public function delete_constructor(int $id): bool {
        $result = $this->wpdb->delete(
            $this->constructors_table,
            ['id' => $id],
            ['%d']
        );
        
        return $result !== false;
    }

    // =========================================================================
    // PERSONS CRUD
    // =========================================================================

    /**
     * Get a person by ID
     */
    public function get_person(int $id): ?object {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->persons_table} WHERE id = %d",
            $id
        );
        $result = $this->wpdb->get_row($sql);
        return $result ?: null;
    }

    /**
     * Get all persons
     */
    public function get_persons(array $args = []): array {
        $defaults = [
            'orderby' => 'full_name',
            'order' => 'ASC',
            'limit' => 100,
            'offset' => 0,
            'search' => '',
        ];
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM {$this->persons_table}";
        $where = [];
        $values = [];
        
        if (!empty($args['search'])) {
            $where[] = "full_name LIKE %s";
            $values[] = '%' . $this->wpdb->esc_like($args['search']) . '%';
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        $allowed_orderby = ['id', 'full_name', 'created_at'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'full_name';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
        
        $sql .= " ORDER BY {$orderby} {$order}";
        $sql .= " LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];
        
        if (!empty($values)) {
            $sql = $this->wpdb->prepare($sql, ...$values);
        }
        
        return $this->wpdb->get_results($sql);
    }

    /**
     * Count total persons
     */
    public function count_persons(string $search = ''): int {
        $sql = "SELECT COUNT(*) FROM {$this->persons_table}";
        
        if (!empty($search)) {
            $sql .= $this->wpdb->prepare(
                " WHERE full_name LIKE %s",
                '%' . $this->wpdb->esc_like($search) . '%'
            );
        }
        
        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Create a person
     */
    public function create_person(array $data): int|false {
        $result = $this->wpdb->insert(
            $this->persons_table,
            [
                'full_name' => sanitize_text_field($data['full_name']),
                'home_page_url' => isset($data['home_page_url']) 
                    ? esc_url_raw($data['home_page_url']) 
                    : null,
            ],
            ['%s', '%s']
        );
        
        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update a person
     */
    public function update_person(int $id, array $data): bool {
        $update_data = [];
        $format = [];
        
        if (isset($data['full_name'])) {
            $update_data['full_name'] = sanitize_text_field($data['full_name']);
            $format[] = '%s';
        }
        if (array_key_exists('home_page_url', $data)) {
            $update_data['home_page_url'] = $data['home_page_url'] 
                ? esc_url_raw($data['home_page_url']) 
                : null;
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $this->wpdb->update(
            $this->persons_table,
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Delete a person
     */
    public function delete_person(int $id): bool {
        $result = $this->wpdb->delete(
            $this->persons_table,
            ['id' => $id],
            ['%d']
        );
        
        return $result !== false;
    }

    // =========================================================================
    // PUZZLES CRUD
    // =========================================================================

    /**
     * Get a puzzle by ID
     */
    public function get_puzzle(int $id): ?object {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->puzzles_table} WHERE id = %d",
            $id
        );
        $result = $this->wpdb->get_row($sql);
        return $result ?: null;
    }

    /**
     * Get puzzle by publication date
     */
    public function get_puzzle_by_date(string $date): ?object {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->puzzles_table} WHERE publication_date = %s",
            $date
        );
        $result = $this->wpdb->get_row($sql);
        return $result ?: null;
    }

    /**
     * Get all puzzles
     */
    public function get_puzzles(array $args = []): array {
        $defaults = [
            'orderby' => 'publication_date',
            'order' => 'DESC',
            'limit' => 100,
            'offset' => 0,
        ];
        $args = wp_parse_args($args, $defaults);
        
        $allowed_orderby = ['id', 'publication_date', 'created_at'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'publication_date';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->puzzles_table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
            $args['limit'],
            $args['offset']
        );
        
        return $this->wpdb->get_results($sql);
    }

    /**
     * Count total puzzles
     */
    public function count_puzzles(): int {
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->puzzles_table}");
    }

    /**
     * Create a puzzle
     */
    public function create_puzzle(array $data): int|false {
        $result = $this->wpdb->insert(
            $this->puzzles_table,
            [
                'publication_date' => sanitize_text_field($data['publication_date']),
            ],
            ['%s']
        );
        
        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update a puzzle
     */
    public function update_puzzle(int $id, array $data): bool {
        if (!isset($data['publication_date'])) {
            return false;
        }
        
        $result = $this->wpdb->update(
            $this->puzzles_table,
            ['publication_date' => sanitize_text_field($data['publication_date'])],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Delete a puzzle
     */
    public function delete_puzzle(int $id): bool {
        // First delete related constructors
        $this->wpdb->delete($this->puzzle_constructors_table, ['puzzle_id' => $id], ['%d']);
        
        $result = $this->wpdb->delete(
            $this->puzzles_table,
            ['id' => $id],
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Get constructors for a puzzle
     */
    public function get_puzzle_constructors(int $puzzle_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT c.* FROM {$this->constructors_table} c
            INNER JOIN {$this->puzzle_constructors_table} pc ON c.id = pc.constructor_id
            WHERE pc.puzzle_id = %d
            ORDER BY pc.constructor_order ASC",
            $puzzle_id
        );
        
        return $this->wpdb->get_results($sql);
    }

    /**
     * Set constructors for a puzzle
     */
    public function set_puzzle_constructors(int $puzzle_id, array $constructor_ids): bool {
        // Delete existing constructors
        $this->wpdb->delete($this->puzzle_constructors_table, ['puzzle_id' => $puzzle_id], ['%d']);
        
        // Insert new constructors
        $order = 1;
        foreach ($constructor_ids as $constructor_id) {
            $this->wpdb->insert(
                $this->puzzle_constructors_table,
                [
                    'puzzle_id' => $puzzle_id,
                    'constructor_id' => (int) $constructor_id,
                    'constructor_order' => $order++,
                ],
                ['%d', '%d', '%d']
            );
        }
        
        return true;
    }

    // =========================================================================
    // ROUNDS CRUD
    // =========================================================================

    /**
     * Get a round by ID
     */
    public function get_round(int $id): ?object {
        $sql = $this->wpdb->prepare(
            "SELECT r.*, p.full_name as clue_giver_name 
            FROM {$this->rounds_table} r
            LEFT JOIN {$this->persons_table} p ON r.clue_giver_id = p.id
            WHERE r.id = %d",
            $id
        );
        $result = $this->wpdb->get_row($sql);
        return $result ?: null;
    }

    /**
     * Get a round by date and round number
     */
    public function get_round_by_date_and_number(string $date, int $round_number = 1): ?object {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->rounds_table} WHERE round_date = %s AND round_number = %d",
            $date,
            $round_number
        );
        $result = $this->wpdb->get_row($sql);
        return $result ?: null;
    }

    /**
     * Get all rounds for a specific date
     */
    public function get_rounds_by_date(string $date): array {
        $sql = $this->wpdb->prepare(
            "SELECT r.*, p.full_name as clue_giver_name 
            FROM {$this->rounds_table} r
            LEFT JOIN {$this->persons_table} p ON r.clue_giver_id = p.id
            WHERE r.round_date = %s
            ORDER BY r.round_number ASC",
            $date
        );
        return $this->wpdb->get_results($sql);
    }

    /**
     * Get next available round number for a date
     */
    public function get_next_round_number(string $date): int {
        $sql = $this->wpdb->prepare(
            "SELECT COALESCE(MAX(round_number), 0) + 1 FROM {$this->rounds_table} WHERE round_date = %s",
            $date
        );
        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Get all rounds
     */
    public function get_rounds(array $args = []): array {
        $defaults = [
            'orderby' => 'round_date',
            'order' => 'DESC',
            'limit' => 0,
            'offset' => 0,
        ];
        $args = wp_parse_args($args, $defaults);
        
        $allowed_orderby = ['id', 'round_date', 'episode_number', 'created_at'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'round_date';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';
        
        // Add secondary sort by round_number when ordering by date
        $secondary_sort = ($orderby === 'round_date') ? ', r.round_number ASC' : '';
        
        $limit_clause = '';
        if ((int) $args['limit'] > 0) {
            $limit_clause = $this->wpdb->prepare(' LIMIT %d OFFSET %d', $args['limit'], $args['offset']);
        }
        
        $sql = "SELECT r.*, p.full_name as clue_giver_name 
            FROM {$this->rounds_table} r
            LEFT JOIN {$this->persons_table} p ON r.clue_giver_id = p.id
            ORDER BY {$orderby} {$order}{$secondary_sort}{$limit_clause}";
        
        return $this->wpdb->get_results($sql);
    }

    /**
     * Count total rounds
     */
    public function count_rounds(): int {
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->rounds_table}");
    }

    /**
     * Create a round
     */
    public function create_round(array $data): int|false {
        $result = $this->wpdb->insert(
            $this->rounds_table,
            [
                'round_date' => sanitize_text_field($data['round_date']),
                'round_number' => (int) ($data['round_number'] ?? 1),
                'episode_number' => (int) $data['episode_number'],
                'episode_id' => isset($data['episode_id']) && $data['episode_id']
                    ? (int) $data['episode_id']
                    : null,
                'episode_url' => isset($data['episode_url']) && $data['episode_url']
                    ? esc_url_raw($data['episode_url'])
                    : null,
                'episode_start_seconds' => (int) ($data['episode_start_seconds'] ?? 0),
                'clue_giver_id' => (int) $data['clue_giver_id'],
                'description' => isset($data['description']) 
                    ? sanitize_textarea_field($data['description']) 
                    : null,
            ],
            ['%s', '%d', '%d', '%d', '%s', '%d', '%d', '%s']
        );
        
        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update a round
     */
    public function update_round(int $id, array $data): bool {
        $update_data = [];
        $format = [];
        
        if (isset($data['round_date'])) {
            $update_data['round_date'] = sanitize_text_field($data['round_date']);
            $format[] = '%s';
        }
        if (isset($data['round_number'])) {
            $update_data['round_number'] = (int) $data['round_number'];
            $format[] = '%d';
        }
        if (isset($data['episode_number'])) {
            $update_data['episode_number'] = (int) $data['episode_number'];
            $format[] = '%d';
        }
        if (array_key_exists('episode_id', $data)) {
            $update_data['episode_id'] = $data['episode_id']
                ? (int) $data['episode_id']
                : null;
            $format[] = '%d';
        }
        if (array_key_exists('episode_url', $data)) {
            $update_data['episode_url'] = $data['episode_url']
                ? esc_url_raw($data['episode_url'])
                : null;
            $format[] = '%s';
        }
        if (isset($data['episode_start_seconds'])) {
            $update_data['episode_start_seconds'] = (int) $data['episode_start_seconds'];
            $format[] = '%d';
        }
        if (isset($data['clue_giver_id'])) {
            $update_data['clue_giver_id'] = (int) $data['clue_giver_id'];
            $format[] = '%d';
        }
        if (array_key_exists('description', $data)) {
            $update_data['description'] = $data['description'] 
                ? sanitize_textarea_field($data['description']) 
                : null;
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $this->wpdb->update(
            $this->rounds_table,
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Delete a round
     */
    public function delete_round(int $id): bool {
        // Delete related data
        $clue_ids = $this->wpdb->get_col(
            $this->wpdb->prepare("SELECT id FROM {$this->clues_table} WHERE round_id = %d", $id)
        );
        
        if (!empty($clue_ids)) {
            $placeholders = implode(',', array_fill(0, count($clue_ids), '%d'));
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "DELETE FROM {$this->guesses_table} WHERE clue_id IN ($placeholders)",
                    ...$clue_ids
                )
            );
        }
        
        $this->wpdb->delete($this->clues_table, ['round_id' => $id], ['%d']);
        $this->wpdb->delete($this->round_solutions_table, ['round_id' => $id], ['%d']);
        $this->wpdb->delete($this->round_guessers_table, ['round_id' => $id], ['%d']);
        
        $result = $this->wpdb->delete(
            $this->rounds_table,
            ['id' => $id],
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Get solution words for a round
     */
    public function get_round_solutions(int $round_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->round_solutions_table} 
            WHERE round_id = %d 
            ORDER BY word_order ASC",
            $round_id
        );
        
        return $this->wpdb->get_results($sql);
    }

    /**
     * Set solution words for a round
     */
    public function set_round_solutions(int $round_id, array $words): bool {
        // Delete existing solutions
        $this->wpdb->delete($this->round_solutions_table, ['round_id' => $round_id], ['%d']);
        
        // Insert new solutions
        $order = 1;
        foreach ($words as $word) {
            $this->wpdb->insert(
                $this->round_solutions_table,
                [
                    'round_id' => $round_id,
                    'word' => strtoupper(sanitize_text_field($word)),
                    'word_order' => $order++,
                ],
                ['%d', '%s', '%d']
            );
        }
        
        return true;
    }

    /**
     * Get guessers for a round
     */
    public function get_round_guessers(int $round_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT p.* FROM {$this->persons_table} p
            INNER JOIN {$this->round_guessers_table} rg ON p.id = rg.person_id
            WHERE rg.round_id = %d
            ORDER BY p.full_name ASC",
            $round_id
        );
        
        return $this->wpdb->get_results($sql);
    }

    /**
     * Set guessers for a round
     */
    public function set_round_guessers(int $round_id, array $person_ids): bool {
        // Delete existing guessers
        $this->wpdb->delete($this->round_guessers_table, ['round_id' => $round_id], ['%d']);
        
        // Insert new guessers
        foreach ($person_ids as $person_id) {
            $this->wpdb->insert(
                $this->round_guessers_table,
                [
                    'round_id' => $round_id,
                    'person_id' => (int) $person_id,
                ],
                ['%d', '%d']
            );
        }
        
        return true;
    }

    // =========================================================================
    // CLUES CRUD
    // =========================================================================

    /**
     * Get a clue by ID
     */
    public function get_clue(int $id): ?object {
        $sql = $this->wpdb->prepare(
            "SELECT c.*, pz.publication_date as puzzle_date
            FROM {$this->clues_table} c
            LEFT JOIN {$this->puzzles_table} pz ON c.puzzle_id = pz.id
            WHERE c.id = %d",
            $id
        );
        $result = $this->wpdb->get_row($sql);
        return $result ?: null;
    }

    /**
     * Get all clues for a round
     */
    public function get_round_clues(int $round_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT c.*, pz.publication_date as puzzle_date
            FROM {$this->clues_table} c
            LEFT JOIN {$this->puzzles_table} pz ON c.puzzle_id = pz.id
            WHERE c.round_id = %d
            ORDER BY c.clue_number ASC",
            $round_id
        );
        
        return $this->wpdb->get_results($sql);
    }

    /**
     * Create a clue
     */
    public function create_clue(array $data): int|false {
        $puzzle_id = !empty($data['puzzle_id']) ? (int) $data['puzzle_id'] : null;
        $puzzle_clue_number = !empty($data['puzzle_clue_number']) ? (int) $data['puzzle_clue_number'] : null;
        $puzzle_clue_direction = !empty($data['puzzle_clue_direction']) ? sanitize_text_field($data['puzzle_clue_direction']) : null;

        $insert_data = [
            'round_id' => (int) $data['round_id'],
            'clue_number' => (int) $data['clue_number'],
            'puzzle_id' => $puzzle_id,
            'puzzle_clue_number' => $puzzle_clue_number,
            'puzzle_clue_direction' => $puzzle_clue_direction,
            'clue_text' => sanitize_textarea_field($data['clue_text']),
            'correct_answer' => strtoupper(sanitize_text_field($data['correct_answer'])),
        ];

        $format = [
            '%d', '%d',
            $puzzle_id !== null ? '%d' : null,
            $puzzle_clue_number !== null ? '%d' : null,
            $puzzle_clue_direction !== null ? '%s' : null,
            '%s', '%s',
        ];

        $result = $this->wpdb->insert($this->clues_table, $insert_data, $format);
        
        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update a clue
     */
    public function update_clue(int $id, array $data): bool {
        $update_data = [];
        $format = [];
        
        if (isset($data['clue_number'])) {
            $update_data['clue_number'] = (int) $data['clue_number'];
            $format[] = '%d';
        }
        if (array_key_exists('puzzle_id', $data)) {
            $update_data['puzzle_id'] = !empty($data['puzzle_id']) ? (int) $data['puzzle_id'] : null;
            $format[] = $update_data['puzzle_id'] !== null ? '%d' : null;
        }
        if (array_key_exists('puzzle_clue_number', $data)) {
            $update_data['puzzle_clue_number'] = !empty($data['puzzle_clue_number']) ? (int) $data['puzzle_clue_number'] : null;
            $format[] = $update_data['puzzle_clue_number'] !== null ? '%d' : null;
        }
        if (array_key_exists('puzzle_clue_direction', $data)) {
            $update_data['puzzle_clue_direction'] = !empty($data['puzzle_clue_direction']) ? sanitize_text_field($data['puzzle_clue_direction']) : null;
            $format[] = $update_data['puzzle_clue_direction'] !== null ? '%s' : null;
        }
        if (isset($data['clue_text'])) {
            $update_data['clue_text'] = sanitize_textarea_field($data['clue_text']);
            $format[] = '%s';
        }
        if (isset($data['correct_answer'])) {
            $update_data['correct_answer'] = strtoupper(sanitize_text_field($data['correct_answer']));
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $this->wpdb->update(
            $this->clues_table,
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Delete a clue
     */
    public function delete_clue(int $id): bool {
        // Delete related guesses
        $this->wpdb->delete($this->guesses_table, ['clue_id' => $id], ['%d']);
        
        $result = $this->wpdb->delete(
            $this->clues_table,
            ['id' => $id],
            ['%d']
        );
        
        return $result !== false;
    }

    // =========================================================================
    // GUESSES CRUD
    // =========================================================================

    /**
     * Get guesses for a clue
     */
    public function get_clue_guesses(int $clue_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT g.*, p.full_name as guesser_name
            FROM {$this->guesses_table} g
            INNER JOIN {$this->persons_table} p ON g.guesser_person_id = p.id
            WHERE g.clue_id = %d
            ORDER BY p.full_name ASC",
            $clue_id
        );
        
        return $this->wpdb->get_results($sql);
    }

    /**
     * Set or update a guess
     */
    public function set_guess(int $clue_id, int $guesser_person_id, string $guessed_word): bool {
        // Get the clue to determine if the guess is correct
        $clue = $this->get_clue($clue_id);
        if (!$clue) {
            return false;
        }
        
        $guessed_word = strtoupper(sanitize_text_field($guessed_word));
        $is_correct = ($guessed_word === $clue->correct_answer) ? 1 : 0;
        
        // Check if guess exists
        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->guesses_table} 
                WHERE clue_id = %d AND guesser_person_id = %d",
                $clue_id,
                $guesser_person_id
            )
        );
        
        if ($existing) {
            $result = $this->wpdb->update(
                $this->guesses_table,
                [
                    'guessed_word' => $guessed_word,
                    'is_correct' => $is_correct,
                ],
                [
                    'clue_id' => $clue_id,
                    'guesser_person_id' => $guesser_person_id,
                ],
                ['%s', '%d'],
                ['%d', '%d']
            );
        } else {
            $result = $this->wpdb->insert(
                $this->guesses_table,
                [
                    'clue_id' => $clue_id,
                    'guesser_person_id' => $guesser_person_id,
                    'guessed_word' => $guessed_word,
                    'is_correct' => $is_correct,
                ],
                ['%d', '%d', '%s', '%d']
            );
        }
        
        return $result !== false;
    }

    /**
     * Delete a guess
     */
    public function delete_guess(int $clue_id, int $guesser_person_id): bool {
        $result = $this->wpdb->delete(
            $this->guesses_table,
            [
                'clue_id' => $clue_id,
                'guesser_person_id' => $guesser_person_id,
            ],
            ['%d', '%d']
        );
        
        return $result !== false;
    }

    // =========================================================================
    // STATISTICS QUERIES
    // =========================================================================

    /**
     * Get number of clues in a round
     */
    public function get_round_clue_count(int $round_id): int {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->clues_table} WHERE round_id = %d",
            $round_id
        );
        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Get guesser results for a round
     */
    public function get_round_guesser_results(int $round_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT 
                p.id as person_id,
                p.full_name,
                COUNT(g.id) as total_guesses,
                SUM(g.is_correct) as correct_guesses
            FROM {$this->persons_table} p
            INNER JOIN {$this->round_guessers_table} rg ON p.id = rg.person_id
            LEFT JOIN {$this->clues_table} c ON c.round_id = rg.round_id
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id AND g.guesser_person_id = p.id
            WHERE rg.round_id = %d
            GROUP BY p.id, p.full_name
            ORDER BY p.full_name ASC",
            $round_id
        );
        
        return $this->wpdb->get_results($sql);
    }

    /**
     * Get person statistics
     */
    public function get_person_stats(int $person_id): object {
        // Get rounds played as guesser
        $rounds_played = (int) $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT round_id) FROM {$this->round_guessers_table} WHERE person_id = %d",
                $person_id
            )
        );
        
        // Get total clues answered and correct answers
        $guess_stats = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_clues_answered,
                    SUM(is_correct) as total_correct
                FROM {$this->guesses_table}
                WHERE guesser_person_id = %d",
                $person_id
            )
        );
        
        // Get per-round statistics for min/max/mean/median calculations
        $round_results = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT 
                    c.round_id,
                    COUNT(*) as clue_count,
                    SUM(g.is_correct) as correct_count
                FROM {$this->guesses_table} g
                INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
                WHERE g.guesser_person_id = %d
                GROUP BY c.round_id",
                $person_id
            )
        );
        
        $correct_counts = array_map(fn($r) => (int) $r->correct_count, $round_results);
        $percentages = array_map(function($r) {
            return $r->clue_count > 0 ? ((int) $r->correct_count / (int) $r->clue_count) * 100 : 0;
        }, $round_results);
        
        return (object) [
            'rounds_played' => $rounds_played,
            'total_clues_answered' => (int) ($guess_stats->total_clues_answered ?? 0),
            'total_correct' => (int) ($guess_stats->total_correct ?? 0),
            'overall_percentage' => $guess_stats->total_clues_answered > 0 
                ? round(($guess_stats->total_correct / $guess_stats->total_clues_answered) * 100, 1) 
                : 0,
            'min_correct' => !empty($correct_counts) ? min($correct_counts) : 0,
            'max_correct' => !empty($correct_counts) ? max($correct_counts) : 0,
            'mean_correct' => !empty($correct_counts) ? round(array_sum($correct_counts) / count($correct_counts), 1) : 0,
            'median_correct' => $this->calculate_median($correct_counts),
            'min_percentage' => !empty($percentages) ? round(min($percentages), 1) : 0,
            'max_percentage' => !empty($percentages) ? round(max($percentages), 1) : 0,
            'mean_percentage' => !empty($percentages) ? round(array_sum($percentages) / count($percentages), 1) : 0,
            'median_percentage' => round($this->calculate_median($percentages), 1),
        ];
    }

    /**
     * Calculate median of an array
     */
    private function calculate_median(array $values): float {
        if (empty($values)) {
            return 0;
        }
        
        sort($values);
        $count = count($values);
        $middle = (int) floor($count / 2);
        
        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }
        
        return $values[$middle];
    }

    /**
     * Get person results by clue number
     */
    public function get_person_results_by_clue_number(int $person_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT 
                c.clue_number,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            WHERE g.guesser_person_id = %d
            GROUP BY c.clue_number
            ORDER BY c.clue_number ASC",
            $person_id
        );
        
        return $this->wpdb->get_results($sql);
    }

    /**
     * Get person results by clue direction (Across vs Down)
     */
    public function get_person_results_by_direction(int $person_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT 
                c.puzzle_clue_direction as direction,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            WHERE g.guesser_person_id = %d
            GROUP BY c.puzzle_clue_direction
            ORDER BY c.puzzle_clue_direction ASC",
            $person_id
        );
        
        return $this->wpdb->get_results($sql);
    }

    /**
     * Get person results by puzzle day of week
     */
    public function get_person_results_by_day_of_week(int $person_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT 
                DAYOFWEEK(pz.publication_date) as day_of_week,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->puzzles_table} pz ON c.puzzle_id = pz.id
            WHERE g.guesser_person_id = %d
            GROUP BY DAYOFWEEK(pz.publication_date)
            ORDER BY DAYOFWEEK(pz.publication_date) ASC",
            $person_id
        );
        
        return $this->wpdb->get_results($sql);
    }

    /**
     * Get person results by puzzle publication decade
     */
    public function get_person_results_by_decade(int $person_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT 
                FLOOR(YEAR(pz.publication_date) / 10) * 10 as decade,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->puzzles_table} pz ON c.puzzle_id = pz.id
            WHERE g.guesser_person_id = %d
            GROUP BY FLOOR(YEAR(pz.publication_date) / 10) * 10
            ORDER BY decade ASC",
            $person_id
        );
        
        return $this->wpdb->get_results($sql);
    }

    /**
     * Get person results by constructor
     */
    public function get_person_results_by_constructor(int $person_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT 
                con.id as constructor_id,
                con.full_name,
                con.xwordinfo_profile_name,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->puzzle_constructors_table} pc ON c.puzzle_id = pc.puzzle_id
            INNER JOIN {$this->constructors_table} con ON pc.constructor_id = con.id
            WHERE g.guesser_person_id = %d
            GROUP BY con.id, con.full_name, con.xwordinfo_profile_name
            ORDER BY (SUM(g.is_correct) / COUNT(*)) DESC, COUNT(*) DESC",
            $person_id
        );
        
        return $this->wpdb->get_results($sql);
    }

    /**
     * Get person's round history
     */
    public function get_person_round_history(int $person_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT 
                r.id as round_id,
                r.round_date,
                r.round_number,
                r.episode_number,
                r.episode_url,
                r.episode_start_seconds,
                COUNT(c.id) as total_clues,
                SUM(g.is_correct) as correct_count
            FROM {$this->rounds_table} r
            INNER JOIN {$this->round_guessers_table} rg ON r.id = rg.round_id
            INNER JOIN {$this->clues_table} c ON r.id = c.round_id
            LEFT JOIN {$this->guesses_table} g ON c.id = g.clue_id AND g.guesser_person_id = rg.person_id
            WHERE rg.person_id = %d
            GROUP BY r.id, r.round_date, r.round_number, r.episode_number, r.episode_url, r.episode_start_seconds
            ORDER BY r.round_date DESC, r.round_number ASC",
            $person_id
        );
        
        return $this->wpdb->get_results($sql);
    }
}
