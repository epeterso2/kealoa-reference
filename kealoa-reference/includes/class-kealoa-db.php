<?php
/**
 * @copyright 2026 Eric Peterson (eric@puzzlehead.org)
 * @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
 *
 * Database Access Layer
 *
 * Provides CRUD operations and query methods for all KEALOA entities.
 *
 * @package KEALOA_Reference
 */

declare(strict_types=1);

namespace com\epeterso2\kealoa;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Kealoa_DB
 *
 * Handles all database operations for the KEALOA Reference plugin.
 * Uses a unified persons table for players, constructors, and editors.
 */
class Kealoa_DB {

    /** @var array<int, int[]>|null Resolved alias map (person_id => all IDs in group), lazily built. */
    private ?array $alias_id_map = null;

    /** @var array<string, int>|null Cached rounds-per-date counts for request-level memoization. */
    private ?array $rounds_per_date_cache = null;

    private \wpdb $wpdb;
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
    // PERSON ALIAS HELPERS
    // =========================================================================

    /**
     * Resolve the alias map from the kealoa_person_aliases option (lazily, once per request).
     *
     * The option stores an array of arrays, where each inner array is a list
     * of person IDs that should be treated as a single person in detail views.
     *
     * Builds a map of person_id => sorted array of all person IDs in the same
     * alias group. Non-aliased persons are not in the map.
     */
    private function resolve_alias_map(): void {
        if ($this->alias_id_map !== null) {
            return;
        }
        $this->alias_id_map = [];
        $groups = get_option('kealoa_person_aliases', []);
        if (!is_array($groups)) {
            return;
        }
        foreach ($groups as $group) {
            if (!is_array($group) || count($group) < 2) {
                continue;
            }
            $ids = array_map('intval', $group);
            sort($ids);
            foreach ($ids as $id) {
                $this->alias_id_map[$id] = $ids;
            }
        }
    }

    /**
     * Invalidate the cached alias map so it is rebuilt on next access.
     *
     * Call this after saving alias groups via the admin panel.
     */
    public function flush_alias_map(): void {
        $this->alias_id_map = null;
    }

    /**
     * Get all stored alias groups with person details.
     *
     * Returns an array of arrays, each containing person objects for one alias group.
     *
     * @return array<int, array<object>> Indexed by group position (0-based).
     */
    public function get_all_alias_groups(): array {
        $groups = get_option('kealoa_person_aliases', []);
        if (!is_array($groups)) {
            return [];
        }
        $result = [];
        foreach ($groups as $index => $group) {
            if (!is_array($group) || count($group) < 2) {
                continue;
            }
            $persons = [];
            foreach ($group as $person_id) {
                $person = $this->get_person((int) $person_id);
                if ($person) {
                    $persons[] = $person;
                }
            }
            if (count($persons) >= 2) {
                $result[$index] = $persons;
            }
        }
        return $result;
    }

    /**
     * Save alias groups to the database option.
     *
     * Expects an array of arrays of person IDs.
     *
     * @param array<array<int>> $groups
     */
    public function save_alias_groups(array $groups): void {
        // Normalize: sort each group, filter out empty/single-element groups.
        $clean = [];
        foreach ($groups as $group) {
            $ids = array_values(array_unique(array_map('intval', (array) $group)));
            if (count($ids) >= 2) {
                sort($ids);
                $clean[] = $ids;
            }
        }
        update_option('kealoa_person_aliases', $clean);
        $this->flush_alias_map();
    }

    /**
     * Get all person IDs in the alias group for the given person.
     *
     * If the person is not aliased, returns [$person_id].
     *
     * @param int $person_id
     * @return int[] Sorted array of person IDs (always at least one element).
     */
    public function get_alias_person_ids(int $person_id): array {
        $this->resolve_alias_map();
        return $this->alias_id_map[$person_id] ?? [$person_id];
    }

    /**
     * Build a SQL WHERE clause fragment for a column that may match one or
     * more person IDs.
     *
     * When $person_ids contains a single value the fragment is:
     *   column = 42
     * When it contains multiple values:
     *   column IN (5, 42)
     *
     * Values are cast to int for safety; no wpdb::prepare() wrapper is needed.
     *
     * @param string    $column     Fully-qualified column name (e.g. "g.guesser_person_id").
     * @param int|int[] $person_ids One ID or an array of IDs.
     * @return string SQL fragment (no leading AND/WHERE).
     */
    public function prepare_person_id_clause(string $column, int|array $person_ids): string {
        if (is_int($person_ids)) {
            $person_ids = [$person_ids];
        }
        $safe = array_map('intval', $person_ids);
        if (count($safe) === 1) {
            return "{$column} = {$safe[0]}";
        }
        return "{$column} IN (" . implode(', ', $safe) . ')';
    }

