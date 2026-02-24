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
     * Get a constructor by name (case-insensitive, underscores match spaces)
     */
    public function get_constructor_by_name(string $name): ?object {
        $name = str_replace('_', ' ', $name);
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->constructors_table} WHERE full_name = %s",
            $name
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
        $insert_data = [
            'full_name' => sanitize_text_field($data['full_name']),
            'xwordinfo_profile_name' => isset($data['xwordinfo_profile_name'])
                ? sanitize_text_field($data['xwordinfo_profile_name'])
                : null,
            'xwordinfo_image_url' => isset($data['xwordinfo_image_url'])
                ? esc_url_raw($data['xwordinfo_image_url'])
                : null,
        ];
        $format = ['%s', '%s', '%s'];

        if (array_key_exists('media_id', $data)) {
            $insert_data['media_id'] = $data['media_id'] ? (int) $data['media_id'] : null;
            $format[] = '%d';
        }

        $result = $this->wpdb->insert(
            $this->constructors_table,
            $insert_data,
            $format
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
        if (array_key_exists('media_id', $data)) {
            $update_data['media_id'] = $data['media_id'] ? (int) $data['media_id'] : null;
            $format[] = '%d';
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
     * Get a person by name (case-insensitive, underscores match spaces)
     */
    public function get_person_by_name(string $name): ?object {
        $name = str_replace('_', ' ', $name);
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->persons_table} WHERE full_name = %s",
            $name
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
        $insert_data = [
            'full_name' => sanitize_text_field($data['full_name']),
            'home_page_url' => isset($data['home_page_url'])
                ? esc_url_raw($data['home_page_url'])
                : null,
            'image_url' => isset($data['image_url'])
                ? esc_url_raw($data['image_url'])
                : null,
            'hide_xwordinfo' => !empty($data['hide_xwordinfo']) ? 1 : 0,
        ];
        $format = ['%s', '%s', '%s', '%d'];

        if (array_key_exists('media_id', $data)) {
            $insert_data['media_id'] = $data['media_id'] ? (int) $data['media_id'] : null;
            $format[] = '%d';
        }

        $result = $this->wpdb->insert(
            $this->persons_table,
            $insert_data,
            $format
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
        if (array_key_exists('image_url', $data)) {
            $update_data['image_url'] = $data['image_url']
                ? esc_url_raw($data['image_url'])
                : null;
            $format[] = '%s';
        }
        if (array_key_exists('media_id', $data)) {
            $update_data['media_id'] = $data['media_id'] ? (int) $data['media_id'] : null;
            $format[] = '%d';
        }
        if (array_key_exists('hide_xwordinfo', $data)) {
            $update_data['hide_xwordinfo'] = !empty($data['hide_xwordinfo']) ? 1 : 0;
            $format[] = '%d';
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
            'constructor_search' => '',
        ];
        $args = wp_parse_args($args, $defaults);

        $allowed_orderby = ['id', 'publication_date', 'created_at'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'publication_date';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        if (!empty($args['constructor_search'])) {
            $sql = $this->wpdb->prepare(
                "SELECT DISTINCT p.* FROM {$this->puzzles_table} p
                INNER JOIN {$this->puzzle_constructors_table} pc ON p.id = pc.puzzle_id
                INNER JOIN {$this->constructors_table} c ON pc.constructor_id = c.id
                WHERE c.full_name LIKE %s
                ORDER BY p.{$orderby} {$order} LIMIT %d OFFSET %d",
                '%' . $this->wpdb->esc_like($args['constructor_search']) . '%',
                $args['limit'],
                $args['offset']
            );
        } else {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->puzzles_table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                $args['limit'],
                $args['offset']
            );
        }

        return $this->wpdb->get_results($sql);
    }

    /**
     * Count total puzzles
     */
    public function count_puzzles(string $constructor_search = ''): int {
        if (!empty($constructor_search)) {
            $sql = $this->wpdb->prepare(
                "SELECT COUNT(DISTINCT p.id) FROM {$this->puzzles_table} p
                INNER JOIN {$this->puzzle_constructors_table} pc ON p.id = pc.puzzle_id
                INNER JOIN {$this->constructors_table} c ON pc.constructor_id = c.id
                WHERE c.full_name LIKE %s",
                '%' . $this->wpdb->esc_like($constructor_search) . '%'
            );
            return (int) $this->wpdb->get_var($sql);
        }
        return (int) $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->puzzles_table}");
    }

    /**
     * Create a puzzle
     */
    public function create_puzzle(array $data): int|false {
        $insert_data = [
            'publication_date' => sanitize_text_field($data['publication_date']),
        ];
        $format = ['%s'];

        if (array_key_exists('editor_name', $data)) {
            $insert_data['editor_name'] = $data['editor_name'] !== null && $data['editor_name'] !== ''
                ? sanitize_text_field($data['editor_name'])
                : null;
            $format[] = '%s';
        }

        $result = $this->wpdb->insert(
            $this->puzzles_table,
            $insert_data,
            $format
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

        $update_data = [
            'publication_date' => sanitize_text_field($data['publication_date']),
        ];
        $format = ['%s'];

        if (array_key_exists('editor_name', $data)) {
            $update_data['editor_name'] = $data['editor_name'] !== null && $data['editor_name'] !== ''
                ? sanitize_text_field($data['editor_name'])
                : null;
            $format[] = '%s';
        }

        $result = $this->wpdb->update(
            $this->puzzles_table,
            $update_data,
            ['id' => $id],
            $format,
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
     * Auto-populate editor names for all puzzles based on historical date ranges.
     *
     * @return int Number of puzzles updated
     */
    public function auto_populate_editor_names(): int {
        $updated = 0;
        foreach (self::get_editor_date_ranges() as $editor) {
            $result = $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$this->puzzles_table}
                     SET editor_name = %s
                     WHERE publication_date >= %s AND publication_date <= %s",
                    $editor['name'],
                    $editor['start'],
                    $editor['end']
                )
            );
            if ($result !== false) {
                $updated += (int) $result;
            }
        }

        return $updated;
    }

    /**
     * Get the NYT crossword editor date ranges.
     *
     * Later entries override earlier ones for overlapping date ranges.
     *
     * @return array<array{name: string, start: string, end: string}>
     */
    public static function get_editor_date_ranges(): array {
        return [
            ['name' => 'Margaret P. Farrar', 'start' => '1942-02-15', 'end' => '1969-01-05'],
            ['name' => 'Will Weng',         'start' => '1969-01-06', 'end' => '1977-02-27'],
            ['name' => 'Eugene T. Maleska', 'start' => '1977-02-28', 'end' => '1993-09-05'],
            ['name' => 'Mel Taub',          'start' => '1993-09-06', 'end' => '1993-11-20'],
            ['name' => 'Will Shortz',       'start' => '1993-11-21', 'end' => '2099-12-31'],
            ['name' => 'Joel Fagliano',     'start' => '2024-03-14', 'end' => '2024-12-29'],
        ];
    }

    /**
     * Get the editor name for a given publication date based on historical date ranges.
     *
     * @param string $date Publication date in Y-m-d format
     * @return string|null Editor name, or null if no editor matches
     */
    public static function get_editor_for_date(string $date): ?string {
        $editor_name = null;
        foreach (self::get_editor_date_ranges() as $editor) {
            if ($date >= $editor['start'] && $date <= $editor['end']) {
                $editor_name = $editor['name'];
            }
        }
        return $editor_name;
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
     * Get the previous round (by date desc, round_number desc)
     */
    public function get_previous_round(int $current_round_id): ?object {
        $current = $this->get_round($current_round_id);
        if (!$current) {
            return null;
        }
        $sql = $this->wpdb->prepare(
            "SELECT id FROM {$this->rounds_table}
            WHERE (round_date < %s) OR (round_date = %s AND round_number < %d)
            ORDER BY round_date DESC, round_number DESC
            LIMIT 1",
            $current->round_date,
            $current->round_date,
            (int) $current->round_number
        );
        $result = $this->wpdb->get_row($sql);
        return $result ?: null;
    }

    /**
     * Get the next round (by date asc, round_number asc)
     */
    public function get_next_round(int $current_round_id): ?object {
        $current = $this->get_round($current_round_id);
        if (!$current) {
            return null;
        }
        $sql = $this->wpdb->prepare(
            "SELECT id FROM {$this->rounds_table}
            WHERE (round_date > %s) OR (round_date = %s AND round_number > %d)
            ORDER BY round_date ASC, round_number ASC
            LIMIT 1",
            $current->round_date,
            $current->round_date,
            (int) $current->round_number
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
     * Get overview statistics for all rounds
     */
    public function get_rounds_overview_stats(): object {
        $total_rounds = $this->count_rounds();

        $total_clues = (int) $this->wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->clues_table}"
        );

        $guess_stats = $this->wpdb->get_row(
            "SELECT COUNT(*) as total_guesses, SUM(is_correct) as total_correct FROM {$this->guesses_table}"
        );

        $total_guesses = (int) ($guess_stats->total_guesses ?? 0);
        $total_correct = (int) ($guess_stats->total_correct ?? 0);

        return (object) [
            'total_rounds' => $total_rounds,
            'total_clues' => $total_clues,
            'total_guesses' => $total_guesses,
            'total_correct' => $total_correct,
            'accuracy' => $total_guesses > 0
                ? round(($total_correct / $total_guesses) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get overview statistics for all rounds grouped by year
     */
    public function get_rounds_stats_by_year(): array {
        $sql = "SELECT
                YEAR(r.round_date) as year,
                COUNT(DISTINCT r.id) as total_rounds,
                COUNT(DISTINCT c.id) as total_clues,
                COUNT(g.id) as total_guesses,
                SUM(g.is_correct) as total_correct
            FROM {$this->rounds_table} r
            LEFT JOIN {$this->clues_table} c ON c.round_id = r.id
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
            GROUP BY YEAR(r.round_date)
            ORDER BY year ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get answer-number-by-clue-number matrix across all rounds,
     * partitioned by the number of solution words in the round.
     *
     * @return array Array of objects with solution_count, clue_number, answer_number, clue_count, correct_count
     */
    public function get_answer_by_clue_matrix(): array {
        $sql = "SELECT
                sc.solution_count,
                c.clue_number,
                rs.word_order AS answer_number,
                COUNT(*) AS clue_count,
                COALESCE(SUM(g.correct_guesses), 0) AS correct_count
            FROM {$this->clues_table} c
            INNER JOIN {$this->round_solutions_table} rs
                ON rs.round_id = c.round_id
                AND UPPER(rs.word) = UPPER(c.correct_answer)
            INNER JOIN (
                SELECT round_id, COUNT(*) AS solution_count
                FROM {$this->round_solutions_table}
                GROUP BY round_id
            ) sc ON sc.round_id = c.round_id
            LEFT JOIN (
                SELECT clue_id, SUM(is_correct) AS correct_guesses
                FROM {$this->guesses_table}
                GROUP BY clue_id
            ) g ON g.clue_id = c.id
            GROUP BY sc.solution_count, c.clue_number, rs.word_order
            ORDER BY sc.solution_count ASC, c.clue_number ASC, rs.word_order ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get the maximum number of solution words in any round.
     *
     * @return int
     */
    public function get_max_solution_count(): int {
        $sql = "SELECT MAX(cnt) FROM (
                SELECT COUNT(*) AS cnt
                FROM {$this->round_solutions_table}
                GROUP BY round_id
            ) sub";

        return (int) $this->wpdb->get_var($sql);
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
                'description2' => isset($data['description2'])
                    ? sanitize_textarea_field($data['description2'])
                    : null,
            ],
            ['%s', '%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s']
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
        if (array_key_exists('description2', $data)) {
            $update_data['description2'] = $data['description2']
                ? sanitize_textarea_field($data['description2'])
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
            "SELECT c.*, pz.publication_date as puzzle_date, pz.editor_name as editor_name
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
        $insert_data = [
            'round_id' => (int) $data['round_id'],
            'clue_number' => (int) $data['clue_number'],
            'clue_text' => sanitize_textarea_field($data['clue_text']),
            'correct_answer' => strtoupper(sanitize_text_field($data['correct_answer'])),
        ];
        $format = ['%d', '%d', '%s', '%s'];

        if (!empty($data['puzzle_id'])) {
            $insert_data['puzzle_id'] = (int) $data['puzzle_id'];
            $format[] = '%d';
        }
        if (!empty($data['puzzle_clue_number'])) {
            $insert_data['puzzle_clue_number'] = (int) $data['puzzle_clue_number'];
            $format[] = '%d';
        }
        if (!empty($data['puzzle_clue_direction'])) {
            $insert_data['puzzle_clue_direction'] = sanitize_text_field($data['puzzle_clue_direction']);
            $format[] = '%s';
        }

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
            $format[] = '%d';
        }
        if (array_key_exists('puzzle_clue_number', $data)) {
            $update_data['puzzle_clue_number'] = !empty($data['puzzle_clue_number']) ? (int) $data['puzzle_clue_number'] : null;
            $format[] = '%d';
        }
        if (array_key_exists('puzzle_clue_direction', $data)) {
            $update_data['puzzle_clue_direction'] = !empty($data['puzzle_clue_direction']) ? sanitize_text_field($data['puzzle_clue_direction']) : null;
            $format[] = '%s';
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

        // Get total clues answered and correct answers (only from rounds where person is a guesser)
        $guess_stats = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT
                    COUNT(*) as total_clues_answered,
                    SUM(g.is_correct) as total_correct
                FROM {$this->guesses_table} g
                INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
                INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
                WHERE g.guesser_person_id = %d",
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
                INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
                WHERE g.guesser_person_id = %d
                GROUP BY c.round_id",
                $person_id
            )
        );

        $correct_counts = array_map(fn($r) => (int) $r->correct_count, $round_results);
        $percentages = array_map(function($r) {
            return $r->clue_count > 0 ? ((int) $r->correct_count / (int) $r->clue_count) * 100 : 0;
        }, $round_results);

        // Calculate longest streak of consecutive correct answers in a single round
        $best_streak = 0;
        $streak_rows = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT c.round_id, c.clue_number, g.is_correct
                FROM {$this->guesses_table} g
                INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
                INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
                WHERE g.guesser_person_id = %d
                ORDER BY c.round_id ASC, c.clue_number ASC",
                $person_id
            )
        );
        $prev_round = null;
        $streak = 0;
        foreach ($streak_rows as $sr) {
            if ((int) $sr->round_id !== $prev_round) {
                $streak = 0;
                $prev_round = (int) $sr->round_id;
            }
            if ((int) $sr->is_correct) {
                $streak++;
                if ($streak > $best_streak) {
                    $best_streak = $streak;
                }
            } else {
                $streak = 0;
            }
        }

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
            'best_streak' => $best_streak,
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
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            WHERE g.guesser_person_id = %d
            GROUP BY c.clue_number
            ORDER BY c.clue_number ASC",
            $person_id
        );

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get person results grouped by answer length (alphanumeric characters only)
     */
    public function get_person_results_by_answer_length(int $person_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT
                CHAR_LENGTH(REGEXP_REPLACE(c.correct_answer, '[^A-Za-z0-9]', '')) as answer_length,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            WHERE g.guesser_person_id = %d
            GROUP BY CHAR_LENGTH(REGEXP_REPLACE(c.correct_answer, '[^A-Za-z0-9]', ''))
            ORDER BY CHAR_LENGTH(REGEXP_REPLACE(c.correct_answer, '[^A-Za-z0-9]', '')) ASC",
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
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
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
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            INNER JOIN {$this->puzzles_table} pz ON c.puzzle_id = pz.id
            WHERE g.guesser_person_id = %d
            GROUP BY DAYOFWEEK(pz.publication_date)
            ORDER BY MOD(DAYOFWEEK(pz.publication_date) + 5, 7) ASC",
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
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            INNER JOIN {$this->puzzles_table} pz ON c.puzzle_id = pz.id
            WHERE g.guesser_person_id = %d
            GROUP BY FLOOR(YEAR(pz.publication_date) / 10) * 10
            ORDER BY decade ASC",
            $person_id
        );

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get person results by year of round
     */
    public function get_person_results_by_year(int $person_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT
                YEAR(r.round_date) as year,
                COUNT(DISTINCT r.id) as rounds_played,
                COUNT(g.id) as total_answered,
                SUM(g.is_correct) as correct_count,
                MAX(rs.round_score) as best_score
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            INNER JOIN {$this->rounds_table} r ON c.round_id = r.id
            LEFT JOIN (
                SELECT g2.guesser_person_id, c2.round_id, SUM(g2.is_correct) as round_score
                FROM {$this->guesses_table} g2
                INNER JOIN {$this->clues_table} c2 ON g2.clue_id = c2.id
                INNER JOIN {$this->round_guessers_table} rg2 ON rg2.round_id = c2.round_id AND rg2.person_id = g2.guesser_person_id
                WHERE g2.guesser_person_id = %d
                GROUP BY g2.guesser_person_id, c2.round_id
            ) rs ON rs.round_id = r.id AND rs.guesser_person_id = g.guesser_person_id
            WHERE g.guesser_person_id = %d
            GROUP BY YEAR(r.round_date)
            ORDER BY year ASC",
            $person_id,
            $person_id
        );

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get the best streak of consecutive correct answers per year for a person
     *
     * @return array<int, int> Keyed by year
     */
    public function get_person_best_streaks_by_year(int $person_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT c.round_id, YEAR(r.round_date) as year, c.clue_number, g.is_correct
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            INNER JOIN {$this->rounds_table} r ON c.round_id = r.id
            WHERE g.guesser_person_id = %d
            ORDER BY year ASC, c.round_id ASC, c.clue_number ASC",
            $person_id
        );

        $rows = $this->wpdb->get_results($sql);
        $map = [];
        $prev_round = null;
        $streak = 0;

        foreach ($rows as $row) {
            $year = (int) $row->year;
            $round_id = (int) $row->round_id;

            if ($round_id !== $prev_round) {
                $streak = 0;
                $prev_round = $round_id;
            }

            if ((int) $row->is_correct) {
                $streak++;
                if (!isset($map[$year]) || $streak > $map[$year]) {
                    $map[$year] = $streak;
                }
            } else {
                $streak = 0;
            }
        }

        return $map;
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
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
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
     * Get person's results grouped by puzzle editor
     */
    public function get_person_results_by_editor(int $person_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT
                COALESCE(p.editor_name, 'Unknown') as editor_name,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            INNER JOIN {$this->puzzles_table} p ON c.puzzle_id = p.id
            WHERE g.guesser_person_id = %d
            GROUP BY editor_name
            ORDER BY (SUM(g.is_correct) / COUNT(*)) DESC, COUNT(*) DESC",
            $person_id
        );

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get the best streak of consecutive correct answers per round for a person.
     *
     * @return array<int, int> Keyed by round_id => best streak in that round
     */
    public function get_person_streak_per_round(int $person_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT c.round_id, c.clue_number, g.is_correct
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            WHERE g.guesser_person_id = %d
            ORDER BY c.round_id ASC, c.clue_number ASC",
            $person_id
        );

        $rows = $this->wpdb->get_results($sql);
        $map = [];
        $prev_round = null;
        $streak = 0;

        foreach ($rows as $row) {
            $round_id = (int) $row->round_id;

            if ($round_id !== $prev_round) {
                $streak = 0;
                $prev_round = $round_id;
            }

            if ((int) $row->is_correct) {
                $streak++;
                if (!isset($map[$round_id]) || $streak > $map[$round_id]) {
                    $map[$round_id] = $streak;
                }
            } else {
                $streak = 0;
            }
        }

        return $map;
    }

    /**
     * Get round IDs where the person answered a given clue number correctly.
     *
     * @return array<int, array<int>> Keyed by clue_number => [round_id, ...]
     */
    public function get_person_correct_clue_rounds(int $person_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT c.clue_number, c.round_id
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            WHERE g.guesser_person_id = %d AND g.is_correct = 1
            ORDER BY c.clue_number ASC, c.round_id ASC",
            $person_id
        );

        $rows = $this->wpdb->get_results($sql);
        $map = [];
        foreach ($rows as $row) {
            $cn = (int) $row->clue_number;
            $map[$cn][] = (int) $row->round_id;
        }

        return $map;
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
            ORDER BY r.round_date DESC, r.round_number DESC",
            $person_id
        );

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get the longest consecutive correct answer streak for a person in a round
     */
    public function get_person_round_streak(int $round_id, int $person_id): int {
        $sql = $this->wpdb->prepare(
            "SELECT g.is_correct
            FROM {$this->clues_table} c
            LEFT JOIN {$this->guesses_table} g ON c.id = g.clue_id AND g.guesser_person_id = %d
            WHERE c.round_id = %d
            ORDER BY c.clue_number ASC",
            $person_id,
            $round_id
        );

        $results = $this->wpdb->get_results($sql);

        $max_streak = 0;
        $current_streak = 0;
        foreach ($results as $row) {
            if ((int) ($row->is_correct ?? 0) === 1) {
                $current_streak++;
                if ($current_streak > $max_streak) {
                    $max_streak = $current_streak;
                }
            } else {
                $current_streak = 0;
            }
        }

        return $max_streak;
    }

    // =========================================================================
    // CONSTRUCTOR STATISTICS
    // =========================================================================

    /**
     * Get all persons (players) with round, guess, and accuracy stats
     */
    public function get_persons_with_stats(): array {
        $sql = "SELECT
                p.id,
                p.full_name,
                COUNT(DISTINCT rg.round_id) as rounds_played,
                COUNT(g.id) as clues_guessed,
                COALESCE(SUM(g.is_correct), 0) as correct_guesses
            FROM {$this->persons_table} p
            INNER JOIN {$this->round_guessers_table} rg ON p.id = rg.person_id
            LEFT JOIN {$this->rounds_table} r ON rg.round_id = r.id
            LEFT JOIN {$this->clues_table} c ON r.id = c.round_id
            LEFT JOIN {$this->guesses_table} g ON c.id = g.clue_id AND g.guesser_person_id = p.id
            GROUP BY p.id, p.full_name
            ORDER BY p.full_name ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get constructors that have puzzles, with puzzle and clue counts
     */
    public function get_constructors_with_stats(): array {
        $sql = "SELECT
                con.id,
                con.full_name,
                con.xwordinfo_profile_name,
                con.xwordinfo_image_url,
                COUNT(DISTINCT pc.puzzle_id) as puzzle_count,
                COUNT(DISTINCT c.id) as clue_count,
                COALESCE(SUM(g.is_correct), 0) as correct_guesses,
                COUNT(g.id) as total_guesses
            FROM {$this->constructors_table} con
            INNER JOIN {$this->puzzle_constructors_table} pc ON con.id = pc.constructor_id
            LEFT JOIN {$this->clues_table} c ON c.puzzle_id = pc.puzzle_id
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
            GROUP BY con.id, con.full_name, con.xwordinfo_profile_name, con.xwordinfo_image_url
            ORDER BY con.full_name ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get aggregate stats for a single constructor
     */
    public function get_constructor_stats(int $constructor_id): ?object {
        $sql = $this->wpdb->prepare(
            "SELECT
                COUNT(DISTINCT pc.puzzle_id) as puzzle_count,
                COUNT(DISTINCT c.id) as clue_count,
                COALESCE(SUM(g.is_correct), 0) as correct_guesses,
                COUNT(g.id) as total_guesses
            FROM {$this->puzzle_constructors_table} pc
            LEFT JOIN {$this->clues_table} c ON c.puzzle_id = pc.puzzle_id
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
            WHERE pc.constructor_id = %d",
            $constructor_id
        );

        return $this->wpdb->get_row($sql);
    }

    /**
     * Get puzzles for a constructor with round info
     */
    public function get_constructor_puzzles(int $constructor_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT
                pz.id as puzzle_id,
                pz.publication_date,
                pz.editor_name,
                GROUP_CONCAT(DISTINCT r.id ORDER BY r.round_date ASC, r.round_number ASC) as round_ids,
                GROUP_CONCAT(DISTINCT r.round_date ORDER BY r.round_date ASC, r.round_number ASC) as round_dates,
                GROUP_CONCAT(DISTINCT r.round_number ORDER BY r.round_date ASC, r.round_number ASC) as round_numbers
            FROM {$this->puzzles_table} pz
            INNER JOIN {$this->puzzle_constructors_table} pc ON pz.id = pc.puzzle_id
            LEFT JOIN {$this->clues_table} c ON c.puzzle_id = pz.id
            LEFT JOIN {$this->rounds_table} r ON c.round_id = r.id
            WHERE pc.constructor_id = %d
            GROUP BY pz.id, pz.publication_date, pz.editor_name
            ORDER BY pz.publication_date DESC",
            $constructor_id
        );

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get player results for all clues by a constructor
     */
    public function get_constructor_player_results(int $constructor_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT
                p.id as person_id,
                p.full_name,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            INNER JOIN {$this->puzzle_constructors_table} pc ON c.puzzle_id = pc.puzzle_id
            INNER JOIN {$this->persons_table} p ON g.guesser_person_id = p.id
            WHERE pc.constructor_id = %d
            GROUP BY p.id, p.full_name
            ORDER BY (SUM(g.is_correct) / COUNT(*)) DESC, COUNT(*) DESC",
            $constructor_id
        );

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get editor results for a constructor's puzzles
     */
    public function get_constructor_editor_results(int $constructor_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT
                COALESCE(pz.editor_name, 'Unknown') as editor_name,
                COUNT(DISTINCT pz.id) as puzzle_count,
                COUNT(DISTINCT c.id) as clue_count,
                COUNT(g.id) as total_guesses,
                COALESCE(SUM(g.is_correct), 0) as correct_guesses
            FROM {$this->puzzle_constructors_table} pc
            INNER JOIN {$this->puzzles_table} pz ON pc.puzzle_id = pz.id
            LEFT JOIN {$this->clues_table} c ON c.puzzle_id = pz.id
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
            WHERE pc.constructor_id = %d
            GROUP BY editor_name
            ORDER BY puzzle_count DESC, editor_name ASC",
            $constructor_id
        );

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get all editors with aggregate guess stats
     */
    public function get_editors_with_stats(): array {
        $sql = "SELECT
                COALESCE(p.editor_name, 'Unknown') as editor_name,
                COUNT(DISTINCT g.id) as clues_guessed,
                COALESCE(SUM(g.is_correct), 0) as correct_guesses
            FROM {$this->puzzles_table} p
            INNER JOIN {$this->clues_table} c ON c.puzzle_id = p.id
            INNER JOIN {$this->guesses_table} g ON g.clue_id = c.id
            WHERE p.editor_name IS NOT NULL AND p.editor_name != ''
            GROUP BY p.editor_name
            ORDER BY p.editor_name ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get player results for all clues edited by an editor
     */
    public function get_editor_player_results(string $editor_name): array {
        $sql = $this->wpdb->prepare(
            "SELECT
                p.id as person_id,
                p.full_name,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            INNER JOIN {$this->puzzles_table} pz ON c.puzzle_id = pz.id
            INNER JOIN {$this->persons_table} p ON g.guesser_person_id = p.id
            WHERE pz.editor_name = %s
            GROUP BY p.id, p.full_name
            ORDER BY (SUM(g.is_correct) / COUNT(*)) DESC, COUNT(*) DESC",
            $editor_name
        );

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get constructor results for all puzzles edited by an editor
     */
    public function get_editor_constructor_results(string $editor_name): array {
        $sql = $this->wpdb->prepare(
            "SELECT
                con.id as constructor_id,
                con.full_name,
                COUNT(DISTINCT pz.id) as puzzle_count,
                COUNT(DISTINCT c.id) as clue_count,
                COUNT(g.id) as total_guesses,
                COALESCE(SUM(g.is_correct), 0) as correct_guesses
            FROM {$this->puzzles_table} pz
            INNER JOIN {$this->puzzle_constructors_table} pc ON pz.id = pc.puzzle_id
            INNER JOIN {$this->constructors_table} con ON pc.constructor_id = con.id
            LEFT JOIN {$this->clues_table} c ON c.puzzle_id = pz.id
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
            WHERE pz.editor_name = %s
            GROUP BY con.id, con.full_name
            ORDER BY puzzle_count DESC, con.full_name ASC",
            $editor_name
        );

        return $this->wpdb->get_results($sql);
    }

    /**
     * Check if an editor name exists in the puzzles table.
     *
     * @param string $name The editor name to check
     * @return bool True if the editor name exists
     */
    public function editor_name_exists(string $name): bool {
        $sql = $this->wpdb->prepare(
            "SELECT 1 FROM {$this->puzzles_table} WHERE editor_name = %s LIMIT 1",
            $name
        );
        return (bool) $this->wpdb->get_var($sql);
    }

    /**
     * Get aggregate stats for a single editor
     */
    public function get_editor_stats(string $editor_name): ?object {
        $sql = $this->wpdb->prepare(
            "SELECT
                COUNT(DISTINCT p.id) as puzzle_count,
                COUNT(DISTINCT c.id) as clue_count,
                COALESCE(SUM(g.is_correct), 0) as correct_guesses,
                COUNT(g.id) as total_guesses
            FROM {$this->puzzles_table} p
            INNER JOIN {$this->clues_table} c ON c.puzzle_id = p.id
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
            WHERE p.editor_name = %s",
            $editor_name
        );

        return $this->wpdb->get_row($sql);
    }

    /**
     * Get puzzles edited by a specific editor, with constructor and round info
     */
    public function get_editor_puzzles(string $editor_name): array {
        $sql = $this->wpdb->prepare(
            "SELECT
                pz.id as puzzle_id,
                pz.publication_date,
                GROUP_CONCAT(DISTINCT con.full_name ORDER BY pc.constructor_order ASC SEPARATOR ', ') as constructor_names,
                GROUP_CONCAT(DISTINCT con.id ORDER BY pc.constructor_order ASC) as constructor_ids,
                GROUP_CONCAT(DISTINCT r.id ORDER BY r.round_date ASC, r.round_number ASC) as round_ids,
                GROUP_CONCAT(DISTINCT r.round_date ORDER BY r.round_date ASC, r.round_number ASC) as round_dates,
                GROUP_CONCAT(DISTINCT r.round_number ORDER BY r.round_date ASC, r.round_number ASC) as round_numbers
            FROM {$this->puzzles_table} pz
            LEFT JOIN {$this->puzzle_constructors_table} pc ON pz.id = pc.puzzle_id
            LEFT JOIN {$this->constructors_table} con ON pc.constructor_id = con.id
            LEFT JOIN {$this->clues_table} c ON c.puzzle_id = pz.id
            LEFT JOIN {$this->rounds_table} r ON c.round_id = r.id
            WHERE pz.editor_name = %s
            GROUP BY pz.id, pz.publication_date
            ORDER BY pz.publication_date DESC",
            $editor_name
        );

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get all puzzles with constructor/round details for the puzzles table
     */
    public function get_all_puzzles_with_details(): array {
        $sql = "SELECT
                pz.id as puzzle_id,
                pz.publication_date,
                COALESCE(pz.editor_name, 'Unknown') as editor_name,
                GROUP_CONCAT(DISTINCT con.full_name ORDER BY pc.constructor_order ASC SEPARATOR ', ') as constructor_names,
                GROUP_CONCAT(DISTINCT con.id ORDER BY pc.constructor_order ASC) as constructor_ids,
                GROUP_CONCAT(DISTINCT r.id ORDER BY r.round_date ASC, r.round_number ASC) as round_ids,
                GROUP_CONCAT(DISTINCT r.round_date ORDER BY r.round_date ASC, r.round_number ASC) as round_dates,
                GROUP_CONCAT(DISTINCT r.round_number ORDER BY r.round_date ASC, r.round_number ASC) as round_numbers
            FROM {$this->puzzles_table} pz
            LEFT JOIN {$this->puzzle_constructors_table} pc ON pz.id = pc.puzzle_id
            LEFT JOIN {$this->constructors_table} con ON pc.constructor_id = con.id
            LEFT JOIN {$this->clues_table} c ON c.puzzle_id = pz.id
            LEFT JOIN {$this->rounds_table} r ON c.round_id = r.id
            GROUP BY pz.id, pz.publication_date, pz.editor_name
            ORDER BY pz.publication_date DESC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get co-constructors for a constructor's puzzles
     */
    public function get_puzzle_co_constructors(int $puzzle_id, int $exclude_constructor_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT con.id, con.full_name, con.xwordinfo_profile_name
            FROM {$this->constructors_table} con
            INNER JOIN {$this->puzzle_constructors_table} pc ON con.id = pc.constructor_id
            WHERE pc.puzzle_id = %d AND con.id != %d
            ORDER BY pc.constructor_order ASC",
            $puzzle_id,
            $exclude_constructor_id
        );

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get the highest round score for each person.
     *
     * Returns an associative array keyed by person_id.
     *
     * @return array<int, int>
     */
    public function get_all_persons_highest_round_scores(): array {
        $sql = "SELECT sub.guesser_person_id, MAX(sub.round_score) as highest_round_score
            FROM (
                SELECT g.guesser_person_id, c.round_id, SUM(g.is_correct) as round_score
                FROM {$this->guesses_table} g
                INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
                GROUP BY g.guesser_person_id, c.round_id
            ) sub
            GROUP BY sub.guesser_person_id";

        $results = $this->wpdb->get_results($sql);
        $map = [];
        foreach ($results as $row) {
            $map[(int) $row->guesser_person_id] = (int) $row->highest_round_score;
        }
        return $map;
    }

    /**
     * Get the round IDs where each person achieved their highest score.
     *
     * Returns an associative array keyed by person_id, each value is an array of round_ids.
     *
     * @return array<int, int[]>
     */
    public function get_all_persons_highest_round_score_round_ids(): array {
        $sql = "SELECT sub.guesser_person_id, sub.round_id, sub.round_score
            FROM (
                SELECT g.guesser_person_id, c.round_id, SUM(g.is_correct) as round_score
                FROM {$this->guesses_table} g
                INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
                GROUP BY g.guesser_person_id, c.round_id
            ) sub
            INNER JOIN (
                SELECT guesser_person_id, MAX(round_score) as max_score
                FROM (
                    SELECT g.guesser_person_id, c.round_id, SUM(g.is_correct) as round_score
                    FROM {$this->guesses_table} g
                    INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
                    GROUP BY g.guesser_person_id, c.round_id
                ) inner_sub
                GROUP BY guesser_person_id
            ) best ON sub.guesser_person_id = best.guesser_person_id AND sub.round_score = best.max_score
            ORDER BY sub.guesser_person_id";

        $results = $this->wpdb->get_results($sql);
        $map = [];
        foreach ($results as $row) {
            $map[(int) $row->guesser_person_id][] = (int) $row->round_id;
        }
        return $map;
    }

    /**
     * Get person scores for a set of round IDs.
     *
     * Returns a nested array: [person_id][round_id] => ['correct' => int, 'total' => int]
     *
     * @param int[] $round_ids
     * @return array<int, array<int, array{correct: int, total: int}>>
     */
    public function get_person_scores_for_rounds(array $round_ids): array {
        if (empty($round_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($round_ids), '%d'));
        $sql = $this->wpdb->prepare(
            "SELECT g.guesser_person_id, c.round_id,
                SUM(g.is_correct) as correct_count,
                COUNT(c.id) as total_clues
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            WHERE c.round_id IN ($placeholders)
            GROUP BY g.guesser_person_id, c.round_id",
            ...$round_ids
        );

        $results = $this->wpdb->get_results($sql);
        $map = [];
        foreach ($results as $row) {
            $person_id = (int) $row->guesser_person_id;
            $round_id = (int) $row->round_id;
            $map[$person_id][$round_id] = [
                'correct' => (int) $row->correct_count,
                'total' => (int) $row->total_clues,
            ];
        }
        return $map;
    }

    /**
     * Get the longest streak of consecutive correct answers in a single round for each person.
     *
     * Returns an associative array keyed by person_id.
     *
     * @return array<int, int>
     */
    public function get_all_persons_longest_streaks(): array {
        $sql = "SELECT g.guesser_person_id, c.round_id, c.clue_number, g.is_correct
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            ORDER BY g.guesser_person_id ASC, c.round_id ASC, c.clue_number ASC";

        $rows = $this->wpdb->get_results($sql);

        $map = [];
        $prev_person = null;
        $prev_round = null;
        $streak = 0;

        foreach ($rows as $row) {
            $person_id = (int) $row->guesser_person_id;
            $round_id = (int) $row->round_id;
            $correct = (int) $row->is_correct;

            if ($person_id !== $prev_person || $round_id !== $prev_round) {
                $streak = 0;
                $prev_person = $person_id;
                $prev_round = $round_id;
            }

            if ($correct) {
                $streak++;
                if (!isset($map[$person_id]) || $streak > $map[$person_id]) {
                    $map[$person_id] = $streak;
                }
            } else {
                $streak = 0;
            }
        }

        return $map;
    }

    /**
     * Get the round IDs where each person achieved their longest streak.
     *
     * Returns an associative array keyed by person_id, each value is an array of round_ids.
     *
     * @return array<int, int[]>
     */
    public function get_all_persons_longest_streak_round_ids(): array {
        $sql = "SELECT g.guesser_person_id, c.round_id, c.clue_number, g.is_correct
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            ORDER BY g.guesser_person_id ASC, c.round_id ASC, c.clue_number ASC";

        $rows = $this->wpdb->get_results($sql);

        // First pass: find the best streak value per person
        $best = [];
        $prev_person = null;
        $prev_round = null;
        $streak = 0;

        foreach ($rows as $row) {
            $person_id = (int) $row->guesser_person_id;
            $round_id = (int) $row->round_id;
            $correct = (int) $row->is_correct;

            if ($person_id !== $prev_person || $round_id !== $prev_round) {
                $streak = 0;
                $prev_person = $person_id;
                $prev_round = $round_id;
            }

            if ($correct) {
                $streak++;
                if (!isset($best[$person_id]) || $streak > $best[$person_id]) {
                    $best[$person_id] = $streak;
                }
            } else {
                $streak = 0;
            }
        }

        // Second pass: collect round IDs that achieved the best streak for each person
        $map = [];
        $round_best_streak = [];
        $prev_person = null;
        $prev_round = null;
        $streak = 0;

        foreach ($rows as $row) {
            $person_id = (int) $row->guesser_person_id;
            $round_id = (int) $row->round_id;
            $correct = (int) $row->is_correct;

            if ($person_id !== $prev_person || $round_id !== $prev_round) {
                $streak = 0;
                $prev_person = $person_id;
                $prev_round = $round_id;
            }

            if ($correct) {
                $streak++;
                $key = $person_id . '-' . $round_id;
                if (!isset($round_best_streak[$key]) || $streak > $round_best_streak[$key]) {
                    $round_best_streak[$key] = $streak;
                }
            } else {
                $streak = 0;
            }
        }

        foreach ($round_best_streak as $key => $streak_val) {
            [$person_id, $round_id] = array_map('intval', explode('-', $key));
            if (isset($best[$person_id]) && $streak_val === $best[$person_id]) {
                $map[$person_id][] = $round_id;
            }
        }

        return $map;
    }

    // =========================================================================
    // EDITOR MEDIA
    // =========================================================================

    /**
     * Get the media attachment ID for an editor
     */
    public function get_editor_media_id(string $editor_name): int {
        $key = 'kealoa_editor_media_' . sanitize_title($editor_name);
        return (int) get_option($key, 0);
    }

    /**
     * Set the media attachment ID for an editor
     */
    public function set_editor_media_id(string $editor_name, int $media_id): bool {
        $key = 'kealoa_editor_media_' . sanitize_title($editor_name);
        return update_option($key, $media_id, false);
    }

    /**
     * Remove the media attachment ID for an editor
     */
    public function delete_editor_media_id(string $editor_name): bool {
        $key = 'kealoa_editor_media_' . sanitize_title($editor_name);
        return delete_option($key);
    }

    /**
     * Search across persons, constructors, editors, and round solution words
     *
     * @param string $search_term The search term
     * @return array Array of results with type, name, and url
     */
    public function search_all(string $search_term): array {
        $results = [];
        $like = '%' . $this->wpdb->esc_like($search_term) . '%';

        // Search persons
        $persons = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, full_name FROM {$this->persons_table} WHERE full_name LIKE %s ORDER BY full_name ASC",
                $like
            )
        );
        foreach ($persons as $p) {
            $slug = str_replace(' ', '_', $p->full_name);
            $results[] = (object) [
                'type' => 'player',
                'name' => $p->full_name,
                'url' => home_url('/kealoa/person/' . urlencode($slug) . '/'),
            ];
        }

        // Search constructors
        $constructors = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, full_name FROM {$this->constructors_table} WHERE full_name LIKE %s ORDER BY full_name ASC",
                $like
            )
        );
        foreach ($constructors as $c) {
            $results[] = (object) [
                'type' => 'constructor',
                'name' => $c->full_name,
                'url' => home_url('/kealoa/constructor/' . urlencode(str_replace(' ', '_', $c->full_name)) . '/'),
            ];
        }

        // Search editors (distinct names from puzzles table)
        $editors = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT DISTINCT editor_name FROM {$this->puzzles_table} WHERE editor_name IS NOT NULL AND editor_name != '' AND editor_name LIKE %s ORDER BY editor_name ASC",
                $like
            )
        );
        foreach ($editors as $e) {
            $results[] = (object) [
                'type' => 'editor',
                'name' => $e->editor_name,
                'url' => home_url('/kealoa/editor/' . urlencode($e->editor_name) . '/'),
            ];
        }

        // Track round IDs already added to avoid duplicates
        $seen_round_ids = [];

        // Helper to build a round result object
        $add_round_result = function (int $round_id) use (&$results, &$seen_round_ids): void {
            if (isset($seen_round_ids[$round_id])) {
                return;
            }
            $seen_round_ids[$round_id] = true;
            $solutions = $this->get_round_solutions($round_id);
            $words = array_map(fn($s) => strtoupper($s->word), $solutions);
            $results[] = (object) [
                'type' => 'round',
                'name' => 'KEALOA #' . $round_id . ': ' . implode(', ', $words),
                'url' => home_url('/kealoa/round/' . $round_id . '/'),
            ];
        };

        // Search round solution words
        $rounds = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT DISTINCT rs.round_id
                FROM {$this->round_solutions_table} rs
                INNER JOIN {$this->rounds_table} r ON rs.round_id = r.id
                WHERE rs.word LIKE %s
                ORDER BY rs.round_id DESC",
                $like
            )
        );
        foreach ($rounds as $rd) {
            $add_round_result((int) $rd->round_id);
        }

        // Search round descriptions
        $desc_rounds = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->rounds_table}
                WHERE (description IS NOT NULL AND description LIKE %s)
                   OR (description2 IS NOT NULL AND description2 LIKE %s)
                ORDER BY round_date DESC, round_number ASC",
                $like,
                $like
            )
        );
        foreach ($desc_rounds as $rd) {
            $add_round_result((int) $rd->id);
        }

        // Search clue text and correct answers (link to parent round)
        $clue_rounds = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT DISTINCT c.round_id
                FROM {$this->clues_table} c
                INNER JOIN {$this->rounds_table} r ON c.round_id = r.id
                WHERE c.clue_text LIKE %s OR c.correct_answer LIKE %s
                ORDER BY c.round_id DESC",
                $like,
                $like
            )
        );
        foreach ($clue_rounds as $rd) {
            $add_round_result((int) $rd->round_id);
        }

        // Include rounds from matching constructors
        if (!empty($constructors)) {
            $constructor_ids = array_map(fn($c) => (int) $c->id, $constructors);
            $placeholders = implode(',', array_fill(0, count($constructor_ids), '%d'));
            $constructor_rounds = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT DISTINCT cl.round_id
                    FROM {$this->puzzle_constructors_table} pc
                    INNER JOIN {$this->clues_table} cl ON cl.puzzle_id = pc.puzzle_id
                    INNER JOIN {$this->rounds_table} r ON cl.round_id = r.id
                    WHERE pc.constructor_id IN ($placeholders)
                    ORDER BY r.round_date DESC, r.round_number ASC",
                    ...$constructor_ids
                )
            );
            foreach ($constructor_rounds as $rd) {
                $add_round_result((int) $rd->round_id);
            }
        }

        // Include rounds from matching players (as guesser or clue giver)
        if (!empty($persons)) {
            $person_ids = array_map(fn($p) => (int) $p->id, $persons);
            $placeholders = implode(',', array_fill(0, count($person_ids), '%d'));
            $player_rounds = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT DISTINCT r.id AS round_id
                    FROM {$this->rounds_table} r
                    LEFT JOIN {$this->round_guessers_table} rg ON rg.round_id = r.id
                    WHERE r.clue_giver_id IN ($placeholders)
                       OR rg.person_id IN ($placeholders)
                    ORDER BY r.round_date DESC, r.round_number ASC",
                    ...array_merge($person_ids, $person_ids)
                )
            );
            foreach ($player_rounds as $rd) {
                $add_round_result((int) $rd->round_id);
            }
        }

        // Include rounds from matching editors
        if (!empty($editors)) {
            $editor_names = array_map(fn($e) => $e->editor_name, $editors);
            $placeholders = implode(',', array_fill(0, count($editor_names), '%s'));
            $editor_rounds = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT DISTINCT cl.round_id
                    FROM {$this->puzzles_table} pz
                    INNER JOIN {$this->clues_table} cl ON cl.puzzle_id = pz.id
                    INNER JOIN {$this->rounds_table} r ON cl.round_id = r.id
                    WHERE pz.editor_name IN ($placeholders)
                    ORDER BY r.round_date DESC, r.round_number ASC",
                    ...$editor_names
                )
            );
            foreach ($editor_rounds as $rd) {
                $add_round_result((int) $rd->round_id);
            }
        }

        return $results;
    }

    // =========================================================================
    // DATABASE CONSISTENCY CHECKS
    // =========================================================================

    /**
     * Run all consistency checks and return categorized issues.
     *
     * @return array<string, array> Keyed by check name, each value is an array
     *                              of issue objects with id, description, etc.
     */
    public function run_consistency_checks(): array {
        $issues = [];

        // 1. Orphan puzzles — not referenced by any clue
        $orphan_puzzles = $this->wpdb->get_results(
            "SELECT p.id, p.publication_date, p.editor_name
             FROM {$this->puzzles_table} p
             LEFT JOIN {$this->clues_table} c ON c.puzzle_id = p.id
             WHERE c.id IS NULL
             ORDER BY p.publication_date"
        );
        if ($orphan_puzzles) {
            $issues['orphan_puzzles'] = $orphan_puzzles;
        }

        // 2. Orphan constructors — not associated with any puzzle
        $orphan_constructors = $this->wpdb->get_results(
            "SELECT c.id, c.full_name
             FROM {$this->constructors_table} c
             LEFT JOIN {$this->puzzle_constructors_table} pc ON pc.constructor_id = c.id
             WHERE pc.id IS NULL
             ORDER BY c.full_name"
        );
        if ($orphan_constructors) {
            $issues['orphan_constructors'] = $orphan_constructors;
        }

        // 3. Rounds with no clues
        $rounds_no_clues = $this->wpdb->get_results(
            "SELECT r.id, r.round_date, r.round_number, r.description
             FROM {$this->rounds_table} r
             LEFT JOIN {$this->clues_table} c ON c.round_id = r.id
             WHERE c.id IS NULL
             ORDER BY r.round_date"
        );
        if ($rounds_no_clues) {
            $issues['rounds_no_clues'] = $rounds_no_clues;
        }

        // 4. Rounds with no solution words
        $rounds_no_solutions = $this->wpdb->get_results(
            "SELECT r.id, r.round_date, r.round_number, r.description
             FROM {$this->rounds_table} r
             LEFT JOIN {$this->round_solutions_table} rs ON rs.round_id = r.id
             WHERE rs.id IS NULL
             ORDER BY r.round_date"
        );
        if ($rounds_no_solutions) {
            $issues['rounds_no_solutions'] = $rounds_no_solutions;
        }

        // 5. Rounds with no guessers
        $rounds_no_guessers = $this->wpdb->get_results(
            "SELECT r.id, r.round_date, r.round_number, r.description
             FROM {$this->rounds_table} r
             LEFT JOIN {$this->round_guessers_table} rg ON rg.round_id = r.id
             WHERE rg.id IS NULL
             ORDER BY r.round_date"
        );
        if ($rounds_no_guessers) {
            $issues['rounds_no_guessers'] = $rounds_no_guessers;
        }

        // 6. Puzzle-constructor links referencing non-existent puzzles
        $orphan_pc_puzzles = $this->wpdb->get_results(
            "SELECT pc.id, pc.puzzle_id, pc.constructor_id
             FROM {$this->puzzle_constructors_table} pc
             LEFT JOIN {$this->puzzles_table} p ON p.id = pc.puzzle_id
             WHERE p.id IS NULL"
        );
        if ($orphan_pc_puzzles) {
            $issues['orphan_puzzle_constructor_puzzles'] = $orphan_pc_puzzles;
        }

        // 7. Puzzle-constructor links referencing non-existent constructors
        $orphan_pc_constructors = $this->wpdb->get_results(
            "SELECT pc.id, pc.puzzle_id, pc.constructor_id
             FROM {$this->puzzle_constructors_table} pc
             LEFT JOIN {$this->constructors_table} c ON c.id = pc.constructor_id
             WHERE c.id IS NULL"
        );
        if ($orphan_pc_constructors) {
            $issues['orphan_puzzle_constructor_constructors'] = $orphan_pc_constructors;
        }

        // 8. Clues referencing non-existent rounds
        $orphan_clue_rounds = $this->wpdb->get_results(
            "SELECT cl.id, cl.round_id, cl.clue_number, cl.clue_text
             FROM {$this->clues_table} cl
             LEFT JOIN {$this->rounds_table} r ON r.id = cl.round_id
             WHERE r.id IS NULL"
        );
        if ($orphan_clue_rounds) {
            $issues['orphan_clue_rounds'] = $orphan_clue_rounds;
        }

        // 9. Clues referencing non-existent puzzles
        $orphan_clue_puzzles = $this->wpdb->get_results(
            "SELECT cl.id, cl.round_id, cl.clue_number, cl.puzzle_id
             FROM {$this->clues_table} cl
             LEFT JOIN {$this->puzzles_table} p ON p.id = cl.puzzle_id
             WHERE cl.puzzle_id IS NOT NULL AND p.id IS NULL"
        );
        if ($orphan_clue_puzzles) {
            $issues['orphan_clue_puzzles'] = $orphan_clue_puzzles;
        }

        // 10. Guesses referencing non-existent clues
        $orphan_guess_clues = $this->wpdb->get_results(
            "SELECT g.id, g.clue_id, g.guesser_person_id, g.guessed_word
             FROM {$this->guesses_table} g
             LEFT JOIN {$this->clues_table} cl ON cl.id = g.clue_id
             WHERE cl.id IS NULL"
        );
        if ($orphan_guess_clues) {
            $issues['orphan_guess_clues'] = $orphan_guess_clues;
        }

        // 11. Guesses referencing non-existent persons
        $orphan_guess_persons = $this->wpdb->get_results(
            "SELECT g.id, g.clue_id, g.guesser_person_id, g.guessed_word
             FROM {$this->guesses_table} g
             LEFT JOIN {$this->persons_table} p ON p.id = g.guesser_person_id
             WHERE p.id IS NULL"
        );
        if ($orphan_guess_persons) {
            $issues['orphan_guess_persons'] = $orphan_guess_persons;
        }

        // 12. Round-guesser links referencing non-existent rounds
        $orphan_rg_rounds = $this->wpdb->get_results(
            "SELECT rg.id, rg.round_id, rg.person_id
             FROM {$this->round_guessers_table} rg
             LEFT JOIN {$this->rounds_table} r ON r.id = rg.round_id
             WHERE r.id IS NULL"
        );
        if ($orphan_rg_rounds) {
            $issues['orphan_round_guesser_rounds'] = $orphan_rg_rounds;
        }

        // 13. Round-guesser links referencing non-existent persons
        $orphan_rg_persons = $this->wpdb->get_results(
            "SELECT rg.id, rg.round_id, rg.person_id
             FROM {$this->round_guessers_table} rg
             LEFT JOIN {$this->persons_table} p ON p.id = rg.person_id
             WHERE p.id IS NULL"
        );
        if ($orphan_rg_persons) {
            $issues['orphan_round_guesser_persons'] = $orphan_rg_persons;
        }

        // 14. Round solutions referencing non-existent rounds
        $orphan_rs_rounds = $this->wpdb->get_results(
            "SELECT rs.id, rs.round_id, rs.word
             FROM {$this->round_solutions_table} rs
             LEFT JOIN {$this->rounds_table} r ON r.id = rs.round_id
             WHERE r.id IS NULL"
        );
        if ($orphan_rs_rounds) {
            $issues['orphan_round_solution_rounds'] = $orphan_rs_rounds;
        }

        // 15. Rounds referencing non-existent clue giver (person)
        $orphan_clue_givers = $this->wpdb->get_results(
            "SELECT r.id, r.round_date, r.round_number, r.clue_giver_id
             FROM {$this->rounds_table} r
             LEFT JOIN {$this->persons_table} p ON p.id = r.clue_giver_id
             WHERE p.id IS NULL"
        );
        if ($orphan_clue_givers) {
            $issues['orphan_round_clue_givers'] = $orphan_clue_givers;
        }

        return $issues;
    }

    /**
     * Delete orphan records from a junction or child table by ID.
     *
     * @param string $table_key Identifier for the table (e.g. 'puzzle_constructors').
     * @param array  $ids       Array of row IDs to delete.
     * @return int Number of rows deleted.
     */
    public function delete_orphan_records(string $table_key, array $ids): int {
        $table_map = [
            'puzzle_constructors'  => $this->puzzle_constructors_table,
            'clues'                => $this->clues_table,
            'guesses'              => $this->guesses_table,
            'round_guessers'       => $this->round_guessers_table,
            'round_solutions'      => $this->round_solutions_table,
        ];

        if (!isset($table_map[$table_key]) || empty($ids)) {
            return 0;
        }

        $table = $table_map[$table_key];
        $deleted = 0;

        foreach ($ids as $id) {
            $result = $this->wpdb->delete($table, ['id' => (int) $id], ['%d']);
            if ($result) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