    // =========================================================================
    // PERSONS CRUD (unified: players, constructors, editors)
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
            $where[] = "(full_name LIKE %s OR nicknames LIKE %s)";
            $like = '%' . $this->wpdb->esc_like($args['search']) . '%';
            $values[] = $like;
            $values[] = $like;
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
            $like = '%' . $this->wpdb->esc_like($search) . '%';
            $sql .= $this->wpdb->prepare(
                " WHERE (full_name LIKE %s OR nicknames LIKE %s)",
                $like,
                $like
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
            'nicknames' => isset($data['nicknames'])
                ? sanitize_text_field($data['nicknames'])
                : null,
            'home_page_url' => isset($data['home_page_url'])
                ? esc_url_raw($data['home_page_url'])
                : null,
            'image_url' => isset($data['image_url'])
                ? esc_url_raw($data['image_url'])
                : null,
            'hide_xwordinfo' => !empty($data['hide_xwordinfo']) ? 1 : 0,
            'xwordinfo_profile_name' => isset($data['xwordinfo_profile_name'])
                ? sanitize_text_field($data['xwordinfo_profile_name'])
                : null,
            'xwordinfo_image_url' => isset($data['xwordinfo_image_url'])
                ? esc_url_raw($data['xwordinfo_image_url'])
                : null,
        ];
        $format = ['%s', '%s', '%s', '%s', '%d', '%s', '%s'];

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
        if (array_key_exists('nicknames', $data)) {
            $update_data['nicknames'] = $data['nicknames']
                ? sanitize_text_field($data['nicknames'])
                : null;
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

    /**
     * Merge one or more source persons into a single target person.
     *
     * Reassigns all foreign-key references from each source person to the
     * target, handling UNIQUE constraints on junction tables by deleting
     * duplicate links rather than creating conflicts.  After reassignment,
     * deletes the source person records.
     *
     * @param int   $target_id  The person ID to keep.
     * @param int[] $source_ids Person IDs to merge into the target.
     * @return int Number of source persons successfully merged and deleted.
     */
    public function merge_persons(int $target_id, array $source_ids): int {
        if (empty($source_ids) || !$target_id) {
            return 0;
        }

        // Remove target from sources if accidentally included
        $source_ids = array_filter($source_ids, fn($id) => (int) $id !== $target_id);
        if (empty($source_ids)) {
            return 0;
        }

        $merged = 0;

        foreach ($source_ids as $source_id) {
            $source_id = (int) $source_id;

            // 1. puzzles.editor_id — simple FK, just update
            $this->wpdb->update(
                $this->puzzles_table,
                ['editor_id' => $target_id],
                ['editor_id' => $source_id],
                ['%d'],
                ['%d']
            );

            // 2. rounds.clue_giver_id — simple FK, just update
            $this->wpdb->update(
                $this->rounds_table,
                ['clue_giver_id' => $target_id],
                ['clue_giver_id' => $source_id],
                ['%d'],
                ['%d']
            );

            // 3. guesses.guesser_person_id — simple FK, just update
            $this->wpdb->update(
                $this->guesses_table,
                ['guesser_person_id' => $target_id],
                ['guesser_person_id' => $source_id],
                ['%d'],
                ['%d']
            );

            // 4. puzzle_constructors — UNIQUE(puzzle_id, person_id)
            //    Delete source rows that would conflict, then update the rest.
            $this->wpdb->query($this->wpdb->prepare(
                "DELETE pc_source FROM {$this->puzzle_constructors_table} pc_source
                 INNER JOIN {$this->puzzle_constructors_table} pc_target
                    ON pc_source.puzzle_id = pc_target.puzzle_id
                    AND pc_target.person_id = %d
                 WHERE pc_source.person_id = %d",
                $target_id,
                $source_id
            ));
            $this->wpdb->update(
                $this->puzzle_constructors_table,
                ['person_id' => $target_id],
                ['person_id' => $source_id],
                ['%d'],
                ['%d']
            );

            // 5. round_guessers — UNIQUE(round_id, person_id)
            //    Delete source rows that would conflict, then update the rest.
            $this->wpdb->query($this->wpdb->prepare(
                "DELETE rg_source FROM {$this->round_guessers_table} rg_source
                 INNER JOIN {$this->round_guessers_table} rg_target
                    ON rg_source.round_id = rg_target.round_id
                    AND rg_target.person_id = %d
                 WHERE rg_source.person_id = %d",
                $target_id,
                $source_id
            ));
            $this->wpdb->update(
                $this->round_guessers_table,
                ['person_id' => $target_id],
                ['person_id' => $source_id],
                ['%d'],
                ['%d']
            );

            // 6. Delete the source person record
            if ($this->delete_person($source_id)) {
                $merged++;
            }
        }

        return $merged;
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
                "SELECT DISTINCT p.*, ed.full_name as editor_name FROM {$this->puzzles_table} p
                INNER JOIN {$this->puzzle_constructors_table} pc ON p.id = pc.puzzle_id
                INNER JOIN {$this->persons_table} per ON pc.person_id = per.id
                LEFT JOIN {$this->persons_table} ed ON p.editor_id = ed.id
                WHERE per.full_name LIKE %s
                ORDER BY p.{$orderby} {$order} LIMIT %d OFFSET %d",
                '%' . $this->wpdb->esc_like($args['constructor_search']) . '%',
                $args['limit'],
                $args['offset']
            );
        } else {
            $sql = $this->wpdb->prepare(
                "SELECT p.*, ed.full_name as editor_name FROM {$this->puzzles_table} p
                LEFT JOIN {$this->persons_table} ed ON p.editor_id = ed.id
                ORDER BY p.{$orderby} {$order} LIMIT %d OFFSET %d",
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
                INNER JOIN {$this->persons_table} per ON pc.person_id = per.id
                WHERE per.full_name LIKE %s",
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

        if (array_key_exists('editor_id', $data)) {
            $insert_data['editor_id'] = $data['editor_id'] ? (int) $data['editor_id'] : null;
            $format[] = '%d';
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

        if (array_key_exists('editor_id', $data)) {
            $update_data['editor_id'] = $data['editor_id'] ? (int) $data['editor_id'] : null;
            $format[] = '%d';
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
     * Auto-populate editor IDs for all puzzles based on historical date ranges.
     *
     * Finds or creates person records for each editor name, then updates
     * puzzles.editor_id for the corresponding date ranges.
     *
     * @return int Number of puzzles updated
     */
    public function auto_populate_editor_ids(): int {
        $updated = 0;
        foreach (self::get_editor_date_ranges() as $editor) {
            // Find or create person for this editor
            $person = $this->get_person_by_name($editor['name']);
            if (!$person) {
                $person_id = $this->create_person(['full_name' => $editor['name']]);
                if (!$person_id) {
                    continue;
                }
            } else {
                $person_id = (int) $person->id;
            }

            $result = $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$this->puzzles_table}
                     SET editor_id = %d
                     WHERE publication_date >= %s AND publication_date <= %s",
                    $person_id,
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
     * Get clues for a puzzle, with round details
     */
    public function get_puzzle_clues(int $puzzle_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT c.*,
                r.id as round_id,
                r.game_number as round_game_number,
                r.round_date,
                r.round_number,
                r.episode_number
            FROM {$this->clues_table} c
            LEFT JOIN {$this->rounds_table} r ON c.round_id = r.id
            WHERE c.puzzle_id = %d
            ORDER BY r.round_date ASC, r.round_number ASC, c.clue_number ASC",
            $puzzle_id
        );

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get player results for clues from a specific puzzle
     */
    public function get_puzzle_player_results(int $puzzle_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT
                p.id as person_id,
                p.full_name,
                COUNT(g.id) as total_guesses,
                COALESCE(SUM(g.is_correct), 0) as correct_guesses
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            INNER JOIN {$this->persons_table} p ON g.guesser_person_id = p.id
            WHERE c.puzzle_id = %d
            GROUP BY p.id, p.full_name
            ORDER BY (COALESCE(SUM(g.is_correct), 0) / COUNT(g.id)) DESC, COUNT(g.id) DESC",
            $puzzle_id
        );

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get guess stats per clue position for a specific puzzle.
     *
     * Returns an array of objects keyed by "{number}_{direction}" with
     * total_guesses and correct_guesses fields.
     *
     * @param int $puzzle_id The puzzle ID.
     * @return array<string, object> Map of position key => stats object.
     */
    public function get_puzzle_clue_stats(int $puzzle_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT
                c.puzzle_clue_number,
                c.puzzle_clue_direction,
                COUNT(g.id) as total_guesses,
                COALESCE(SUM(g.is_correct), 0) as correct_guesses
            FROM {$this->clues_table} c
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
            WHERE c.puzzle_id = %d
            GROUP BY c.puzzle_clue_number, c.puzzle_clue_direction
            ORDER BY c.puzzle_clue_number ASC, c.puzzle_clue_direction ASC",
            $puzzle_id
        );

        $rows = $this->wpdb->get_results($sql);
        $map = [];
        foreach ($rows as $row) {
            $key = $row->puzzle_clue_number . '_' . $row->puzzle_clue_direction;
            $map[$key] = $row;
        }
        return $map;
    }

    /**
     * Get guess stats per puzzle for multiple puzzles in a single query.
     *
     * @param int[] $puzzle_ids Array of puzzle IDs.
     * @return array<int, object> Map of puzzle_id => stats object with total_guesses and correct_guesses.
     */
    public function get_puzzle_guess_stats_bulk(array $puzzle_ids): array {
        $map = [];
        foreach ($puzzle_ids as $pid) {
            $map[(int) $pid] = (object) ['total_guesses' => 0, 'correct_guesses' => 0];
        }
        if (empty($puzzle_ids)) {
            return $map;
        }
        $in = $this->ids_in_clause($puzzle_ids);
        $sql = "SELECT
                    c.puzzle_id,
                    COUNT(g.id) as total_guesses,
                    COALESCE(SUM(g.is_correct), 0) as correct_guesses
                FROM {$this->clues_table} c
                LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
                WHERE c.puzzle_id IN ({$in})
                GROUP BY c.puzzle_id";
        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            $map[(int) $row->puzzle_id] = $row;
        }
        return $map;
    }

    /**
     * Get constructors (persons) for a puzzle
     */
    public function get_puzzle_constructors(int $puzzle_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT p.* FROM {$this->persons_table} p
            INNER JOIN {$this->puzzle_constructors_table} pc ON p.id = pc.person_id
            WHERE pc.puzzle_id = %d
            ORDER BY pc.constructor_order ASC",
            $puzzle_id
        );

        return $this->wpdb->get_results($sql);
    }

    /**
     * Set constructors (persons) for a puzzle
     */
    public function set_puzzle_constructors(int $puzzle_id, array $person_ids): bool {
        // Delete existing constructors
        $this->wpdb->delete($this->puzzle_constructors_table, ['puzzle_id' => $puzzle_id], ['%d']);

        // Insert new constructors
        $order = 1;
        foreach ($person_ids as $person_id) {
            $this->wpdb->insert(
                $this->puzzle_constructors_table,
                [
                    'puzzle_id' => $puzzle_id,
                    'person_id' => (int) $person_id,
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
     * Get a round by game number
     */
    public function get_round_by_game_number(int $game_number): ?object {
        $sql = $this->wpdb->prepare(
            "SELECT r.*, p.full_name as clue_giver_name
            FROM {$this->rounds_table} r
            LEFT JOIN {$this->persons_table} p ON r.clue_giver_id = p.id
            WHERE r.game_number = %d",
            $game_number
        );
        $result = $this->wpdb->get_row($sql);
        return $result ?: null;
    }

    /**
     * Get the previous round (by date desc, round_number desc)
     *
     * @param int         $current_round_id The current round ID.
     * @param object|null $current          Optional pre-loaded round object to avoid an extra query.
     */
    public function get_previous_round(int $current_round_id, ?object $current = null): ?object {
        if (!$current) {
            $current = $this->get_round($current_round_id);
        }
        if (!$current) {
            return null;
        }
        $sql = $this->wpdb->prepare(
            "SELECT id, game_number FROM {$this->rounds_table}
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
     *
     * @param int         $current_round_id The current round ID.
     * @param object|null $current          Optional pre-loaded round object to avoid an extra query.
     */
    public function get_next_round(int $current_round_id, ?object $current = null): ?object {
        if (!$current) {
            $current = $this->get_round($current_round_id);
        }
        if (!$current) {
            return null;
        }
        $sql = $this->wpdb->prepare(
            "SELECT id, game_number FROM {$this->rounds_table}
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

        $allowed_orderby = ['id', 'game_number', 'round_date', 'episode_number', 'created_at'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'round_date';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Add secondary sort by round_number when ordering by date or game_number
        $secondary_sort = in_array($orderby, ['round_date', 'game_number']) ? ', r.round_number ASC' : '';

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
        $row = $this->wpdb->get_row(
            "SELECT
                (SELECT COUNT(*) FROM {$this->rounds_table}) as total_rounds,
                (SELECT COUNT(DISTINCT person_id) FROM {$this->round_guessers_table}) as total_players,
                (SELECT COUNT(*) FROM {$this->clues_table}) as total_clues,
                (SELECT COUNT(DISTINCT puzzle_id) FROM {$this->clues_table} WHERE puzzle_id IS NOT NULL) as total_puzzles,
                (SELECT COUNT(DISTINCT pc.person_id) FROM {$this->puzzle_constructors_table} pc
                    INNER JOIN {$this->clues_table} c ON c.puzzle_id = pc.puzzle_id) as total_constructors,
                (SELECT COUNT(*) FROM {$this->guesses_table}) as total_guesses,
                (SELECT COALESCE(SUM(is_correct), 0) FROM {$this->guesses_table}) as total_correct"
        );

        $total_guesses = (int) ($row->total_guesses ?? 0);
        $total_correct = (int) ($row->total_correct ?? 0);

        return (object) [
            'total_players' => (int) ($row->total_players ?? 0),
            'total_rounds' => (int) ($row->total_rounds ?? 0),
            'total_clues' => (int) ($row->total_clues ?? 0),
            'total_puzzles' => (int) ($row->total_puzzles ?? 0),
            'total_constructors' => (int) ($row->total_constructors ?? 0),
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
     * Get answer-to-answer transition counts for consecutive clue positions.
     *
     * For each pair of consecutive clues in a round, records which solution
     * word was the correct answer at position k-1 (from_answer) and which
     * was correct at position k (to_answer), partitioned by solution count.
     *
     * @return array Array of objects with solution_count, to_clue_number, from_answer, to_answer, transition_count.
     */
    public function get_answer_transition_counts(): array {
        $sql = "SELECT
                sc.solution_count,
                c2.clue_number AS to_clue_number,
                rs1.word_order AS from_answer,
                rs2.word_order AS to_answer,
                COUNT(*) AS transition_count
            FROM {$this->clues_table} c2
            INNER JOIN {$this->clues_table} c1
                ON c1.round_id = c2.round_id
                AND c1.clue_number = c2.clue_number - 1
            INNER JOIN {$this->round_solutions_table} rs1
                ON rs1.round_id = c1.round_id
                AND UPPER(rs1.word) = UPPER(c1.correct_answer)
            INNER JOIN {$this->round_solutions_table} rs2
                ON rs2.round_id = c2.round_id
                AND UPPER(rs2.word) = UPPER(c2.correct_answer)
            INNER JOIN (
                SELECT round_id, COUNT(*) AS solution_count
                FROM {$this->round_solutions_table}
                GROUP BY round_id
            ) sc ON sc.round_id = c1.round_id
            GROUP BY sc.solution_count, c2.clue_number, rs1.word_order, rs2.word_order
            ORDER BY sc.solution_count ASC, c2.clue_number ASC, rs1.word_order ASC, rs2.word_order ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get the full sequence of correct answers (as word_order) for every round.
     *
     * Returns rows ordered by solution count, round, and clue number so that
     * consecutive rows for the same round form the complete answer sequence.
     *
     * @return array Array of objects with solution_count, round_id, clue_number, word_order.
     */
    public function get_round_clue_sequences(): array {
        $sql = "SELECT
                sc.solution_count,
                c.round_id,
                c.clue_number,
                rs.word_order
            FROM {$this->clues_table} c
            INNER JOIN {$this->round_solutions_table} rs
                ON rs.round_id = c.round_id
                AND UPPER(rs.word) = UPPER(c.correct_answer)
            INNER JOIN (
                SELECT round_id, COUNT(*) AS solution_count
                FROM {$this->round_solutions_table}
                GROUP BY round_id
            ) sc ON sc.round_id = c.round_id
            ORDER BY sc.solution_count ASC, c.round_id ASC, c.clue_number ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Create a round
     */
    public function create_round(array $data): int|false {
        $insert_data = [
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
        ];
        $format = ['%s', '%d', '%d', '%d', '%s', '%d', '%d', '%s', '%s'];

        // Auto-assign next game_number unless explicitly provided
        if (isset($data['game_number']) && $data['game_number'] !== '' && $data['game_number'] !== null) {
            $insert_data['game_number'] = (int) $data['game_number'];
            $format[] = '%d';
        } else {
            $insert_data['game_number'] = $this->get_next_game_number();
            $format[] = '%d';
        }

        $result = $this->wpdb->insert(
            $this->rounds_table,
            $insert_data,
            $format
        );

        $this->rounds_per_date_cache = null;
        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update a round
     */
    public function update_round(int $id, array $data): bool {
        $update_data = [];
        $format = [];

        if (isset($data['game_number'])) {
            $update_data['game_number'] = (int) $data['game_number'];
            $format[] = '%d';
        }
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

        $this->rounds_per_date_cache = null;
        return $result !== false;
    }

    /**
     * Delete a round
     *
     * Removes the round and all related data (clues, guesses, solutions,
     * guessers). If the round has a game_number, decrements game_number
     * for all rounds above the deleted one to preserve continuity.
     */
    public function delete_round(int $id): bool {
        // Capture game_number before deletion
        $round = $this->get_round($id);
        $game_number = $round ? (int) ($round->game_number ?? 0) : 0;

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

        // Close the gap: decrement game_number for all rounds above the deleted one
        if ($result !== false && $game_number > 0) {
            $this->wpdb->query(
                $this->wpdb->prepare(
                    "UPDATE {$this->rounds_table} SET game_number = game_number - 1 WHERE game_number > %d ORDER BY game_number ASC",
                    $game_number
                )
            );
        }

        $this->rounds_per_date_cache = null;
        return $result !== false;
    }

    /**
     * Get the next available game_number (MAX + 1).
     */
    public function get_next_game_number(): int {
        $max = (int) $this->wpdb->get_var(
            "SELECT COALESCE(MAX(game_number), 0) FROM {$this->rounds_table}"
        );
        return $max + 1;
    }

    /**
     * Insert a round after a given game_number.
     *
     * Increments all game_numbers above the target to open a slot,
     * then creates a new empty round at game_number = $after + 1.
     *
     * @param int $after The game_number after which the new round is inserted.
     * @return int|false The new round ID, or false on failure.
     */
    public function insert_round_after_game_number(int $after): int|false {
        // Shift all rounds with game_number > $after up by 1
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->rounds_table} SET game_number = game_number + 1 WHERE game_number > %d ORDER BY game_number DESC",
                $after
            )
        );

        // The new round gets game_number = $after + 1
        return $after + 1;
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
     * Get only the correct_answer for a clue (lightweight, no JOINs).
     */
    public function get_clue_correct_answer(int $clue_id): ?string {
        return $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT correct_answer FROM {$this->clues_table} WHERE id = %d",
                $clue_id
            )
        );
    }

    /**
     * Get a clue by ID
     */
    public function get_clue(int $id): ?object {
        $sql = $this->wpdb->prepare(
            "SELECT c.*, pz.publication_date as puzzle_date, pz.editor_id, ed.full_name as editor_name
            FROM {$this->clues_table} c
            LEFT JOIN {$this->puzzles_table} pz ON c.puzzle_id = pz.id
            LEFT JOIN {$this->persons_table} ed ON pz.editor_id = ed.id
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
            "SELECT c.*, pz.publication_date as puzzle_date, pz.editor_id, ed.full_name as editor_name
            FROM {$this->clues_table} c
            LEFT JOIN {$this->puzzles_table} pz ON c.puzzle_id = pz.id
            LEFT JOIN {$this->persons_table} ed ON pz.editor_id = ed.id
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
     *
     * @param int         $clue_id           Clue ID.
     * @param int         $guesser_person_id Person ID of the guesser.
     * @param string      $guessed_word      The guessed word.
     * @param string|null $correct_answer     Pre-fetched correct answer to avoid re-querying.
     */
    public function set_guess(int $clue_id, int $guesser_person_id, string $guessed_word, ?string $correct_answer = null): bool {
        if ($correct_answer === null) {
            $correct_answer = $this->get_clue_correct_answer($clue_id);
            if ($correct_answer === null) {
                return false;
            }
        }

        $guessed_word = strtoupper(sanitize_text_field($guessed_word));
        $is_correct = ($guessed_word === $correct_answer) ? 1 : 0;

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

    // =========================================================================
    // BULK-FETCH METHODS
    // =========================================================================

    /**
     * Build a safe comma-separated list of integer IDs for use in IN clauses.
     *
     * @param int[] $ids Array of integer IDs.
     * @return string Comma-separated integer string (e.g. "1,2,3").
     */
    private function ids_in_clause(array $ids): string {
        return implode(',', array_map('intval', $ids));
    }

    /**
     * Get solution words for multiple rounds in a single query.
     *
     * @param int[] $round_ids Array of round IDs.
     * @return array<int, array> Map of round_id => array of solution objects.
     */
    public function get_rounds_bulk(array $round_ids): array {
        $map = [];
        foreach ($round_ids as $rid) {
            $map[(int) $rid] = null;
        }
        if (empty($round_ids)) {
            return $map;
        }
        $in = $this->ids_in_clause($round_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $this->wpdb->get_results(
            "SELECT r.*, p.full_name AS clue_giver_name
            FROM {$this->rounds_table} r
            LEFT JOIN {$this->persons_table} p ON r.clue_giver_id = p.id
            WHERE r.id IN ($in)"
        );
        foreach ($rows as $row) {
            $map[(int) $row->id] = $row;
        }
        return $map;
    }

    /**
     * Get solution words for multiple rounds in a single query.
     *
     * @param int[] $round_ids Array of round IDs.
     * @return array<int, array> Map of round_id => array of solution objects.
     */
    public function get_round_solutions_bulk(array $round_ids): array {
        $map = [];
        foreach ($round_ids as $rid) {
            $map[(int) $rid] = [];
        }
        if (empty($round_ids)) {
            return $map;
        }
        $in = $this->ids_in_clause($round_ids);
        $sql = "SELECT * FROM {$this->round_solutions_table}
                WHERE round_id IN ({$in})
                ORDER BY round_id, word_order ASC";
        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            $map[(int) $row->round_id][] = $row;
        }
        return $map;
    }

    /**
     * Get constructors for multiple puzzles in a single query.
     *
     * @param int[] $puzzle_ids Array of puzzle IDs.
     * @return array<int, array> Map of puzzle_id => array of person objects.
     */
    public function get_puzzle_constructors_bulk(array $puzzle_ids): array {
        $map = [];
        foreach ($puzzle_ids as $pid) {
            $map[(int) $pid] = [];
        }
        if (empty($puzzle_ids)) {
            return $map;
        }
        $in = $this->ids_in_clause($puzzle_ids);
        $sql = "SELECT p.*, pc.puzzle_id FROM {$this->persons_table} p
                INNER JOIN {$this->puzzle_constructors_table} pc ON p.id = pc.person_id
                WHERE pc.puzzle_id IN ({$in})
                ORDER BY pc.puzzle_id, pc.constructor_order ASC";
        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            $map[(int) $row->puzzle_id][] = $row;
        }
        return $map;
    }

    /**
     * Get guesses for multiple clues in a single query.
     *
     * @param int[] $clue_ids Array of clue IDs.
     * @return array<int, array> Map of clue_id => array of guess objects.
     */
    public function get_clue_guesses_bulk(array $clue_ids): array {
        $map = [];
        foreach ($clue_ids as $cid) {
            $map[(int) $cid] = [];
        }
        if (empty($clue_ids)) {
            return $map;
        }
        $in = $this->ids_in_clause($clue_ids);
        $sql = "SELECT g.*, p.full_name as guesser_name
                FROM {$this->guesses_table} g
                INNER JOIN {$this->persons_table} p ON g.guesser_person_id = p.id
                WHERE g.clue_id IN ({$in})
                ORDER BY g.clue_id, p.full_name ASC";
        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            $map[(int) $row->clue_id][] = $row;
        }
        return $map;
    }

    /**
     * Get guesser results for multiple rounds in a single query.
     *
     * @param int[] $round_ids Array of round IDs.
     * @return array<int, array> Map of round_id => array of result objects
     *                           (person_id, full_name, total_guesses, correct_guesses).
     */
    public function get_round_guesser_results_bulk(array $round_ids): array {
        $map = [];
        foreach ($round_ids as $rid) {
            $map[(int) $rid] = [];
        }
        if (empty($round_ids)) {
            return $map;
        }
        $in = $this->ids_in_clause($round_ids);
        $sql = "SELECT
                    rg.round_id,
                    p.id as person_id,
                    p.full_name,
                    COUNT(g.id) as total_guesses,
                    COALESCE(SUM(g.is_correct), 0) as correct_guesses
                FROM {$this->round_guessers_table} rg
                INNER JOIN {$this->persons_table} p ON p.id = rg.person_id
                LEFT JOIN {$this->clues_table} c ON c.round_id = rg.round_id
                LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id AND g.guesser_person_id = p.id
                WHERE rg.round_id IN ({$in})
                GROUP BY rg.round_id, p.id, p.full_name
                ORDER BY rg.round_id, p.full_name ASC";
        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            $map[(int) $row->round_id][] = $row;
        }
        return $map;
    }

    /**
     * Get clue counts for multiple rounds in a single query.
     *
     * @param int[] $round_ids Array of round IDs.
     * @return array<int, int> Map of round_id => clue count.
     */
    public function get_round_clue_counts_bulk(array $round_ids): array {
        $map = [];
        foreach ($round_ids as $rid) {
            $map[(int) $rid] = 0;
        }
        if (empty($round_ids)) {
            return $map;
        }
        $in = $this->ids_in_clause($round_ids);
        $sql = "SELECT round_id, COUNT(*) as cnt
                FROM {$this->clues_table}
                WHERE round_id IN ({$in})
                GROUP BY round_id";
        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            $map[(int) $row->round_id] = (int) $row->cnt;
        }
        return $map;
    }

    /**
     * Get the number of rounds per date across all dates.
     *
     * @return array<string, int> Map of date string => round count.
     */
    public function get_rounds_per_date_counts(): array {
        if ($this->rounds_per_date_cache !== null) {
            return $this->rounds_per_date_cache;
        }

        $sql = "SELECT round_date, COUNT(*) as cnt
                FROM {$this->rounds_table}
                GROUP BY round_date";
        $rows = $this->wpdb->get_results($sql);
        $map = [];
        foreach ($rows as $row) {
            $map[$row->round_date] = (int) $row->cnt;
        }

        $this->rounds_per_date_cache = $map;
        return $map;
    }

    /**
     * Get guessers (person objects) for multiple rounds in a single query.
     *
     * @param int[] $round_ids Array of round IDs.
     * @return array<int, array> Map of round_id => array of person objects.
     */
    public function get_round_guessers_bulk(array $round_ids): array {
        $map = [];
        foreach ($round_ids as $rid) {
            $map[(int) $rid] = [];
        }
        if (empty($round_ids)) {
            return $map;
        }
        $in = $this->ids_in_clause($round_ids);
        $sql = "SELECT p.*, rg.round_id
                FROM {$this->persons_table} p
                INNER JOIN {$this->round_guessers_table} rg ON p.id = rg.person_id
                WHERE rg.round_id IN ({$in})
                ORDER BY rg.round_id, p.full_name ASC";
        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            $map[(int) $row->round_id][] = $row;
        }
        return $map;
    }

    /**
     * Get Alternation percentages for multiple rounds in a single query.
     *
     * Fetches all correct_answer values for the given rounds, then
     * computes the run-count metric per round in PHP.
     *
     * @param int[] $round_ids Array of round IDs.
     * @return array<int, float> Map of round_id => alternation percentage (0-100).
     */
    public function get_round_alternation_pcts_bulk(array $round_ids): array {
        $map = [];
        foreach ($round_ids as $rid) {
            $map[(int) $rid] = 0.0;
        }
        if (empty($round_ids)) {
            return $map;
        }
        $in = $this->ids_in_clause($round_ids);
        $sql = "SELECT round_id, correct_answer
                FROM {$this->clues_table}
                WHERE round_id IN ({$in})
                ORDER BY round_id, clue_number ASC";
        $rows = $this->wpdb->get_results($sql);

        // Group answers by round_id
        $answers_by_round = [];
        foreach ($rows as $row) {
            $answers_by_round[(int) $row->round_id][] = $row->correct_answer;
        }

        // Compute alternation per round
        foreach ($answers_by_round as $rid => $answers) {
            $n = count($answers);
            if ($n < 2) {
                $map[$rid] = 0.0;
                continue;
            }
            $runs = 1;
            for ($i = 1; $i < $n; $i++) {
                if ($answers[$i] !== $answers[$i - 1]) {
                    $runs++;
                }
            }
            $map[$rid] = (($runs - 1) / ($n - 1)) * 100;
        }
        return $map;
    }

    /**
     * Get per-player guess alternation for a single round.
     *
     * For each guesser in the round, orders their guessed_word values
     * by clue_number and computes the Wald-Wolfowitz run-count metric
     * on the guess sequence.  This mirrors get_round_alternation_pct()
     * but operates on guesses instead of correct answers.
     *
     * @param int $round_id The round ID.
     * @return array Array of objects with person_id, full_name, alternation_pct.
     */
    public function get_round_guess_alternation_per_player(int $round_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT g.guesser_person_id, p.full_name, g.guessed_word, c.clue_number
             FROM {$this->guesses_table} g
             INNER JOIN {$this->clues_table} c ON c.id = g.clue_id
             INNER JOIN {$this->persons_table} p ON p.id = g.guesser_person_id
             WHERE c.round_id = %d
             ORDER BY g.guesser_person_id, c.clue_number ASC",
            $round_id
        );
        $rows = $this->wpdb->get_results($sql);

        // Group guessed words by person
        $words_by_person = [];
        $names = [];
        foreach ($rows as $row) {
            $pid = (int) $row->guesser_person_id;
            $words_by_person[$pid][] = $row->guessed_word;
            $names[$pid] = $row->full_name;
        }

        $results = [];
        foreach ($words_by_person as $pid => $words) {
            $n = count($words);
            if ($n < 2) {
                $alt_pct = 0.0;
            } else {
                $runs = 1;
                for ($i = 1; $i < $n; $i++) {
                    if ($words[$i] !== $words[$i - 1]) {
                        $runs++;
                    }
                }
                $alt_pct = (($runs - 1) / ($n - 1)) * 100;
            }
            $results[] = (object) [
                'person_id'       => $pid,
                'full_name'       => $names[$pid],
                'alternation_pct' => $alt_pct,
            ];
        }
        return $results;
    }

    /**
     * Get per-player guess alternation for multiple rounds in a single query.
     *
     * Bulk version of get_round_guess_alternation_per_player().
     *
     * @param int[] $round_ids Array of round IDs.
     * @return array<int, array> Map of round_id => [objects with person_id, alternation_pct].
     */
    public function get_round_guess_alternation_per_player_bulk(array $round_ids): array {
        $map = [];
        foreach ($round_ids as $rid) {
            $map[(int) $rid] = [];
        }
        if (empty($round_ids)) {
            return $map;
        }
        $in = $this->ids_in_clause($round_ids);
        $sql = "SELECT c.round_id, g.guesser_person_id, g.guessed_word, c.clue_number
                FROM {$this->guesses_table} g
                INNER JOIN {$this->clues_table} c ON c.id = g.clue_id
                WHERE c.round_id IN ({$in})
                ORDER BY c.round_id, g.guesser_person_id, c.clue_number ASC";
        $rows = $this->wpdb->get_results($sql);

        // Group guessed words by (round_id, person_id)
        $words_by_round_person = [];
        foreach ($rows as $row) {
            $rid = (int) $row->round_id;
            $pid = (int) $row->guesser_person_id;
            $words_by_round_person[$rid][$pid][] = $row->guessed_word;
        }

        // Compute alternation per player per round
        foreach ($words_by_round_person as $rid => $by_person) {
            foreach ($by_person as $pid => $words) {
                $n = count($words);
                if ($n < 2) {
                    $alt_pct = 0.0;
                } else {
                    $runs = 1;
                    for ($i = 1; $i < $n; $i++) {
                        if ($words[$i] !== $words[$i - 1]) {
                            $runs++;
                        }
                    }
                    $alt_pct = (($runs - 1) / ($n - 1)) * 100;
                }
                $map[$rid][] = (object) [
                    'person_id'       => $pid,
                    'alternation_pct' => $alt_pct,
                ];
            }
        }
        return $map;
    }

    /**
     * Get per-player guess evenness for multiple rounds in a single query.
     *
     * For each guesser in each round, computes Pielou's Evenness Index
     * on the distribution of their guessed_word values among the round's
     * solution words.  This mirrors get_round_evenness_bulk() but
     * operates on guesses instead of correct answers.
     *
     * @param int[] $round_ids Array of round IDs.
     * @return array<int, array> Map of round_id => [objects with person_id, evenness_pct].
     */
    public function get_round_guess_evenness_per_player_bulk(array $round_ids): array {
        $map = [];
        foreach ($round_ids as $rid) {
            $map[(int) $rid] = [];
        }
        if (empty($round_ids)) {
            return $map;
        }
        $in = $this->ids_in_clause($round_ids);

        // Get the number of solution words per round
        $sol_sql = "SELECT round_id, COUNT(*) as word_count
                    FROM {$this->round_solutions_table}
                    WHERE round_id IN ({$in})
                    GROUP BY round_id";
        $sol_rows = $this->wpdb->get_results($sol_sql);
        $solution_counts = [];
        foreach ($sol_rows as $sr) {
            $solution_counts[(int) $sr->round_id] = (int) $sr->word_count;
        }

        // Count how many guesses each player gave for each guessed_word, per round
        $sql = "SELECT c.round_id, g.guesser_person_id, g.guessed_word, COUNT(*) as cnt
                FROM {$this->guesses_table} g
                INNER JOIN {$this->clues_table} c ON c.id = g.clue_id
                WHERE c.round_id IN ({$in})
                GROUP BY c.round_id, g.guesser_person_id, g.guessed_word";
        $rows = $this->wpdb->get_results($sql);

        // Group counts by (round_id, person_id)
        $counts_by_round_person = [];
        foreach ($rows as $row) {
            $rid = (int) $row->round_id;
            $pid = (int) $row->guesser_person_id;
            $counts_by_round_person[$rid][$pid][] = (int) $row->cnt;
        }

        // Compute Pielou's Evenness per player per round
        foreach ($counts_by_round_person as $rid => $by_person) {
            $s_count = $solution_counts[$rid] ?? 0;
            foreach ($by_person as $pid => $counts) {
                // Add zero-count entries for solution words not guessed
                $missing = $s_count - count($counts);
                for ($i = 0; $i < $missing; $i++) {
                    $counts[] = 0;
                }
                if ($s_count <= 1) {
                    $evenness = 100.0;
                } else {
                    $n_total = array_sum($counts);
                    $h_prime = 0.0;
                    foreach ($counts as $count) {
                        $p_i = $count / $n_total;
                        if ($p_i > 0) {
                            $h_prime -= $p_i * log($p_i);
                        }
                    }
                    $evenness = ($h_prime / log($s_count)) * 100;
                }
                $map[$rid][] = (object) [
                    'person_id'    => $pid,
                    'evenness_pct' => $evenness,
                ];
            }
        }
        return $map;
    }

    /**
     * Get guess alternation statistics broken down by clue number.
     *
     * For each clue number 2–10, returns the number of player-clue pairs
     * ("chances") and the number of times a player's guess differed from
     * their guess on the previous clue ("taken").  This mirrors
     * get_alternation_by_clue_number() but compares guessed_word
     * instead of correct_answer.
     *
     * @param int|int[]|null $clue_giver_id  Optional host person ID(s) to filter rounds.
     * @return array<int, object{clue_number: int, chances: int, taken: int}>
     */
    public function get_guess_alternation_by_clue_number(int|array|null $clue_giver_id = null): array {
        $host_join  = '';
        $host_where = '';
        if ($clue_giver_id !== null) {
            $host_join  = "INNER JOIN {$this->rounds_table} r ON c2.round_id = r.id";
            $host_where = 'AND ' . $this->prepare_person_id_clause('r.clue_giver_id', $clue_giver_id);
        }

        $sql = "SELECT
                    c2.clue_number,
                    COUNT(*) AS chances,
                    SUM(CASE WHEN g2.guessed_word != g1.guessed_word THEN 1 ELSE 0 END) AS taken
                FROM {$this->guesses_table} g2
                INNER JOIN {$this->clues_table} c2 ON c2.id = g2.clue_id
                INNER JOIN {$this->clues_table} c1
                    ON c1.round_id = c2.round_id
                    AND c1.clue_number = c2.clue_number - 1
                INNER JOIN {$this->guesses_table} g1
                    ON g1.clue_id = c1.id
                    AND g1.guesser_person_id = g2.guesser_person_id
                {$host_join}
                WHERE c2.clue_number BETWEEN 2 AND 10
                {$host_where}
                GROUP BY c2.clue_number
                ORDER BY c2.clue_number ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get Pielou's Evenness Index for multiple rounds in a single query.
     *
     * Evenness measures how evenly the correct answers are distributed
     * among the solution words. 100% means every solution word appears
     * equally often; lower values indicate imbalance.
     *
     * J' = H' / ln(S), where H' = -Σ(p_i * ln(p_i)) and S = number of
     * distinct solution words. Scaled to 0-100%.
     *
     * @param int[] $round_ids Array of round IDs.
     * @return array<int, float> Map of round_id => evenness percentage (0-100).
     */
    public function get_round_evenness_bulk(array $round_ids): array {
        $map = [];
        foreach ($round_ids as $rid) {
            $map[(int) $rid] = 0.0;
        }
        if (empty($round_ids)) {
            return $map;
        }
        $in = $this->ids_in_clause($round_ids);

        // Get the number of solution words per round
        $sol_sql = "SELECT round_id, COUNT(*) as word_count
                    FROM {$this->round_solutions_table}
                    WHERE round_id IN ({$in})
                    GROUP BY round_id";
        $sol_rows = $this->wpdb->get_results($sol_sql);
        $solution_counts = [];
        foreach ($sol_rows as $sr) {
            $solution_counts[(int) $sr->round_id] = (int) $sr->word_count;
        }

        // Count how many clues have each correct_answer, per round
        $sql = "SELECT round_id, correct_answer, COUNT(*) as cnt
                FROM {$this->clues_table}
                WHERE round_id IN ({$in})
                GROUP BY round_id, correct_answer";
        $rows = $this->wpdb->get_results($sql);

        // Group counts by round
        $counts_by_round = [];
        foreach ($rows as $row) {
            $counts_by_round[(int) $row->round_id][] = (int) $row->cnt;
        }

        // Compute Pielou's Evenness per round
        foreach ($counts_by_round as $rid => $counts) {
            // Use solution word count (includes words with zero clues)
            $s_count = $solution_counts[$rid] ?? count($counts);
            // Add zero-count entries for solution words not used as clue answers
            $missing = $s_count - count($counts);
            for ($i = 0; $i < $missing; $i++) {
                $counts[] = 0;
            }
            if ($s_count <= 1) {
                $map[$rid] = 100.0;
                continue;
            }
            $n_total = array_sum($counts);
            $h_prime = 0.0;
            foreach ($counts as $count) {
                $p_i = $count / $n_total;
                if ($p_i > 0) {
                    $h_prime -= $p_i * log($p_i);
                }
            }
            $map[$rid] = ($h_prime / log($s_count)) * 100;
        }
        return $map;
    }

    /**
     * Get mean clue age (in days) for multiple rounds in a single query.
     *
     * Clue age is the number of days between the round date and the
     * puzzle publication date of each clue.
     *
     * @param int[] $round_ids Array of round IDs.
     * @return array<int, object|null> Map of round_id => object with mean property, or null.
     */
    public function get_round_clue_age_stats_bulk(array $round_ids): array {
        $map = [];
        foreach ($round_ids as $rid) {
            $map[(int) $rid] = null;
        }
        if (empty($round_ids)) {
            return $map;
        }
        $in = $this->ids_in_clause($round_ids);
        $sql = "SELECT c.round_id, DATEDIFF(r.round_date, p.publication_date) AS age_days
                FROM {$this->clues_table} c
                INNER JOIN {$this->rounds_table} r ON r.id = c.round_id
                INNER JOIN {$this->puzzles_table} p ON p.id = c.puzzle_id
                WHERE c.round_id IN ({$in})
                ORDER BY c.round_id, c.clue_number ASC";
        $rows = $this->wpdb->get_results($sql);

        // Group ages by round_id
        $ages_by_round = [];
        foreach ($rows as $row) {
            $ages_by_round[(int) $row->round_id][] = (int) $row->age_days;
        }

        // Compute mean per round
        foreach ($ages_by_round as $rid => $ages) {
            $n = count($ages);
            if ($n === 0) {
                continue;
            }
            $mean = array_sum($ages) / $n;
            $sum_sq = 0.0;
            foreach ($ages as $age) {
                $sum_sq += ($age - $mean) ** 2;
            }
            $stddev = sqrt($sum_sq / $n);
            $map[$rid] = (object) [
                'mean'   => round($mean, 1),
                'stddev' => round($stddev, 1),
            ];
        }
        return $map;
    }

    /**
     * Get all person roles for all persons in bulk.
     *
     * @return array<int, string[]> Map of person_id => array of role strings.
     */
    public function get_all_person_roles(): array {
        // Players
        $player_ids = $this->wpdb->get_col(
            "SELECT DISTINCT person_id FROM {$this->round_guessers_table}"
        );
        // Constructors
        $constructor_ids = $this->wpdb->get_col(
            "SELECT DISTINCT person_id FROM {$this->puzzle_constructors_table}"
        );
        // Editors
        $editor_ids = $this->wpdb->get_col(
            "SELECT DISTINCT editor_id FROM {$this->puzzles_table} WHERE editor_id IS NOT NULL"
        );
        // Clue givers
        $clue_giver_ids = $this->wpdb->get_col(
            "SELECT DISTINCT clue_giver_id FROM {$this->rounds_table}"
        );

        $all_ids = array_unique(array_merge(
            array_map('intval', $player_ids),
            array_map('intval', $constructor_ids),
            array_map('intval', $editor_ids),
            array_map('intval', $clue_giver_ids)
        ));

        $map = [];
        foreach ($all_ids as $pid) {
            $map[$pid] = [];
        }
        foreach ($player_ids as $id) {
            $map[(int) $id][] = 'player';
        }
        foreach ($constructor_ids as $id) {
            $map[(int) $id][] = 'constructor';
        }
        foreach ($editor_ids as $id) {
            $map[(int) $id][] = 'editor';
        }
        foreach ($clue_giver_ids as $id) {
            $map[(int) $id][] = 'clue_giver';
        }
        return $map;
    }

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
     * Get the Alternation for a round (Wald-Wolfowitz run-count metric).
     *
     * A "run" is a maximal consecutive sequence of identical correct answers.
     * Alternation = ((r - 1) / (n - 1)) * 100, where r = number of runs and
     * n = number of clues.  Returns 0 when the round has fewer than 2 clues.
     *
     * @param int $round_id The round ID.
     * @return float The Alternation percentage (0-100).
     */
    public function get_round_alternation_pct(int $round_id): float {
        $sql = $this->wpdb->prepare(
            "SELECT correct_answer FROM {$this->clues_table} WHERE round_id = %d ORDER BY clue_number ASC",
            $round_id
        );
        $answers = $this->wpdb->get_col($sql);

        $n = count($answers);
        if ($n < 2) {
            return 0.0;
        }

        // Count the number of runs
        $runs = 1;
        for ($i = 1; $i < $n; $i++) {
            if ($answers[$i] !== $answers[$i - 1]) {
                $runs++;
            }
        }

        return (($runs - 1) / ($n - 1)) * 100;
    }

    /**
     * Get alternation statistics broken down by clue number.
     *
     * For each clue number 2–10, returns the number of rounds that had
     * that clue ("chances") and the number of times the answer differed
     * from the previous clue's answer ("taken").
     *
     * @param int|int[]|null $clue_giver_id  Optional host person ID(s) to filter rounds.
     * @return array<int, object{clue_number: int, chances: int, taken: int}>
     */
    public function get_alternation_by_clue_number(int|array|null $clue_giver_id = null): array {
        $host_join  = '';
        $host_where = '';
        if ($clue_giver_id !== null) {
            $host_join  = "INNER JOIN {$this->rounds_table} r ON c2.round_id = r.id";
            $host_where = 'AND ' . $this->prepare_person_id_clause('r.clue_giver_id', $clue_giver_id);
        }

        $sql = "SELECT
                    c2.clue_number,
                    COUNT(*) AS chances,
                    SUM(CASE WHEN c2.correct_answer != c1.correct_answer THEN 1 ELSE 0 END) AS taken
                FROM {$this->clues_table} c2
                INNER JOIN {$this->clues_table} c1
                    ON c1.round_id = c2.round_id
                    AND c1.clue_number = c2.clue_number - 1
                {$host_join}
                WHERE c2.clue_number BETWEEN 2 AND 10
                {$host_where}
                GROUP BY c2.clue_number
                ORDER BY c2.clue_number ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get the mean and standard deviation of clue ages for a round.
     *
     * Clue age is the number of days between the round date and the
     * puzzle publication date of each clue.  Clues without a linked
     * puzzle are excluded.
     *
     * @param int $round_id The round ID.
     * @return object|null Object with properties mean and stddev (both floats,
     *                     in days), or null when no clues have a linked puzzle.
     */
    public function get_round_clue_age_stats(int $round_id): ?object {
        $sql = $this->wpdb->prepare(
            "SELECT DATEDIFF(r.round_date, p.publication_date) AS age_days
             FROM {$this->clues_table} c
             INNER JOIN {$this->rounds_table} r ON r.id = c.round_id
             INNER JOIN {$this->puzzles_table} p ON p.id = c.puzzle_id
             WHERE c.round_id = %d
             ORDER BY c.clue_number ASC",
            $round_id
        );
        $rows = $this->wpdb->get_col($sql);

        $n = count($rows);
        if ($n === 0) {
            return null;
        }

        $ages = array_map('intval', $rows);
        $mean = array_sum($ages) / $n;

        $sum_sq = 0.0;
        foreach ($ages as $age) {
            $sum_sq += ($age - $mean) ** 2;
        }
        // Use population standard deviation (every clue in the round)
        $stddev = sqrt($sum_sq / $n);

        return (object) [
            'mean'   => round($mean, 1),
            'stddev' => round($stddev, 1),
        ];
    }

    /**
     * Get average clue age by clue position (clue number).
     *
     * Groups all clues by their clue_number and computes the average
     * age (in days, = round_date − publication_date) at each position.
     * Optionally filtered by clue-giver (host) person ID(s).
     *
     * @param int|int[]|null $clue_giver_id  Optional host person ID(s).
     * @return array<int, object{clue_number: int, rounds: int, avg_age: float, min_age: int, max_age: int}>
     */
    public function get_avg_clue_age_by_position(int|array|null $clue_giver_id = null): array {
        $host_where = '';
        if ($clue_giver_id !== null) {
            $host_where = 'AND ' . $this->prepare_person_id_clause('r.clue_giver_id', $clue_giver_id);
        }

        $sql = "SELECT
                    c.clue_number,
                    COUNT(*) AS rounds,
                    AVG(DATEDIFF(r.round_date, p.publication_date)) AS avg_age,
                    MIN(DATEDIFF(r.round_date, p.publication_date)) AS min_age,
                    MAX(DATEDIFF(r.round_date, p.publication_date)) AS max_age
                FROM {$this->clues_table} c
                INNER JOIN {$this->rounds_table} r ON r.id = c.round_id
                INNER JOIN {$this->puzzles_table} p ON p.id = c.puzzle_id
                WHERE 1=1
                {$host_where}
                GROUP BY c.clue_number
                ORDER BY c.clue_number ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get rounds where no clue has a linked puzzle.
     *
     * Returns round objects with clue_giver_name, ordered by round_date DESC.
     *
     * @return array Array of round objects.
     */
    public function get_rounds_without_puzzles(): array {
        $sql = "SELECT r.*, p.full_name AS clue_giver_name
            FROM {$this->rounds_table} r
            LEFT JOIN {$this->persons_table} p ON r.clue_giver_id = p.id
            WHERE NOT EXISTS (
                SELECT 1 FROM {$this->clues_table} c
                WHERE c.round_id = r.id AND c.puzzle_id IS NOT NULL
            )
            ORDER BY r.round_date DESC, r.round_number ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get per-round Alternation and accuracy data for all rounds.
     *
     * Returns an array of objects with round_id, total_guesses, and
     * correct_guesses.  The caller computes Alternation via
     * get_round_alternation_pct() for each round.
     *
     * @return array Array of objects with round_id, total_guesses, correct_guesses.
     */
    public function get_rounds_guess_stats(): array {
        $sql = "SELECT
                r.id AS round_id,
                COUNT(g.id) AS total_guesses,
                COALESCE(SUM(g.is_correct), 0) AS correct_guesses
            FROM {$this->rounds_table} r
            LEFT JOIN {$this->clues_table} c ON c.round_id = r.id
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
            GROUP BY r.id
            ORDER BY r.id ASC";

        return $this->wpdb->get_results($sql);
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
    public function get_person_stats(int|array $person_ids): object {
        $clause = $this->prepare_person_id_clause('g.guesser_person_id', $person_ids);
        // Single query fetches per-clue data; everything else is derived in PHP
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $clue_rows = $this->wpdb->get_results(
                "SELECT c.round_id, c.clue_number, g.is_correct
                FROM {$this->guesses_table} g
                INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
                INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
                WHERE {$clause}
                ORDER BY c.round_id ASC, c.clue_number ASC"
        );

        // Derive aggregate stats from clue-level data
        $total_clues_answered = count($clue_rows);
        $total_correct = 0;
        $per_round = []; // round_id => ['clue_count' => int, 'correct_count' => int]
        $best_streak = 0;
        $prev_round = null;
        $streak = 0;

        foreach ($clue_rows as $row) {
            $rid = (int) $row->round_id;
            $correct = (int) $row->is_correct;
            $total_correct += $correct;

            if (!isset($per_round[$rid])) {
                $per_round[$rid] = ['clue_count' => 0, 'correct_count' => 0];
            }
            $per_round[$rid]['clue_count']++;
            $per_round[$rid]['correct_count'] += $correct;

            // Streak tracking (reset on new round)
            if ($rid !== $prev_round) {
                $streak = 0;
                $prev_round = $rid;
            }
            if ($correct) {
                $streak++;
                if ($streak > $best_streak) {
                    $best_streak = $streak;
                }
            } else {
                $streak = 0;
            }
        }

        $rounds_played = count($per_round);
        $correct_counts = array_map(fn($r) => $r['correct_count'], array_values($per_round));
        $percentages = array_map(function ($r) {
            return $r['clue_count'] > 0 ? ($r['correct_count'] / $r['clue_count']) * 100 : 0;
        }, array_values($per_round));

        return (object) [
            'rounds_played' => $rounds_played,
            'total_clues_answered' => $total_clues_answered,
            'total_correct' => $total_correct,
            'overall_percentage' => $total_clues_answered > 0
                ? round(($total_correct / $total_clues_answered) * 100, 1)
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
    public function get_person_results_by_clue_number(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('g.guesser_person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                c.clue_number,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            WHERE {$clause}
            GROUP BY c.clue_number
            ORDER BY c.clue_number ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get person results grouped by answer length (alphanumeric characters only)
     */
    public function get_person_results_by_answer_length(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('g.guesser_person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                CHAR_LENGTH(REGEXP_REPLACE(c.correct_answer, '[^A-Za-z0-9]', '')) as answer_length,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            WHERE {$clause}
            GROUP BY CHAR_LENGTH(REGEXP_REPLACE(c.correct_answer, '[^A-Za-z0-9]', ''))
            ORDER BY CHAR_LENGTH(REGEXP_REPLACE(c.correct_answer, '[^A-Za-z0-9]', '')) ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get person results grouped by the number of answer words in the round
     */
    public function get_person_results_by_answer_word_count(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('g.guesser_person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                rs_count.word_count as answer_word_count,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            INNER JOIN (
                SELECT round_id, COUNT(*) as word_count
                FROM {$this->round_solutions_table}
                GROUP BY round_id
            ) rs_count ON rs_count.round_id = c.round_id
            WHERE {$clause}
            GROUP BY rs_count.word_count
            ORDER BY rs_count.word_count ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get person results by clue direction (Across vs Down)
     */
    public function get_person_results_by_direction(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('g.guesser_person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                c.puzzle_clue_direction as direction,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            WHERE {$clause}
            GROUP BY c.puzzle_clue_direction
            ORDER BY c.puzzle_clue_direction ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get person results by puzzle day of week
     */
    public function get_person_results_by_day_of_week(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('g.guesser_person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                DAYOFWEEK(pz.publication_date) as day_of_week,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            INNER JOIN {$this->puzzles_table} pz ON c.puzzle_id = pz.id
            WHERE {$clause}
            GROUP BY DAYOFWEEK(pz.publication_date)
            ORDER BY MOD(DAYOFWEEK(pz.publication_date) + 5, 7) ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get person results by puzzle publication decade
     */
    public function get_person_results_by_decade(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('g.guesser_person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                FLOOR(YEAR(pz.publication_date) / 10) * 10 as decade,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            INNER JOIN {$this->puzzles_table} pz ON c.puzzle_id = pz.id
            WHERE {$clause}
            GROUP BY FLOOR(YEAR(pz.publication_date) / 10) * 10
            ORDER BY decade ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get person results by year of round
     */
    public function get_person_results_by_year(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('g.guesser_person_id', $person_ids);
        $sub_clause = $this->prepare_person_id_clause('g2.guesser_person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
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
                WHERE {$sub_clause}
                GROUP BY g2.guesser_person_id, c2.round_id
            ) rs ON rs.round_id = r.id AND rs.guesser_person_id = g.guesser_person_id
            WHERE {$clause}
            GROUP BY YEAR(r.round_date)
            ORDER BY year ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get the best streak of consecutive correct answers per year for a person
     *
     * @return array<int, int> Keyed by year
     */
    public function get_person_best_streaks_by_year(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('g.guesser_person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT c.round_id, YEAR(r.round_date) as year, c.clue_number, g.is_correct
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            INNER JOIN {$this->rounds_table} r ON c.round_id = r.id
            WHERE {$clause}
            ORDER BY year ASC, c.round_id ASC, c.clue_number ASC";

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
    public function get_person_results_by_constructor(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('g.guesser_person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                con.id as constructor_id,
                con.full_name,
                con.xwordinfo_profile_name,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            INNER JOIN {$this->puzzle_constructors_table} pc ON c.puzzle_id = pc.puzzle_id
            INNER JOIN {$this->persons_table} con ON pc.person_id = con.id
            WHERE {$clause}
            GROUP BY con.id, con.full_name, con.xwordinfo_profile_name
            ORDER BY (SUM(g.is_correct) / COUNT(*)) DESC, COUNT(*) DESC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get person's results grouped by puzzle editor
     */
    public function get_person_results_by_editor(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('g.guesser_person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                ed.id as editor_id,
                COALESCE(ed.full_name, 'Unknown') as editor_name,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            INNER JOIN {$this->puzzles_table} p ON c.puzzle_id = p.id
            LEFT JOIN {$this->persons_table} ed ON p.editor_id = ed.id
            WHERE {$clause}
            GROUP BY ed.id, ed.full_name
            ORDER BY (SUM(g.is_correct) / COUNT(*)) DESC, COUNT(*) DESC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get the best streak of consecutive correct answers per round for a person.
     *
     * @return array<int, int> Keyed by round_id => best streak in that round
     */
    public function get_person_streak_per_round(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('g.guesser_person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT c.round_id, c.clue_number, g.is_correct
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            WHERE {$clause}
            ORDER BY c.round_id ASC, c.clue_number ASC";

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
    public function get_person_correct_clue_rounds(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('g.guesser_person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT c.clue_number, c.round_id
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            WHERE {$clause} AND g.is_correct = 1
            ORDER BY c.clue_number ASC, c.round_id ASC";

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
    public function get_person_round_history(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('rg.person_id', $person_ids);
        $guess_clause = $this->prepare_person_id_clause('g.guesser_person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                r.id as round_id,
                r.game_number,
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
            LEFT JOIN {$this->guesses_table} g ON c.id = g.clue_id AND {$guess_clause}
            WHERE {$clause}
            GROUP BY r.id, r.game_number, r.round_date, r.round_number, r.episode_number, r.episode_url, r.episode_start_seconds
            ORDER BY r.round_date DESC, r.round_number DESC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get all puzzles from rounds a person has played
     */
    public function get_person_puzzles(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('rg.person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                pz.id as puzzle_id,
                pz.publication_date,
                pz.editor_id,
                COALESCE(ed.full_name, '') as editor_name,
                GROUP_CONCAT(DISTINCT con.full_name ORDER BY pc.constructor_order ASC SEPARATOR ', ') as constructor_names,
                GROUP_CONCAT(DISTINCT con.id ORDER BY pc.constructor_order ASC) as constructor_ids,
                GROUP_CONCAT(DISTINCT r.id ORDER BY r.round_date ASC, r.round_number ASC) as round_ids,
                GROUP_CONCAT(DISTINCT r.game_number ORDER BY r.round_date ASC, r.round_number ASC) as round_game_numbers,
                GROUP_CONCAT(DISTINCT r.round_date ORDER BY r.round_date ASC, r.round_number ASC) as round_dates,
                GROUP_CONCAT(DISTINCT r.round_number ORDER BY r.round_date ASC, r.round_number ASC) as round_numbers
            FROM {$this->puzzles_table} pz
            INNER JOIN {$this->clues_table} c ON c.puzzle_id = pz.id
            INNER JOIN {$this->rounds_table} r ON c.round_id = r.id
            INNER JOIN {$this->round_guessers_table} rg ON r.id = rg.round_id
            LEFT JOIN {$this->puzzle_constructors_table} pc ON pz.id = pc.puzzle_id
            LEFT JOIN {$this->persons_table} con ON pc.person_id = con.id
            LEFT JOIN {$this->persons_table} ed ON pz.editor_id = ed.id
            WHERE {$clause}
            GROUP BY pz.id, pz.publication_date, ed.full_name
            ORDER BY pz.publication_date DESC";

        return $this->wpdb->get_results($sql);
    }

    // =========================================================================
    // ROLE-BASED STATISTICS (Constructor & Editor roles on persons)
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
     * Get persons who are constructors, with puzzle and clue counts
     */
    public function get_persons_who_are_constructors(): array {
        $sql = "SELECT
                p.id,
                p.full_name,
                p.xwordinfo_profile_name,
                p.xwordinfo_image_url,
                COUNT(DISTINCT pc.puzzle_id) as puzzle_count,
                COUNT(DISTINCT c.id) as clue_count,
                COALESCE(SUM(g.is_correct), 0) as correct_guesses,
                COUNT(g.id) as total_guesses
            FROM {$this->persons_table} p
            INNER JOIN {$this->puzzle_constructors_table} pc ON p.id = pc.person_id
            LEFT JOIN {$this->clues_table} c ON c.puzzle_id = pc.puzzle_id
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
            GROUP BY p.id, p.full_name, p.xwordinfo_profile_name, p.xwordinfo_image_url
            ORDER BY p.full_name ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get aggregate stats for a person's constructor role
     */
    public function get_person_constructor_stats(int|array $person_ids): ?object {
        $clause = $this->prepare_person_id_clause('pc.person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                COUNT(DISTINCT pc.puzzle_id) as puzzle_count,
                COUNT(DISTINCT c.id) as clue_count,
                COUNT(DISTINCT g.guesser_person_id) as player_count,
                COALESCE(SUM(g.is_correct), 0) as correct_guesses,
                COUNT(g.id) as total_guesses
            FROM {$this->puzzle_constructors_table} pc
            LEFT JOIN {$this->clues_table} c ON c.puzzle_id = pc.puzzle_id
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
            WHERE {$clause}";

        return $this->wpdb->get_row($sql);
    }

    /**
     * Get puzzles for a person's constructor role with round info
     */
    public function get_person_constructor_puzzles(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('pc.person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                pz.id as puzzle_id,
                pz.publication_date,
                pz.editor_id,
                COALESCE(ed.full_name, '') as editor_name,
                GROUP_CONCAT(DISTINCT r.id ORDER BY r.round_date ASC, r.round_number ASC) as round_ids,
                GROUP_CONCAT(DISTINCT r.game_number ORDER BY r.round_date ASC, r.round_number ASC) as round_game_numbers,
                GROUP_CONCAT(DISTINCT r.round_date ORDER BY r.round_date ASC, r.round_number ASC) as round_dates,
                GROUP_CONCAT(DISTINCT r.round_number ORDER BY r.round_date ASC, r.round_number ASC) as round_numbers
            FROM {$this->puzzles_table} pz
            INNER JOIN {$this->puzzle_constructors_table} pc ON pz.id = pc.puzzle_id
            LEFT JOIN {$this->persons_table} ed ON pz.editor_id = ed.id
            LEFT JOIN {$this->clues_table} c ON c.puzzle_id = pz.id
            LEFT JOIN {$this->rounds_table} r ON c.round_id = r.id
            WHERE {$clause}
            GROUP BY pz.id, pz.publication_date, ed.full_name
            ORDER BY pz.publication_date DESC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get player results for all clues by a person's constructor role
     */
    public function get_person_constructor_player_results(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('pc.person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                p.id as person_id,
                p.full_name,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            INNER JOIN {$this->puzzle_constructors_table} pc ON c.puzzle_id = pc.puzzle_id
            INNER JOIN {$this->persons_table} p ON g.guesser_person_id = p.id
            WHERE {$clause}
            GROUP BY p.id, p.full_name
            ORDER BY (SUM(g.is_correct) / COUNT(*)) DESC, COUNT(*) DESC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get editor results for a person's constructor puzzles
     */
    public function get_person_constructor_editor_results(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('pc.person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                ed.id as editor_id,
                COALESCE(ed.full_name, 'Unknown') as editor_name,
                COUNT(DISTINCT pz.id) as puzzle_count,
                COUNT(DISTINCT c.id) as clue_count,
                COUNT(g.id) as total_guesses,
                COALESCE(SUM(g.is_correct), 0) as correct_guesses
            FROM {$this->puzzle_constructors_table} pc
            INNER JOIN {$this->puzzles_table} pz ON pc.puzzle_id = pz.id
            LEFT JOIN {$this->persons_table} ed ON pz.editor_id = ed.id
            LEFT JOIN {$this->clues_table} c ON c.puzzle_id = pz.id
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
            WHERE {$clause}
            GROUP BY ed.id, ed.full_name
            ORDER BY puzzle_count DESC, ed.full_name ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get persons who are editors, with aggregate guess stats
     */
    public function get_persons_who_are_editors(): array {
        $sql = "SELECT
                ed.id,
                ed.full_name,
                ed.full_name as editor_name,
                COUNT(DISTINCT p.id) as puzzle_count,
                COUNT(DISTINCT c.id) as clue_count,
                COUNT(DISTINCT g.id) as total_guesses,
                COALESCE(SUM(g.is_correct), 0) as correct_guesses
            FROM {$this->persons_table} ed
            INNER JOIN {$this->puzzles_table} p ON p.editor_id = ed.id
            INNER JOIN {$this->clues_table} c ON c.puzzle_id = p.id
            INNER JOIN {$this->guesses_table} g ON g.clue_id = c.id
            GROUP BY ed.id, ed.full_name
            ORDER BY ed.full_name ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get player results for all clues under a person's editor role
     */
    public function get_person_editor_player_results(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('pz.editor_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                p.id as person_id,
                p.full_name,
                COUNT(*) as total_answered,
                SUM(g.is_correct) as correct_count
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            INNER JOIN {$this->puzzles_table} pz ON c.puzzle_id = pz.id
            INNER JOIN {$this->persons_table} p ON g.guesser_person_id = p.id
            WHERE {$clause}
            GROUP BY p.id, p.full_name
            ORDER BY (SUM(g.is_correct) / COUNT(*)) DESC, COUNT(*) DESC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get constructor results for all puzzles under a person's editor role
     */
    public function get_person_editor_constructor_results(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('pz.editor_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                con.id as constructor_id,
                con.full_name,
                COUNT(DISTINCT pz.id) as puzzle_count,
                COUNT(DISTINCT c.id) as clue_count,
                COUNT(g.id) as total_guesses,
                COALESCE(SUM(g.is_correct), 0) as correct_guesses
            FROM {$this->puzzles_table} pz
            INNER JOIN {$this->puzzle_constructors_table} pc ON pz.id = pc.puzzle_id
            INNER JOIN {$this->persons_table} con ON pc.person_id = con.id
            LEFT JOIN {$this->clues_table} c ON c.puzzle_id = pz.id
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
            WHERE {$clause}
            GROUP BY con.id, con.full_name
            ORDER BY puzzle_count DESC, con.full_name ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get aggregate stats for a person's editor role
     */
    public function get_person_editor_stats(int|array $person_ids): ?object {
        $clause = $this->prepare_person_id_clause('p.editor_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                COUNT(DISTINCT p.id) as puzzle_count,
                COUNT(DISTINCT c.id) as clue_count,
                COUNT(DISTINCT g.guesser_person_id) as player_count,
                COALESCE(SUM(g.is_correct), 0) as correct_guesses,
                COUNT(g.id) as total_guesses
            FROM {$this->puzzles_table} p
            INNER JOIN {$this->clues_table} c ON c.puzzle_id = p.id
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
            WHERE {$clause}";

        return $this->wpdb->get_row($sql);
    }

    /**
     * Get puzzles for a person's editor role, with constructor and round info
     */
    public function get_person_editor_puzzles(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('pz.editor_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                pz.id as puzzle_id,
                pz.publication_date,
                GROUP_CONCAT(DISTINCT con.full_name ORDER BY pc.constructor_order ASC SEPARATOR ', ') as constructor_names,
                GROUP_CONCAT(DISTINCT con.id ORDER BY pc.constructor_order ASC) as constructor_ids,
                GROUP_CONCAT(DISTINCT r.id ORDER BY r.round_date ASC, r.round_number ASC) as round_ids,
                GROUP_CONCAT(DISTINCT r.game_number ORDER BY r.round_date ASC, r.round_number ASC) as round_game_numbers,
                GROUP_CONCAT(DISTINCT r.round_date ORDER BY r.round_date ASC, r.round_number ASC) as round_dates,
                GROUP_CONCAT(DISTINCT r.round_number ORDER BY r.round_date ASC, r.round_number ASC) as round_numbers
            FROM {$this->puzzles_table} pz
            LEFT JOIN {$this->puzzle_constructors_table} pc ON pz.id = pc.puzzle_id
            LEFT JOIN {$this->persons_table} con ON pc.person_id = con.id
            LEFT JOIN {$this->clues_table} c ON c.puzzle_id = pz.id
            LEFT JOIN {$this->rounds_table} r ON c.round_id = r.id
            WHERE {$clause}
            GROUP BY pz.id, pz.publication_date
            ORDER BY pz.publication_date DESC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get all persons who are clue givers, with aggregate stats
     */
    public function get_persons_who_are_clue_givers(): array {
        $sql = "SELECT
                p.id,
                p.full_name,
                p.full_name as clue_giver_name,
                COUNT(DISTINCT r.id) as round_count,
                COUNT(DISTINCT c.id) as clue_count,
                COUNT(DISTINCT rg.person_id) as guesser_count,
                COUNT(DISTINCT g.id) as total_guesses,
                COUNT(DISTINCT CASE WHEN g.is_correct = 1 THEN g.id END) as correct_guesses
            FROM {$this->persons_table} p
            INNER JOIN {$this->rounds_table} r ON r.clue_giver_id = p.id
            LEFT JOIN {$this->clues_table} c ON c.round_id = r.id
            LEFT JOIN {$this->round_guessers_table} rg ON rg.round_id = r.id
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
            GROUP BY p.id, p.full_name
            ORDER BY p.full_name ASC";

        return $this->wpdb->get_results($sql);
    }

    // =========================================================================
    // CLUE GIVER STATS
    // =========================================================================

    /**
     * Get overall stats for a person's clue giver role
     */
    public function get_clue_giver_stats(int|array $person_ids): ?object {
        $sub_clause = $this->prepare_person_id_clause('r2.clue_giver_id', $person_ids);
        $clause = $this->prepare_person_id_clause('r.clue_giver_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                COUNT(DISTINCT r.id) as round_count,
                COUNT(DISTINCT c.id) as clue_count,
                (SELECT COUNT(DISTINCT rg.person_id)
                    FROM {$this->round_guessers_table} rg
                    INNER JOIN {$this->rounds_table} r2 ON r2.id = rg.round_id
                    WHERE {$sub_clause}
                ) as guesser_count,
                COUNT(CASE WHEN grg.id IS NOT NULL THEN g.id END) as total_guesses,
                COALESCE(SUM(CASE WHEN grg.id IS NOT NULL THEN g.is_correct END), 0) as correct_guesses
            FROM {$this->rounds_table} r
            LEFT JOIN {$this->clues_table} c ON c.round_id = r.id
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
            LEFT JOIN {$this->round_guessers_table} grg ON grg.round_id = r.id AND grg.person_id = g.guesser_person_id
            WHERE {$clause}";

        return $this->wpdb->get_row($sql);
    }

    /**
     * Get the total number of unique players (guessers) across all rounds hosted by a person
     */
    public function get_clue_giver_unique_players(int|array $person_ids): int {
        $clause = $this->prepare_person_id_clause('r.clue_giver_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT COUNT(DISTINCT rg.person_id)
            FROM {$this->round_guessers_table} rg
            INNER JOIN {$this->rounds_table} r ON r.id = rg.round_id
            WHERE {$clause}";

        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Get clue giver stats grouped by year
     */
    public function get_clue_giver_stats_by_year(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('r.clue_giver_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                YEAR(r.round_date) as year,
                COUNT(DISTINCT r.id) as round_count,
                COUNT(DISTINCT c.id) as clue_count,
                COUNT(CASE WHEN grg.id IS NOT NULL THEN g.id END) as total_guesses,
                COALESCE(SUM(CASE WHEN grg.id IS NOT NULL THEN g.is_correct END), 0) as correct_guesses
            FROM {$this->rounds_table} r
            LEFT JOIN {$this->clues_table} c ON c.round_id = r.id
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
            LEFT JOIN {$this->round_guessers_table} grg ON grg.round_id = r.id AND grg.person_id = g.guesser_person_id
            WHERE {$clause}
            GROUP BY YEAR(r.round_date)
            ORDER BY year DESC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get clue giver stats grouped by day of week (of the round date)
     */
    public function get_clue_giver_stats_by_day(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('r.clue_giver_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                DAYOFWEEK(pz.publication_date) as day_of_week,
                COUNT(DISTINCT r.id) as round_count,
                COUNT(DISTINCT c.id) as clue_count,
                COUNT(CASE WHEN grg.id IS NOT NULL THEN g.id END) as total_guesses,
                COALESCE(SUM(CASE WHEN grg.id IS NOT NULL THEN g.is_correct END), 0) as correct_guesses
            FROM {$this->rounds_table} r
            LEFT JOIN {$this->clues_table} c ON c.round_id = r.id
            LEFT JOIN {$this->puzzles_table} pz ON pz.id = c.puzzle_id
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
            LEFT JOIN {$this->round_guessers_table} grg ON grg.round_id = r.id AND grg.person_id = g.guesser_person_id
            WHERE {$clause}
              AND pz.publication_date IS NOT NULL
            GROUP BY DAYOFWEEK(pz.publication_date)
            ORDER BY MOD(DAYOFWEEK(pz.publication_date) + 5, 7) ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get clue giver stats broken down per guesser
     */
    public function get_clue_giver_stats_by_guesser(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('r.clue_giver_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                p.id as person_id,
                p.full_name,
                COUNT(g.id) as total_guesses,
                COALESCE(SUM(g.is_correct), 0) as correct_guesses
            FROM {$this->rounds_table} r
            INNER JOIN {$this->clues_table} c ON c.round_id = r.id
            INNER JOIN {$this->guesses_table} g ON g.clue_id = c.id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = r.id AND rg.person_id = g.guesser_person_id
            INNER JOIN {$this->persons_table} p ON p.id = g.guesser_person_id
            WHERE {$clause}
            GROUP BY p.id, p.full_name
            ORDER BY (COALESCE(SUM(g.is_correct), 0) / COUNT(g.id)) DESC, COUNT(g.id) DESC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get rounds for a person's clue giver role with aggregate stats
     */
    public function get_clue_giver_rounds(int|array $person_ids): array {
        $clause = $this->prepare_person_id_clause('r.clue_giver_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                r.id as round_id,
                r.game_number,
                r.round_date,
                r.round_number,
                r.episode_number,
                r.episode_id,
                r.episode_url,
                COALESCE(cs.clue_count, 0) as clue_count,
                COALESCE(cs.total_guesses, 0) as total_guesses,
                COALESCE(cs.correct_guesses, 0) as correct_guesses,
                COALESCE(gi.guesser_count, 0) as guesser_count,
                gi.guesser_names,
                gi.guesser_ids
            FROM {$this->rounds_table} r
            LEFT JOIN (
                SELECT
                    c.round_id,
                    COUNT(DISTINCT c.id) as clue_count,
                    COUNT(CASE WHEN grg.id IS NOT NULL THEN g.id END) as total_guesses,
                    COALESCE(SUM(CASE WHEN grg.id IS NOT NULL THEN g.is_correct END), 0) as correct_guesses
                FROM {$this->clues_table} c
                LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
                LEFT JOIN {$this->round_guessers_table} grg ON grg.round_id = c.round_id AND grg.person_id = g.guesser_person_id
                GROUP BY c.round_id
            ) cs ON cs.round_id = r.id
            LEFT JOIN (
                SELECT
                    rg.round_id,
                    COUNT(DISTINCT rg.person_id) as guesser_count,
                    GROUP_CONCAT(DISTINCT p.full_name ORDER BY p.full_name ASC SEPARATOR ', ') as guesser_names,
                    GROUP_CONCAT(DISTINCT p.id ORDER BY p.full_name ASC) as guesser_ids
                FROM {$this->round_guessers_table} rg
                LEFT JOIN {$this->persons_table} p ON p.id = rg.person_id
                GROUP BY rg.round_id
            ) gi ON gi.round_id = r.id
            WHERE {$clause}
            ORDER BY r.round_date DESC, r.round_number DESC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get all clue-giving streaks for a person
     *
     * A clue is considered "correct" if at least one guesser answered it correctly.
     * Clues are ordered chronologically by round date, round number, and clue number.
     *
     * Returns an object with:
     *   - best_correct_streak (int)
     *   - best_incorrect_streak (int)
     *   - streaks (array of objects with type, length, round_ids, start_date, end_date)
     */
    public function get_clue_giver_streaks(int|array $person_ids): object {
        $clause = $this->prepare_person_id_clause('r.clue_giver_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                c.id,
                c.round_id,
                c.clue_number,
                r.round_date,
                COALESCE(SUM(CASE WHEN grg.id IS NOT NULL THEN g.is_correct END), 0) as correct_count,
                COUNT(CASE WHEN grg.id IS NOT NULL THEN g.id END) as guess_count
            FROM {$this->rounds_table} r
            INNER JOIN {$this->clues_table} c ON c.round_id = r.id
            LEFT JOIN {$this->guesses_table} g ON g.clue_id = c.id
            LEFT JOIN {$this->round_guessers_table} grg ON grg.round_id = r.id AND grg.person_id = g.guesser_person_id
            WHERE {$clause}
            GROUP BY c.id, c.round_id, c.clue_number, r.round_date, r.round_number
            ORDER BY r.round_date ASC, r.round_number ASC, c.clue_number ASC";

        $rows = $this->wpdb->get_results($sql);

        $best_correct   = 0;
        $best_incorrect = 0;
        $all_streaks    = [];

        // Current streak tracking
        $current_type   = null;   // 'correct' or 'incorrect'
        $current_length = 0;
        $current_round_ids  = [];
        $current_start_date = null;
        $current_end_date   = null;

        $finish_streak = function () use (&$all_streaks, &$current_type, &$current_length, &$current_round_ids, &$current_start_date, &$current_end_date) {
            if ($current_type !== null && $current_length >= 2) {
                $all_streaks[] = (object) [
                    'type'       => $current_type,
                    'length'     => $current_length,
                    'round_ids'  => array_keys($current_round_ids),
                    'start_date' => $current_start_date,
                    'end_date'   => $current_end_date,
                ];
            }
        };

        foreach ($rows as $row) {
            $rid  = (int) $row->round_id;
            $date = $row->round_date;
            $is_correct = (int) $row->correct_count > 0;
            $type = $is_correct ? 'correct' : 'incorrect';

            if ($type === $current_type) {
                // Continue current streak
                $current_length++;
                $current_round_ids[$rid] = true;
                $current_end_date = $date;
            } else {
                // Finish previous streak, start new one
                $finish_streak();
                $current_type   = $type;
                $current_length = 1;
                $current_round_ids  = [$rid => true];
                $current_start_date = $date;
                $current_end_date   = $date;
            }

            if ($is_correct && $current_length > $best_correct) {
                $best_correct = $current_length;
            }
            if (!$is_correct && $current_length > $best_incorrect) {
                $best_incorrect = $current_length;
            }
        }

        // Finish the last streak
        $finish_streak();

        // Sort streaks by length descending
        usort($all_streaks, function ($a, $b) {
            return $b->length <=> $a->length;
        });

        return (object) [
            'best_correct_streak'   => $best_correct,
            'best_incorrect_streak' => $best_incorrect,
            'streaks'               => $all_streaks,
        ];
    }

    /**
     * Get streaks of consecutive correct/incorrect guesses for a player.
     *
     * @param int|int[] $person_ids Person ID or array of aliased IDs.
     * @return object Object with best_correct_streak, best_incorrect_streak, and streaks array.
     */
    public function get_player_streaks(int|array $person_ids): object {
        $clause = $this->prepare_person_id_clause('g.guesser_person_id', $person_ids);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                g.id,
                c.round_id,
                c.clue_number,
                r.round_date,
                g.is_correct
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            INNER JOIN {$this->rounds_table} r ON r.id = c.round_id
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = c.round_id AND rg.person_id = g.guesser_person_id
            WHERE {$clause}
            ORDER BY r.round_date ASC, r.round_number ASC, c.clue_number ASC";

        $rows = $this->wpdb->get_results($sql);

        $best_correct   = 0;
        $best_incorrect = 0;
        $all_streaks    = [];

        $current_type   = null;
        $current_length = 0;
        $current_round_ids  = [];
        $current_start_date = null;
        $current_end_date   = null;

        $finish_streak = function () use (&$all_streaks, &$current_type, &$current_length, &$current_round_ids, &$current_start_date, &$current_end_date) {
            if ($current_type !== null && $current_length >= 2) {
                $all_streaks[] = (object) [
                    'type'       => $current_type,
                    'length'     => $current_length,
                    'round_ids'  => array_keys($current_round_ids),
                    'start_date' => $current_start_date,
                    'end_date'   => $current_end_date,
                ];
            }
        };

        foreach ($rows as $row) {
            $rid  = (int) $row->round_id;
            $date = $row->round_date;
            $is_correct = (int) $row->is_correct;
            $type = $is_correct ? 'correct' : 'incorrect';

            if ($type === $current_type) {
                $current_length++;
                $current_round_ids[$rid] = true;
                $current_end_date = $date;
            } else {
                $finish_streak();
                $current_type   = $type;
                $current_length = 1;
                $current_round_ids  = [$rid => true];
                $current_start_date = $date;
                $current_end_date   = $date;
            }

            if ($is_correct && $current_length > $best_correct) {
                $best_correct = $current_length;
            }
            if (!$is_correct && $current_length > $best_incorrect) {
                $best_incorrect = $current_length;
            }
        }

        $finish_streak();

        usort($all_streaks, function ($a, $b) {
            return $b->length <=> $a->length;
        });

        return (object) [
            'best_correct_streak'   => $best_correct,
            'best_incorrect_streak' => $best_incorrect,
            'streaks'               => $all_streaks,
        ];
    }

    // =========================================================================
    // ROLE INFERENCE
    // =========================================================================

    /**
     * Get all active roles for a person (or merged alias group)
     *
     * @param int|int[] $person_ids One person ID or array of aliased IDs.
     * @return string[] Array of role names: 'player', 'constructor', 'editor', 'clue_giver'
     */
    public function get_person_roles(int|array $person_ids): array {
        $player_clause      = $this->prepare_person_id_clause('person_id', $person_ids);
        $constructor_clause = $this->prepare_person_id_clause('person_id', $person_ids);
        $editor_clause      = $this->prepare_person_id_clause('editor_id', $person_ids);
        $clue_giver_clause  = $this->prepare_person_id_clause('clue_giver_id', $person_ids);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT
                (SELECT 1 FROM {$this->round_guessers_table} WHERE {$player_clause} LIMIT 1) as is_player,
                (SELECT 1 FROM {$this->puzzle_constructors_table} WHERE {$constructor_clause} LIMIT 1) as is_constructor,
                (SELECT 1 FROM {$this->puzzles_table} WHERE {$editor_clause} LIMIT 1) as is_editor,
                (SELECT 1 FROM {$this->rounds_table} WHERE {$clue_giver_clause} LIMIT 1) as is_clue_giver";

        $row = $this->wpdb->get_row($sql);
        $roles = [];
        if ($row->is_player) {
            $roles[] = 'player';
        }
        if ($row->is_constructor) {
            $roles[] = 'constructor';
        }
        if ($row->is_editor) {
            $roles[] = 'editor';
        }
        if ($row->is_clue_giver) {
            $roles[] = 'clue_giver';
        }
        return $roles;
    }

    /**
     * Get all puzzles with constructor/round details for the puzzles table
     */
    public function get_all_puzzles_with_details(): array {
        $sql = "SELECT
                pz.id as puzzle_id,
                pz.publication_date,
                COALESCE(ed.full_name, '') as editor_name,
                ed.id as editor_id,
                GROUP_CONCAT(DISTINCT con.full_name ORDER BY pc.constructor_order ASC SEPARATOR ', ') as constructor_names,
                GROUP_CONCAT(DISTINCT con.id ORDER BY pc.constructor_order ASC) as constructor_ids,
                GROUP_CONCAT(DISTINCT r.id ORDER BY r.round_date ASC, r.round_number ASC) as round_ids,
                GROUP_CONCAT(DISTINCT r.game_number ORDER BY r.round_date ASC, r.round_number ASC) as round_game_numbers,
                GROUP_CONCAT(DISTINCT r.round_date ORDER BY r.round_date ASC, r.round_number ASC) as round_dates,
                GROUP_CONCAT(DISTINCT r.round_number ORDER BY r.round_date ASC, r.round_number ASC) as round_numbers
            FROM {$this->puzzles_table} pz
            LEFT JOIN {$this->persons_table} ed ON pz.editor_id = ed.id
            LEFT JOIN {$this->puzzle_constructors_table} pc ON pz.id = pc.puzzle_id
            LEFT JOIN {$this->persons_table} con ON pc.person_id = con.id
            LEFT JOIN {$this->clues_table} c ON c.puzzle_id = pz.id
            LEFT JOIN {$this->rounds_table} r ON c.round_id = r.id
            GROUP BY pz.id, pz.publication_date, ed.full_name, ed.id
            ORDER BY pz.publication_date DESC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get co-constructors for a constructor's puzzles
     */
    /**
     * Get all puzzles that have been used in more than one distinct round.
     *
     * Returns puzzle rows with constructor/editor info and a round_count column,
     * ordered by round_count DESC then publication_date ASC.
     *
     * @return array Array of puzzle objects.
     */
    public function get_puzzles_used_in_multiple_rounds(): array {
        $sql = "SELECT
                pz.id,
                pz.publication_date,
                COALESCE(ed.full_name, '') as editor_name,
                ed.id as editor_id,
                GROUP_CONCAT(DISTINCT con.full_name ORDER BY pc.constructor_order ASC SEPARATOR ', ') as constructor_names,
                GROUP_CONCAT(DISTINCT con.id ORDER BY pc.constructor_order ASC) as constructor_ids,
                rd.round_count,
                rd.round_ids,
                rd.round_dates,
                rd.round_numbers,
                rd.round_game_numbers
            FROM {$this->puzzles_table} pz
            LEFT JOIN {$this->persons_table} ed ON pz.editor_id = ed.id
            LEFT JOIN {$this->puzzle_constructors_table} pc ON pz.id = pc.puzzle_id
            LEFT JOIN {$this->persons_table} con ON pc.person_id = con.id
            INNER JOIN (
                SELECT
                    dc.puzzle_id,
                    COUNT(dc.round_id) as round_count,
                    GROUP_CONCAT(dc.round_id ORDER BY r.round_date ASC, r.round_number ASC) as round_ids,
                    GROUP_CONCAT(r.round_date ORDER BY r.round_date ASC, r.round_number ASC) as round_dates,
                    GROUP_CONCAT(r.round_number ORDER BY r.round_date ASC, r.round_number ASC) as round_numbers,
                    GROUP_CONCAT(r.game_number ORDER BY r.round_date ASC, r.round_number ASC) as round_game_numbers
                FROM (SELECT DISTINCT puzzle_id, round_id FROM {$this->clues_table}) dc
                INNER JOIN {$this->rounds_table} r ON r.id = dc.round_id
                GROUP BY dc.puzzle_id
                HAVING COUNT(dc.round_id) > 1
            ) rd ON rd.puzzle_id = pz.id
            GROUP BY pz.id, pz.publication_date, ed.full_name, ed.id, rd.round_count, rd.round_ids, rd.round_dates, rd.round_numbers, rd.round_game_numbers
            ORDER BY rd.round_count DESC, pz.publication_date ASC";

        return $this->wpdb->get_results($sql) ?: [];
    }

    /**
     * Get co-constructors for a constructor's puzzles
     */
    public function get_puzzle_co_constructors(int $puzzle_id, int $exclude_person_id): array {
        $sql = $this->wpdb->prepare(
            "SELECT p.id, p.full_name, p.xwordinfo_profile_name
            FROM {$this->persons_table} p
            INNER JOIN {$this->puzzle_constructors_table} pc ON p.id = pc.person_id
            WHERE pc.puzzle_id = %d AND p.id != %d
            ORDER BY pc.constructor_order ASC",
            $puzzle_id,
            $exclude_person_id
        );

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get co-constructors for multiple puzzles in a single query.
     *
     * @param int[] $puzzle_ids Array of puzzle IDs.
     * @param int   $exclude_person_id Person ID to exclude (the main constructor).
     * @return array<int, array> Map of puzzle_id => array of co-constructor objects.
     */
    public function get_puzzle_co_constructors_bulk(array $puzzle_ids, int|array $exclude_person_ids): array {
        $map = [];
        foreach ($puzzle_ids as $pid) {
            $map[(int) $pid] = [];
        }
        if (empty($puzzle_ids)) {
            return $map;
        }
        $in = $this->ids_in_clause($puzzle_ids);
        $exclude_ids = is_int($exclude_person_ids) ? [$exclude_person_ids] : $exclude_person_ids;
        $not_in = implode(', ', array_map('intval', $exclude_ids));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT pc.puzzle_id, p.id, p.full_name, p.xwordinfo_profile_name
            FROM {$this->persons_table} p
            INNER JOIN {$this->puzzle_constructors_table} pc ON p.id = pc.person_id
            WHERE pc.puzzle_id IN ({$in}) AND p.id NOT IN ({$not_in})
            ORDER BY pc.puzzle_id, pc.constructor_order ASC";
        $rows = $this->wpdb->get_results($sql);
        foreach ($rows as $row) {
            $map[(int) $row->puzzle_id][] = $row;
        }
        return $map;
    }

    /**
     * Get the highest round score for each person.
     *
     * Returns an associative array keyed by person_id.
     *
     * @return array<int, int>
     */
    public function get_all_persons_highest_round_scores(): array {
        $combined = $this->get_all_persons_highest_round_scores_with_round_ids();
        $map = [];
        foreach ($combined as $person_id => $data) {
            $map[$person_id] = $data['score'];
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
        $combined = $this->get_all_persons_highest_round_scores_with_round_ids();
        $map = [];
        foreach ($combined as $person_id => $data) {
            $map[$person_id] = $data['round_ids'];
        }
        return $map;
    }

    /**
     * Get the highest round score AND corresponding round IDs for each person
     * in a single query + PHP pass.
     *
     * @return array<int, array{score: int, round_ids: int[]}>
     */
    public function get_all_persons_highest_round_scores_with_round_ids(): array {
        $sql = "SELECT g.guesser_person_id, c.round_id, SUM(g.is_correct) as round_score
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            GROUP BY g.guesser_person_id, c.round_id
            ORDER BY g.guesser_person_id";

        $rows = $this->wpdb->get_results($sql);
        $map = [];
        foreach ($rows as $row) {
            $pid = (int) $row->guesser_person_id;
            $score = (int) $row->round_score;
            $rid = (int) $row->round_id;

            if (!isset($map[$pid]) || $score > $map[$pid]['score']) {
                $map[$pid] = ['score' => $score, 'round_ids' => [$rid]];
            } elseif ($score === $map[$pid]['score']) {
                $map[$pid]['round_ids'][] = $rid;
            }
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
        $combined = $this->get_all_persons_longest_streaks_with_round_ids();
        $map = [];
        foreach ($combined as $person_id => $data) {
            $map[$person_id] = $data['streak'];
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
        $combined = $this->get_all_persons_longest_streaks_with_round_ids();
        $map = [];
        foreach ($combined as $person_id => $data) {
            $map[$person_id] = $data['round_ids'];
        }
        return $map;
    }

    /**
     * Get the longest streak AND corresponding round IDs for each person
     * in a single query + PHP pass.
     *
     * @return array<int, array{streak: int, round_ids: int[]}>
     */
    public function get_all_persons_longest_streaks_with_round_ids(): array {
        $sql = "SELECT g.guesser_person_id, c.round_id, c.clue_number, g.is_correct
            FROM {$this->guesses_table} g
            INNER JOIN {$this->clues_table} c ON g.clue_id = c.id
            ORDER BY g.guesser_person_id ASC, c.round_id ASC, c.clue_number ASC";

        $rows = $this->wpdb->get_results($sql);

        $best = [];        // person_id => best streak length
        $round_best = [];  // "person_id-round_id" => best streak in that round
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
                $key = $person_id . '-' . $round_id;
                if (!isset($round_best[$key]) || $streak > $round_best[$key]) {
                    $round_best[$key] = $streak;
                }
            } else {
                $streak = 0;
            }
        }

        // Build combined map
        $map = [];
        foreach ($best as $person_id => $streak_val) {
            $map[$person_id] = ['streak' => $streak_val, 'round_ids' => []];
        }
        foreach ($round_best as $key => $streak_val) {
            [$person_id, $round_id] = array_map('intval', explode('-', $key));
            if (isset($best[$person_id]) && $streak_val === $best[$person_id]) {
                $map[$person_id]['round_ids'][] = $round_id;
            }
        }

        return $map;
    }

    /**
     * Search across persons, puzzles, rounds, and round solution words
     *
     * @param string $search_term The search term
     * @return array Array of results with type, name, and url
     */
    public function search_all(string $search_term): array {
        $results = [];
        $like = '%' . $this->wpdb->esc_like($search_term) . '%';

        // Search persons (unified: players, constructors, editors)
        $persons = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id, full_name FROM {$this->persons_table} WHERE full_name LIKE %s OR nicknames LIKE %s ORDER BY full_name ASC",
                $like,
                $like
            )
        );
        $role_display_names = [
            'player'      => 'Player',
            'constructor' => 'Constructor',
            'editor'      => 'Editor',
            'clue_giver'  => 'Host',
        ];
        // Bulk-fetch roles for all matched persons
        $person_ids = array_map(fn($p) => (int) $p->id, $persons);
        $all_roles = !empty($person_ids) ? $this->get_all_person_roles() : [];
        foreach ($persons as $p) {
            $roles = $all_roles[(int) $p->id] ?? [];
            $role_label = !empty($roles)
                ? implode(', ', array_map(fn($r) => $role_display_names[$r] ?? ucfirst($r), $roles))
                : 'Person';
            $slug = str_replace(' ', '_', $p->full_name);
            $results[] = (object) [
                'type' => 'person',
                'name' => $p->full_name . ' (' . $role_label . ')',
                'url' => home_url('/kealoa/person/' . urlencode($slug) . '/'),
            ];
        }

        // ---- Collect puzzle matches (deduplicated) ----
        $seen_puzzle_ids = [];
        $puzzle_objects = []; // puzzle_id => puzzle object

        $collect_puzzle = function (object $puzzle) use (&$seen_puzzle_ids, &$puzzle_objects): void {
            $pid = (int) $puzzle->puzzle_id;
            if (!isset($seen_puzzle_ids[$pid])) {
                $seen_puzzle_ids[$pid] = true;
                $puzzle_objects[$pid] = $puzzle;
            }
        };

        // Search puzzles by publication date
        $date_puzzles = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT id as puzzle_id, publication_date, editor_id
                FROM {$this->puzzles_table}
                WHERE publication_date LIKE %s
                ORDER BY publication_date DESC",
                $like
            )
        );
        foreach ($date_puzzles as $pz) {
            $collect_puzzle($pz);
        }

        // Search puzzles by constructor name (via persons)
        $con_puzzles = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT DISTINCT pz.id as puzzle_id, pz.publication_date, pz.editor_id
                FROM {$this->puzzles_table} pz
                INNER JOIN {$this->puzzle_constructors_table} pc ON pz.id = pc.puzzle_id
                INNER JOIN {$this->persons_table} con ON pc.person_id = con.id
                WHERE (con.full_name LIKE %s OR con.nicknames LIKE %s)
                ORDER BY pz.publication_date DESC",
                $like,
                $like
            )
        );
        foreach ($con_puzzles as $pz) {
            $collect_puzzle($pz);
        }

        // Search puzzles by editor name (via persons)
        $ed_puzzles = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT DISTINCT pz.id as puzzle_id, pz.publication_date, pz.editor_id
                FROM {$this->puzzles_table} pz
                INNER JOIN {$this->persons_table} ed ON pz.editor_id = ed.id
                WHERE (ed.full_name LIKE %s OR ed.nicknames LIKE %s)
                ORDER BY pz.publication_date DESC",
                $like,
                $like
            )
        );
        foreach ($ed_puzzles as $pz) {
            $collect_puzzle($pz);
        }

        // Search puzzles by clue text and correct answers
        $clue_puzzles = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT DISTINCT pz.id as puzzle_id, pz.publication_date, pz.editor_id
                FROM {$this->clues_table} c
                INNER JOIN {$this->puzzles_table} pz ON c.puzzle_id = pz.id
                WHERE c.clue_text LIKE %s OR c.correct_answer LIKE %s
                ORDER BY pz.publication_date DESC",
                $like,
                $like
            )
        );
        foreach ($clue_puzzles as $pz) {
            $collect_puzzle($pz);
        }

        // Bulk-fetch constructors and editors for all matched puzzles
        $puzzle_id_list = array_keys($puzzle_objects);
        $bulk_constructors = !empty($puzzle_id_list) ? $this->get_puzzle_constructors_bulk($puzzle_id_list) : [];
        $editor_ids_needed = array_unique(array_filter(array_map(fn($pz) => (int) ($pz->editor_id ?? 0), $puzzle_objects)));
        $bulk_editors = [];
        if (!empty($editor_ids_needed)) {
            $ed_in = $this->ids_in_clause($editor_ids_needed);
            $ed_rows = $this->wpdb->get_results("SELECT id, full_name FROM {$this->persons_table} WHERE id IN ({$ed_in})");
            foreach ($ed_rows as $ed) {
                $bulk_editors[(int) $ed->id] = $ed->full_name;
            }
        }

        // Build puzzle results
        foreach ($puzzle_objects as $pid => $puzzle) {
            $constructors = $bulk_constructors[$pid] ?? [];
            $con_names = array_map(fn($c) => $c->full_name, $constructors);
            $day_name = date('l', strtotime($puzzle->publication_date));
            $label = $day_name . ', ' . $puzzle->publication_date;
            if (!empty($con_names)) {
                $label .= ' — ' . implode(' & ', $con_names);
            }
            if (!empty($puzzle->editor_id) && isset($bulk_editors[(int) $puzzle->editor_id])) {
                $label .= ' (ed. ' . $bulk_editors[(int) $puzzle->editor_id] . ')';
            }
            $results[] = (object) [
                'type' => 'puzzle',
                'name' => $label,
                'url'  => home_url('/kealoa/puzzle/' . $puzzle->publication_date . '/'),
            ];
        }

        // ---- Collect round matches (deduplicated) ----
        $seen_round_ids = [];

        $round_id_order = []; // Preserves insertion order for dedup

        $collect_round = function (int $round_id) use (&$seen_round_ids, &$round_id_order): void {
            if (!isset($seen_round_ids[$round_id])) {
                $seen_round_ids[$round_id] = true;
                $round_id_order[] = $round_id;
            }
        };

        // Search rounds by solution words
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
            $collect_round((int) $rd->round_id);
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
            $collect_round((int) $rd->id);
        }

        // Include rounds from matching persons (as constructor, player, or editor)
        if (!empty($person_ids)) {
            $placeholders = implode(',', array_fill(0, count($person_ids), '%d'));

            // Rounds where person is constructor
            $constructor_rounds = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT DISTINCT cl.round_id
                    FROM {$this->puzzle_constructors_table} pc
                    INNER JOIN {$this->clues_table} cl ON cl.puzzle_id = pc.puzzle_id
                    INNER JOIN {$this->rounds_table} r ON cl.round_id = r.id
                    WHERE pc.person_id IN ($placeholders)
                    ORDER BY r.round_date DESC, r.round_number ASC",
                    ...$person_ids
                )
            );
            foreach ($constructor_rounds as $rd) {
                $collect_round((int) $rd->round_id);
            }

            // Rounds where person is player (guesser or clue giver)
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
                $collect_round((int) $rd->round_id);
            }

            // Rounds where person is editor
            $editor_rounds = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT DISTINCT cl.round_id
                    FROM {$this->puzzles_table} pz
                    INNER JOIN {$this->clues_table} cl ON cl.puzzle_id = pz.id
                    INNER JOIN {$this->rounds_table} r ON cl.round_id = r.id
                    WHERE pz.editor_id IN ($placeholders)
                    ORDER BY r.round_date DESC, r.round_number ASC",
                    ...$person_ids
                )
            );
            foreach ($editor_rounds as $rd) {
                $collect_round((int) $rd->round_id);
            }
        }

        // Bulk-fetch solutions and game_numbers for all matched rounds, then build results
        $bulk_solutions = !empty($round_id_order) ? $this->get_round_solutions_bulk($round_id_order) : [];
        $game_numbers = [];
        if (!empty($round_id_order)) {
            $placeholders = implode(',', array_fill(0, count($round_id_order), '%d'));
            $rows = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT id, game_number FROM {$this->rounds_table} WHERE id IN ($placeholders)",
                    ...$round_id_order
                )
            );
            foreach ($rows as $row) {
                $game_numbers[(int) $row->id] = (int) $row->game_number;
            }
        }
        foreach ($round_id_order as $rid) {
            $solutions = $bulk_solutions[$rid] ?? [];
            $words = array_map(fn($s) => strtoupper($s->word), $solutions);
            $gn = $game_numbers[$rid] ?? $rid;
            $results[] = (object) [
                'type' => 'round',
                'name' => 'KEALOA #' . $gn . ': ' . implode(', ', $words),
                'url'  => home_url('/kealoa/round/' . $gn . '/'),
            ];
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
            "SELECT p.id, p.publication_date, p.editor_id
             FROM {$this->puzzles_table} p
             LEFT JOIN {$this->clues_table} c ON c.puzzle_id = p.id
             WHERE c.id IS NULL
             ORDER BY p.publication_date"
        );
        if ($orphan_puzzles) {
            $issues['orphan_puzzles'] = $orphan_puzzles;
        }

        // 2. Rounds with no clues
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
            "SELECT pc.id, pc.puzzle_id, pc.person_id
             FROM {$this->puzzle_constructors_table} pc
             LEFT JOIN {$this->puzzles_table} p ON p.id = pc.puzzle_id
             WHERE p.id IS NULL"
        );
        if ($orphan_pc_puzzles) {
            $issues['orphan_puzzle_constructor_puzzles'] = $orphan_pc_puzzles;
        }

        // 7. Puzzle-constructor links referencing non-existent persons
        $orphan_pc_persons = $this->wpdb->get_results(
            "SELECT pc.id, pc.puzzle_id, pc.person_id
             FROM {$this->puzzle_constructors_table} pc
             LEFT JOIN {$this->persons_table} p ON p.id = pc.person_id
             WHERE p.id IS NULL"
        );
        if ($orphan_pc_persons) {
            $issues['orphan_puzzle_constructor_persons'] = $orphan_pc_persons;
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

        // 16. Puzzles referencing non-existent editor (person)
        $orphan_puzzle_editors = $this->wpdb->get_results(
            "SELECT pz.id, pz.publication_date, pz.editor_id
             FROM {$this->puzzles_table} pz
             LEFT JOIN {$this->persons_table} p ON p.id = pz.editor_id
             WHERE pz.editor_id IS NOT NULL AND p.id IS NULL"
        );
        if ($orphan_puzzle_editors) {
            $issues['orphan_puzzle_editors'] = $orphan_puzzle_editors;
        }

        // 17. Orphan persons — not referenced by any role
        $orphan_persons = $this->wpdb->get_results(
            "SELECT p.id, p.full_name
             FROM {$this->persons_table} p
             LEFT JOIN {$this->round_guessers_table} rg ON rg.person_id = p.id
             LEFT JOIN {$this->rounds_table} r_giver ON r_giver.clue_giver_id = p.id
             LEFT JOIN {$this->guesses_table} g ON g.guesser_person_id = p.id
             LEFT JOIN {$this->puzzle_constructors_table} pc ON pc.person_id = p.id
             LEFT JOIN {$this->puzzles_table} pz ON pz.editor_id = p.id
             WHERE rg.id IS NULL
               AND r_giver.id IS NULL
               AND g.id IS NULL
               AND pc.id IS NULL
               AND pz.id IS NULL
             ORDER BY p.full_name"
        );
        if ($orphan_persons) {
            $issues['orphan_persons'] = $orphan_persons;
        }

        // 18. Non-contiguous game numbers — game_number should be 1..N with no gaps
        $game_number_issues = [];
        $all_game_numbers = $this->wpdb->get_col(
            "SELECT game_number FROM {$this->rounds_table} ORDER BY game_number ASC"
        );
        if (!empty($all_game_numbers)) {
            $expected = 1;
            foreach ($all_game_numbers as $gn) {
                $gn = (int) $gn;
                if ($gn !== $expected) {
                    // Report the gap: we expected $expected but found $gn
                    $game_number_issues[] = (object) [
                        'expected_game_number' => $expected,
                        'actual_game_number'   => $gn,
                        'issue'                => $gn > $expected
                            ? sprintf('Missing game number(s) %d–%d', $expected, $gn - 1)
                            : sprintf('Duplicate or out-of-order game number %d', $gn),
                    ];
                    $expected = $gn + 1;
                } else {
                    $expected++;
                }
            }
        }
        if ($game_number_issues) {
            $issues['non_contiguous_game_numbers'] = $game_number_issues;
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
            'persons'              => $this->persons_table,
        ];

        if (!isset($table_map[$table_key]) || empty($ids)) {
            return 0;
        }

        $table = $table_map[$table_key];
        $int_ids = array_map('intval', $ids);
        $in = implode(',', $int_ids);

        return (int) $this->wpdb->query("DELETE FROM {$table} WHERE id IN ({$in})");
    }

    /**
     * Clear clue_giver_id on specified rounds (set to NULL).
     *
     * @param int[] $round_ids Array of round IDs.
     * @return int Number of rows updated.
     */
    public function clear_round_clue_givers(array $round_ids): int {
        if (empty($round_ids)) {
            return 0;
        }
        $int_ids = array_map('intval', $round_ids);
        $in = implode(',', $int_ids);

        return (int) $this->wpdb->query(
            "UPDATE {$this->rounds_table} SET clue_giver_id = NULL WHERE id IN ({$in})"
        );
    }

    /**
     * Clear editor_id on specified puzzles (set to NULL).
     *
     * @param int[] $puzzle_ids Array of puzzle IDs.
     * @return int Number of rows updated.
     */
    public function clear_puzzle_editors(array $puzzle_ids): int {
        if (empty($puzzle_ids)) {
            return 0;
        }
        $int_ids = array_map('intval', $puzzle_ids);
        $in = implode(',', $int_ids);

        return (int) $this->wpdb->query(
            "UPDATE {$this->puzzles_table} SET editor_id = NULL WHERE id IN ({$in})"
        );
    }

    /**
     * Renumber all game_numbers to be contiguous starting at 1.
     *
     * Orders rounds by round_date ASC, round_number ASC, then assigns
     * game_number = 1, 2, 3, ... sequentially.
     *
     * @return int Number of rows updated.
     */
    public function renumber_game_numbers(): int {
        $rounds = $this->wpdb->get_results(
            "SELECT id, game_number FROM {$this->rounds_table} ORDER BY round_date ASC, round_number ASC"
        );
        if (empty($rounds)) {
            return 0;
        }
        $updated = 0;
        $expected = 1;
        foreach ($rounds as $round) {
            if ((int) $round->game_number !== $expected) {
                $this->wpdb->update(
                    $this->rounds_table,
                    ['game_number' => $expected],
                    ['id' => (int) $round->id],
                    ['%d'],
                    ['%d']
                );
                $updated++;
            }
            $expected++;
        }
        return $updated;
    }

    /**
     * Get puzzles where a constructor is also the editor.
     *
     * Returns puzzle rows where at least one constructor's person_id matches
     * the puzzle's editor_id, with round info for each puzzle.
     * Special case: treats constructor "Alex Eaton-Salners" and editor
     * "Will Shortz" as the same person.
     *
     * @return array Array of puzzle objects.
     */
    public function get_puzzles_same_constructor_editor(): array {
        $sql = "SELECT
                pz.id,
                pz.publication_date,
                GROUP_CONCAT(DISTINCT con.full_name ORDER BY pc.constructor_order ASC SEPARATOR ', ') AS constructor_names,
                GROUP_CONCAT(DISTINCT con.id ORDER BY pc.constructor_order ASC) AS constructor_ids,
                COALESCE(ed.full_name, '') AS editor_name,
                ed.id AS editor_id,
                rd.round_ids,
                rd.round_dates,
                rd.round_numbers,
                rd.round_game_numbers
            FROM {$this->puzzles_table} pz
            INNER JOIN {$this->puzzle_constructors_table} pc ON pz.id = pc.puzzle_id
            INNER JOIN {$this->persons_table} con ON pc.person_id = con.id
            LEFT JOIN {$this->persons_table} ed ON pz.editor_id = ed.id
            LEFT JOIN (
                SELECT
                    dc.puzzle_id,
                    GROUP_CONCAT(dc.round_id ORDER BY r.round_date ASC, r.round_number ASC) AS round_ids,
                    GROUP_CONCAT(r.round_date ORDER BY r.round_date ASC, r.round_number ASC) AS round_dates,
                    GROUP_CONCAT(r.round_number ORDER BY r.round_date ASC, r.round_number ASC) AS round_numbers,
                    GROUP_CONCAT(r.game_number ORDER BY r.round_date ASC, r.round_number ASC) AS round_game_numbers
                FROM (SELECT DISTINCT puzzle_id, round_id FROM {$this->clues_table}) dc
                INNER JOIN {$this->rounds_table} r ON r.id = dc.round_id
                GROUP BY dc.puzzle_id
            ) rd ON rd.puzzle_id = pz.id
            WHERE pz.editor_id = con.id
               OR (con.full_name = 'Alex Eaton-Salners' AND ed.full_name = 'Will Shortz')
            GROUP BY pz.id, pz.publication_date, ed.full_name, ed.id, rd.round_ids, rd.round_dates, rd.round_numbers, rd.round_game_numbers
            ORDER BY pz.publication_date DESC";

        $results = $this->wpdb->get_results($sql);

        if ($results === null && !empty($this->wpdb->last_error)) {
            error_log('KEALOA same_constructor_editor query error: ' . $this->wpdb->last_error);
        }

        return $results ?: [];
    }

    /**
     * Get rounds where at least one solution word was never used as a correct answer.
     *
     * Returns round data plus a comma-separated list of unused words.
     *
     * @return array Array of objects with round fields + unused_words.
     */
    public function get_rounds_with_unused_answers(): array {
        $sql = "SELECT r.*, p.full_name AS clue_giver_name,
                GROUP_CONCAT(rs.word ORDER BY rs.word_order SEPARATOR ', ') AS unused_words
            FROM {$this->rounds_table} r
            INNER JOIN {$this->round_solutions_table} rs ON rs.round_id = r.id
            LEFT JOIN {$this->persons_table} p ON r.clue_giver_id = p.id
            WHERE NOT EXISTS (
                SELECT 1 FROM {$this->clues_table} c
                WHERE c.round_id = r.id
                    AND UPPER(c.correct_answer) = UPPER(rs.word)
            )
            GROUP BY r.id
            ORDER BY r.round_date DESC, r.round_number ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get rounds that have more than one player (guesser).
     *
     * Returns round data plus the number of players and a comma-separated
     * list of player names.
     *
     * @return array Array of objects with round fields + player_count, player_names.
     */
    public function get_rounds_with_multiple_players(): array {
        $sql = "SELECT r.*, COUNT(rg.person_id) AS player_count,
                GROUP_CONCAT(p2.full_name ORDER BY p2.full_name SEPARATOR ', ') AS player_names
            FROM {$this->rounds_table} r
            INNER JOIN {$this->round_guessers_table} rg ON rg.round_id = r.id
            INNER JOIN {$this->persons_table} p2 ON p2.id = rg.person_id
            GROUP BY r.id
            HAVING COUNT(rg.person_id) > 1
            ORDER BY COUNT(rg.person_id) DESC, r.round_date DESC, r.round_number ASC";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get rounds where a single constructor has more than one puzzle.
     *
     * Returns round data plus the constructor name and the number of
     * distinct puzzles they constructed in that round.
     *
     * @return array Array of objects with round fields + constructor_name, puzzle_count.
     */
    public function get_rounds_with_repeated_constructor(): array {
        $sql = "SELECT r.*, p.full_name AS constructor_name,
                COUNT(DISTINCT c.puzzle_id) AS puzzle_count
            FROM {$this->rounds_table} r
            INNER JOIN {$this->clues_table} c ON c.round_id = r.id
            INNER JOIN {$this->puzzle_constructors_table} pc ON pc.puzzle_id = c.puzzle_id
            INNER JOIN {$this->persons_table} p ON p.id = pc.person_id
            WHERE c.puzzle_id IS NOT NULL
            GROUP BY r.id, pc.person_id
            HAVING COUNT(DISTINCT c.puzzle_id) > 1
            ORDER BY COUNT(DISTINCT c.puzzle_id) DESC, r.round_date DESC, r.round_number ASC";

        return $this->wpdb->get_results($sql);
    }
}
