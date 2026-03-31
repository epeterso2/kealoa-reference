<?php
/**
 * @copyright 2026 Eric Peterson (eric@puzzlehead.org)
 * @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
 *
 * Shortcodes
 *
 * Provides shortcodes for displaying KEALOA data on the frontend.
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
 * Class Kealoa_Shortcodes
 *
 * Registers and renders shortcodes for KEALOA data display.
 */
class Kealoa_Shortcodes {

    private Kealoa_DB $db;

    /** Transient cache TTL in seconds (24 hours) */
    private const CACHE_TTL = DAY_IN_SECONDS;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Kealoa_DB();

        add_shortcode('kealoa_rounds_table', [$this, 'render_rounds_table']);
        add_shortcode('kealoa_rounds_stats', [$this, 'render_rounds_stats']);
        add_shortcode('kealoa_round', [$this, 'render_round']);
        add_shortcode('kealoa_person', [$this, 'render_person']);
        add_shortcode('kealoa_persons_table', [$this, 'render_persons_table']);
        add_shortcode('kealoa_constructors_table', [$this, 'render_constructors_table']);
        add_shortcode('kealoa_editors_table', [$this, 'render_editors_table']);
        add_shortcode('kealoa_clue_givers_table', [$this, 'render_clue_givers_table']);
        add_shortcode('kealoa_hosts_table', [$this, 'render_hosts_table']);
        add_shortcode('kealoa_puzzles_table', [$this, 'render_puzzles_table']);
        add_shortcode('kealoa_puzzle', [$this, 'render_puzzle']);
        add_shortcode('kealoa_predictions', [$this, 'render_predictions']);
        add_shortcode('kealoa_version', [$this, 'render_version']);
    }

    // =========================================================================
    // TRANSIENT CACHE HELPERS
    // =========================================================================

    /**
     * Get the current cache version number.
     *
     * Every transient key includes this version so that bumping
     * the version effectively invalidates all cached output.
     */
    private function get_cache_version(): string {
        return get_option('kealoa_cache_version', '1');
    }

    /**
     * Build a namespaced transient key.
     */
    private function cache_key(string $name): string {
        return 'kealoa_v' . $this->get_cache_version() . '_' . $name;
    }

    /**
     * Return cached HTML or run the renderer, cache its output, and return it.
     *
     * @param string   $name     Short cache name (e.g. "person_42").
     * @param callable $renderer Zero-arg callable that returns an HTML string.
     */
    private function get_cached_or_render(string $name, callable $renderer): string {
        $key = $this->cache_key($name);

        $cached = get_transient($key);
        if ($cached !== false) {
            return $cached;
        }

        $html = $renderer();
        set_transient($key, $html, self::CACHE_TTL);
        return $html;
    }

    /**
     * Flush all KEALOA transient caches by incrementing the cache version.
     *
     * Old transients are left to expire naturally via their TTL.
     * Also clears WP Super Cache / WP page caches if available.
     */
    public static function flush_all_caches(): void {
        $version = (int) get_option('kealoa_cache_version', '1');
        update_option('kealoa_cache_version', (string) ($version + 1));

        // Clear WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }

        // Clear WP object cache (Memcached / Redis, etc.)
        wp_cache_flush();
    }

    /**
     * Render rounds stats shortcode
     *
     * [kealoa_rounds_stats]
     */
    public function render_rounds_stats(array $atts = []): string {
        return $this->get_cached_or_render('rounds_stats', function () {
            $overview = $this->db->get_rounds_overview_stats();
            if (empty($overview)) {
                return '<p class="kealoa-no-data">' . esc_html__('No KEALOA round statistics found.', 'kealoa-reference') . '</p>';
            }
            return $this->render_rounds_stats_html($overview);
        });
    }

    /**
     * Render rounds stats HTML (shared helper)
     */
    private function render_rounds_stats_html(object $overview): string {
        ob_start();
        ?>
        <div class="kealoa-stats-grid">
            <div class="kealoa-stat-card">
                <span class="kealoa-stat-value"><?php echo esc_html($overview->total_players); ?></span>
                <span class="kealoa-stat-label"><?php esc_html_e('Players', 'kealoa-reference'); ?></span>
            </div>
            <div class="kealoa-stat-card">
                <span class="kealoa-stat-value"><?php echo esc_html($overview->total_rounds); ?></span>
                <span class="kealoa-stat-label"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></span>
            </div>
            <div class="kealoa-stat-card">
                <span class="kealoa-stat-value"><?php echo esc_html($overview->total_puzzles); ?></span>
                <span class="kealoa-stat-label"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></span>
            </div>
            <div class="kealoa-stat-card">
                <span class="kealoa-stat-value"><?php echo esc_html($overview->total_constructors); ?></span>
                <span class="kealoa-stat-label"><?php esc_html_e('Constructors', 'kealoa-reference'); ?></span>
            </div>
            <div class="kealoa-stat-card">
                <span class="kealoa-stat-value"><?php echo esc_html($overview->total_clues); ?></span>
                <span class="kealoa-stat-label"><?php esc_html_e('Clues', 'kealoa-reference'); ?></span>
            </div>
            <div class="kealoa-stat-card">
                <span class="kealoa-stat-value"><?php echo esc_html($overview->total_guesses); ?></span>
                <span class="kealoa-stat-label"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></span>
            </div>
            <div class="kealoa-stat-card">
                <span class="kealoa-stat-value"><?php echo esc_html($overview->total_correct); ?></span>
                <span class="kealoa-stat-label"><?php esc_html_e('Correct', 'kealoa-reference'); ?></span>
            </div>
            <div class="kealoa-stat-card">
                <span class="kealoa-stat-value"><?php echo Kealoa_Formatter::format_percentage($overview->accuracy); ?></span>
                <span class="kealoa-stat-label"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render rounds table shortcode
     *
     * [kealoa_rounds_table]
     */
    public function render_rounds_table(array $atts = []): string {
        $atts = shortcode_atts([
        'limit' => 0,
        'order' => 'DESC',
    ], $atts, 'kealoa_rounds_table');

        $cache_name = 'rounds_table_' . (int) $atts['limit'] . '_' . $atts['order'];
        return $this->get_cached_or_render($cache_name, function () use ($atts) {

        $rounds = $this->db->get_rounds([
            'limit' => (int) $atts['limit'],
            'order' => $atts['order'],
        ]);

        if (empty($rounds)) {
            return '<p class="kealoa-no-data">' . esc_html__('No KEALOA rounds found.', 'kealoa-reference') . '</p>';
        }

        $overview = $this->db->get_rounds_overview_stats();

        ob_start();
        ?>
        <div class="kealoa-rounds-table-wrapper">

            <div class="kealoa-tabs">
                <div class="kealoa-tab-nav">
                    <button class="kealoa-tab-button active" data-tab="rounds-played"><?php esc_html_e('Rounds Played', 'kealoa-reference'); ?></button>
                    <button class="kealoa-tab-button" data-tab="detailed-stats"><?php esc_html_e('Detailed Stats', 'kealoa-reference'); ?></button>
                    <button class="kealoa-tab-button" data-tab="summary-stats"><?php esc_html_e('Summary Stats', 'kealoa-reference'); ?></button>
                    <button class="kealoa-tab-button" data-tab="rounds-curiosities"><?php esc_html_e('Curiosities', 'kealoa-reference'); ?></button>
                </div>

                <div class="kealoa-tab-panel active" data-tab="rounds-played">

            <h3><?php esc_html_e('All Rounds', 'kealoa-reference'); ?></h3>
            <p class="kealoa-section-description"><?php esc_html_e('Complete list of all rounds played with results and key statistics.', 'kealoa-reference'); ?></p>
            <div class="kealoa-filter-controls" data-target="kealoa-rounds-table">
                <div class="kealoa-filter-row">
                    <div class="kealoa-filter-group">
                        <label for="kealoa-rd-date-min"><?php esc_html_e('Date Range', 'kealoa-reference'); ?></label>
                        <div class="kealoa-filter-range">
                            <input type="date" id="kealoa-rd-date-min" class="kealoa-filter-input" data-filter="date-min" data-col="0">
                            <span class="kealoa-filter-range-sep">&ndash;</span>
                            <input type="date" id="kealoa-rd-date-max" class="kealoa-filter-input" data-filter="date-max" data-col="0">
                        </div>
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-rd-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                        <input type="text" id="kealoa-rd-search" class="kealoa-filter-input" data-filter="search" data-col="1" placeholder="<?php esc_attr_e('Solution words...', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-rd-type"><?php esc_html_e('Type', 'kealoa-reference'); ?></label>
                        <select id="kealoa-rd-type" class="kealoa-filter-select" data-filter="exact" data-col="2">
                            <option value=""><?php esc_html_e('All Types', 'kealoa-reference'); ?></option>
                            <option value="true"><?php esc_html_e('True', 'kealoa-reference'); ?></option>
                            <option value="near"><?php esc_html_e('Near', 'kealoa-reference'); ?></option>
                            <option value="free"><?php esc_html_e('Free', 'kealoa-reference'); ?></option>
                        </select>
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-rd-player"><?php esc_html_e('Player', 'kealoa-reference'); ?></label>
                        <input type="text" id="kealoa-rd-player" class="kealoa-filter-input" data-filter="search" data-col="3" placeholder="<?php esc_attr_e('Player name...', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-rd-desc"><?php esc_html_e('Description', 'kealoa-reference'); ?></label>
                        <input type="text" id="kealoa-rd-desc" class="kealoa-filter-input" data-filter="search" data-col="4" placeholder="<?php esc_attr_e('Description...', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group kealoa-filter-actions">
                        <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                        <span class="kealoa-filter-count"></span>
                    </div>
                </div>
            </div>
            <div class="kealoa-table-scroll">
            <table class="kealoa-table kealoa-rounds-table" id="kealoa-rounds-table">
                <thead>
                    <tr>
                        <th data-sort="date" data-default-sort="desc"><?php esc_html_e('Date', 'kealoa-reference'); ?></th>
                        <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                        <th data-sort="text"><?php esc_html_e('Type', 'kealoa-reference'); ?></th>
                        <th><?php esc_html_e('Results', 'kealoa-reference'); ?></th>
                        <th data-sort="text"><?php esc_html_e('Description', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Bulk pre-fetch data for all rounds
                    $all_round_ids = array_map(function ($r) { return (int) $r->id; }, $rounds);
                    $bulk_solutions = $this->db->get_round_solutions_bulk($all_round_ids);
                    $bulk_clue_counts = $this->db->get_round_clue_counts_bulk($all_round_ids);
                    $bulk_guesser_results = $this->db->get_round_guesser_results_bulk($all_round_ids);
                    $rounds_per_date = $this->db->get_rounds_per_date_counts();
                    ?>
                    <?php foreach ($rounds as $round): ?>
                        <?php
                        $rid = (int) $round->id;
                        $solutions = $bulk_solutions[$rid] ?? [];
                        $clue_count = $bulk_clue_counts[$rid] ?? 0;
                        $guesser_results = $bulk_guesser_results[$rid] ?? [];
                        $round_num = (int) ($round->round_number ?? 1);
                        $date_count = $rounds_per_date[$round->round_date] ?? 1;
                        ?>
                        <tr>
                            <td class="kealoa-date-cell" data-sort-value="<?php echo esc_attr(date('Ymd', strtotime($round->round_date)) * 100 + $round_num); ?>">
                                <?php
                                echo Kealoa_Formatter::format_round_date_link((int) $round->game_number, $round->round_date);
                                if ($date_count > 1) {
                                    echo ' <span class="kealoa-round-number">(#' . esc_html($round_num) . ')</span>';
                                }
                                ?>
                            </td>
                            <td class="kealoa-solutions-cell">
                                <?php echo Kealoa_Formatter::format_solution_words_link((int) $round->game_number, $solutions); ?>
                            </td>
                            <td class="kealoa-type-cell">
                                <?php
                                $kealoa_type = Kealoa_Formatter::classify_kealoa_type($solutions);
                                echo esc_html(Kealoa_Formatter::format_kealoa_type_label($kealoa_type));
                                ?>
                            </td>
                            <td class="kealoa-results-cell">
                                <?php echo Kealoa_Formatter::format_guesser_results($guesser_results, $clue_count); ?>
                            </td>
                            <td class="kealoa-description-cell">
                                <?php echo esc_html($round->description ?? ''); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>

                </div><!-- end Rounds Played tab -->

                <div class="kealoa-tab-panel" data-tab="summary-stats">
            <?php echo $this->render_rounds_stats_html($overview); ?>

            <?php $yearly_stats = $this->db->get_rounds_stats_by_year(); ?>
            <?php if (!empty($yearly_stats)): ?>
            <h3><?php esc_html_e('Statistics by Year', 'kealoa-reference'); ?></h3>
            <p class="kealoa-section-description"><?php esc_html_e('Year-by-year breakdown of rounds played, clues given, and overall accuracy.', 'kealoa-reference'); ?></p>
            <div class="kealoa-table-scroll">
            <table class="kealoa-table kealoa-year-table">
                <thead>
                    <tr>
                        <th data-sort="number"><?php esc_html_e('Year', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Clues', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($yearly_stats as $ys): ?>
                        <tr>
                            <td><?php echo esc_html($ys->year); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $ys->total_rounds)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $ys->total_clues)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $ys->total_guesses)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $ys->total_correct)); ?></td>
                            <?php
                            $pct = (int) $ys->total_guesses > 0
                                ? ((int) $ys->total_correct / (int) $ys->total_guesses) * 100
                                : 0;
                            ?>
                            <td data-value="<?php echo esc_attr(number_format((float) $pct, 2, '.', '')); ?>">
                                <?php echo Kealoa_Formatter::format_percentage($pct); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>

            <?php
            $matrix_data = $this->db->get_answer_by_clue_matrix();
            // Group data by solution count
            $by_solution_count = [];
            foreach ($matrix_data as $row) {
                $sc = (int) $row->solution_count;
                $by_solution_count[$sc][] = $row;
            }
            ksort($by_solution_count);
            ?>
            <?php foreach ($by_solution_count as $sol_count => $sc_data): ?>
            <h3><?php echo esc_html(sprintf(
                /* translators: %d = number of answer words */
                __('Answer Frequency (%d Possible Answers)', 'kealoa-reference'),
                $sol_count
            )); ?></h3>
            <p class="kealoa-section-description"><?php esc_html_e('How often each answer word appeared at each clue position.', 'kealoa-reference'); ?></p>
            <div class="kealoa-table-scroll">
            <table class="kealoa-table kealoa-show-empty-cols">
                <thead>
                    <tr>
                        <th data-sort="number"><?php esc_html_e('Clue #', 'kealoa-reference'); ?></th>
                        <?php for ($an = 1; $an <= $sol_count; $an++): ?>
                            <th data-sort="number"><?php echo esc_html(sprintf(
                                /* translators: %d = answer number */
                                __('Answer %d', 'kealoa-reference'),
                                $an
                            )); ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $matrix = [];
                    $clue_numbers = [];
                    foreach ($sc_data as $row) {
                        $cn = (int) $row->clue_number;
                        $an = (int) $row->answer_number;
                        $clue_numbers[$cn] = true;
                        $matrix[$cn][$an] = (int) $row->clue_count;
                    }
                    ksort($clue_numbers);
                    ?>
                    <?php foreach (array_keys($clue_numbers) as $cn): ?>
                        <?php
                        $row_total = 0;
                        for ($an = 1; $an <= $sol_count; $an++) {
                            $row_total += $matrix[$cn][$an] ?? 0;
                        }
                        ?>
                        <tr>
                            <td><?php echo esc_html($cn); ?></td>
                            <?php for ($an = 1; $an <= $sol_count; $an++):
                                $count = $matrix[$cn][$an] ?? 0;
                                $freq = $row_total > 0 ? ($count / $row_total) * 100 : 0;
                            ?>
                                <td data-value="<?php echo esc_attr(number_format((float) $freq, 2, '.', '')); ?>"><?php echo $count > 0 ? Kealoa_Formatter::format_percentage($freq) : '—'; ?></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php
                    $col_totals = [];
                    $grand_total = 0;
                    for ($an = 1; $an <= $sol_count; $an++) {
                        $col_totals[$an] = 0;
                        foreach (array_keys($clue_numbers) as $cn) {
                            $col_totals[$an] += $matrix[$cn][$an] ?? 0;
                        }
                        $grand_total += $col_totals[$an];
                    }
                    ?>
                    <tr>
                        <td><strong><?php esc_html_e('All', 'kealoa-reference'); ?></strong></td>
                        <?php for ($an = 1; $an <= $sol_count; $an++):
                            $all_freq = $grand_total > 0 ? ($col_totals[$an] / $grand_total) * 100 : 0;
                        ?>
                            <td data-value="<?php echo esc_attr(number_format((float) $all_freq, 2, '.', '')); ?>"><strong><?php echo $col_totals[$an] > 0 ? Kealoa_Formatter::format_percentage($all_freq) : '—'; ?></strong></td>
                        <?php endfor; ?>
                    </tr>
                </tbody>
            </table>
            </div>
            <?php endforeach; ?>

            <?php
            // Build Alternation vs Accuracy table
            $round_guess_stats = $this->db->get_rounds_guess_stats();
            $all_rgs_ids = array_map(function ($rgs) { return (int) $rgs->round_id; }, $round_guess_stats);
            $all_alternation_pcts = $this->db->get_round_alternation_pcts_bulk($all_rgs_ids);
            $alternation_buckets = [];
            foreach ($round_guess_stats as $rgs) {
                $rid = (int) $rgs->round_id;
                $alternation = $all_alternation_pcts[$rid] ?? 0.0;
                $bucket = (int) floor($alternation / 10) * 10;
                if ($bucket >= 100) {
                    $bucket = 90; // merge 100% into 90-100 bucket
                }
                if (!isset($alternation_buckets[$bucket])) {
                    $alternation_buckets[$bucket] = ['rounds' => 0, 'guesses' => 0, 'correct' => 0];
                }
                $alternation_buckets[$bucket]['rounds']++;
                $alternation_buckets[$bucket]['guesses'] += (int) $rgs->total_guesses;
                $alternation_buckets[$bucket]['correct'] += (int) $rgs->correct_guesses;
            }
            ksort($alternation_buckets);
            ?>
            <?php if (!empty($alternation_buckets)): ?>
            <h3><?php esc_html_e('Answer Alternation', 'kealoa-reference'); ?> <?php esc_html_e('vs Accuracy', 'kealoa-reference'); ?></h3>
            <p class="kealoa-section-description"><?php esc_html_e('How accuracy varies based on how evenly the correct answers were distributed across the solution words.', 'kealoa-reference'); ?></p>
            <div class="kealoa-table-scroll">
            <table class="kealoa-table kealoa-alternation-table">
                <thead>
                    <tr>
                        <th data-sort="text"><?php esc_html_e('Answer Alternation', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alternation_buckets as $bucket => $stats): ?>
                        <?php
                        $bucket_label = $bucket . '%–' . ($bucket + 10) . '%';
                        $bucket_acc = $stats['guesses'] > 0
                            ? ($stats['correct'] / $stats['guesses']) * 100
                            : 0;
                        ?>
                        <tr>
                            <td><?php echo esc_html($bucket_label); ?></td>
                            <td><?php echo esc_html(number_format_i18n($stats['rounds'])); ?></td>
                            <td><?php echo esc_html(number_format_i18n($stats['guesses'])); ?></td>
                            <td><?php echo esc_html(number_format_i18n($stats['correct'])); ?></td>
                            <td data-value="<?php echo esc_attr(number_format((float) $bucket_acc, 2, '.', '')); ?>">
                                <?php echo Kealoa_Formatter::format_percentage($bucket_acc); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>

            <?php
            // Build Guess Alternation vs Accuracy table (per-player)
            $all_guess_alt = $this->db->get_round_guess_alternation_per_player_bulk($all_rgs_ids);
            $guess_alt_buckets = [];
            foreach ($round_guess_stats as $rgs) {
                $rid = (int) $rgs->round_id;
                $player_alts = $all_guess_alt[$rid] ?? [];
                if (empty($player_alts)) {
                    continue;
                }
                // Each player-in-a-round is one data point
                // We need per-player guess counts, so fetch guesser results
                $rgs_total = (int) $rgs->total_guesses;
                $rgs_correct = (int) $rgs->correct_guesses;
                $n_players = count($player_alts);
                foreach ($player_alts as $pa) {
                    $bucket = (int) floor($pa->alternation_pct / 10) * 10;
                    if ($bucket >= 100) {
                        $bucket = 90;
                    }
                    if (!isset($guess_alt_buckets[$bucket])) {
                        $guess_alt_buckets[$bucket] = ['entries' => 0, 'guesses' => 0, 'correct' => 0];
                    }
                    $guess_alt_buckets[$bucket]['entries']++;
                    // Approximate: distribute round guesses evenly among players
                    $guess_alt_buckets[$bucket]['guesses'] += (int) round($rgs_total / $n_players);
                    $guess_alt_buckets[$bucket]['correct'] += (int) round($rgs_correct / $n_players);
                }
            }
            ksort($guess_alt_buckets);
            ?>
            <?php if (!empty($guess_alt_buckets)): ?>
            <h3><?php esc_html_e('Guess Alternation', 'kealoa-reference'); ?> <?php esc_html_e('vs Accuracy', 'kealoa-reference'); ?></h3>
            <p class="kealoa-section-description"><?php esc_html_e('How accuracy varies based on how evenly guesses were spread across the solution words.', 'kealoa-reference'); ?></p>
            <div class="kealoa-table-scroll">
            <table class="kealoa-table kealoa-alternation-table">
                <thead>
                    <tr>
                        <th data-sort="text"><?php esc_html_e('Guess Alternation', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($guess_alt_buckets as $bucket => $gstats): ?>
                        <?php
                        $bucket_label = $bucket . '%–' . ($bucket + 10) . '%';
                        $gbucket_acc = $gstats['guesses'] > 0
                            ? ($gstats['correct'] / $gstats['guesses']) * 100
                            : 0;
                        ?>
                        <tr>
                            <td><?php echo esc_html($bucket_label); ?></td>
                            <td><?php echo esc_html(number_format_i18n($gstats['guesses'])); ?></td>
                            <td><?php echo esc_html(number_format_i18n($gstats['correct'])); ?></td>
                            <td data-value="<?php echo esc_attr(number_format((float) $gbucket_acc, 2, '.', '')); ?>">
                                <?php echo Kealoa_Formatter::format_percentage($gbucket_acc); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>

            <?php $alt_by_clue = $this->db->get_alternation_by_clue_number(); ?>
            <?php if (!empty($alt_by_clue)): ?>
            <h3><?php esc_html_e('Answer Alternation', 'kealoa-reference'); ?> <?php esc_html_e('by Clue Number', 'kealoa-reference'); ?></h3>
            <p class="kealoa-section-description"><?php esc_html_e('How often the correct answer alternated to a different solution word from one clue to the next.', 'kealoa-reference'); ?></p>
            <div class="kealoa-table-scroll">
            <table class="kealoa-table">
                <thead>
                    <tr>
                        <th data-sort="number"><?php esc_html_e('Clue', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Chances', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Taken', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('%', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alt_by_clue as $row):
                        $alt_pct = (int) $row->chances > 0
                            ? ((int) $row->taken / (int) $row->chances) * 100
                            : 0;
                    ?>
                        <tr>
                            <td><?php echo esc_html((int) $row->clue_number); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $row->chances)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $row->taken)); ?></td>
                            <td data-value="<?php echo esc_attr(number_format((float) $alt_pct, 2, '.', '')); ?>">
                                <?php echo Kealoa_Formatter::format_percentage($alt_pct); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>

            <?php $guess_alt_by_clue = $this->db->get_guess_alternation_by_clue_number(); ?>
            <?php if (!empty($guess_alt_by_clue)): ?>
            <h3><?php esc_html_e('Guess Alternation', 'kealoa-reference'); ?> <?php esc_html_e('by Clue Number', 'kealoa-reference'); ?></h3>
            <p class="kealoa-section-description"><?php esc_html_e('How often the player\'s guess alternated to a different solution word from one clue to the next.', 'kealoa-reference'); ?></p>
            <div class="kealoa-table-scroll">
            <table class="kealoa-table">
                <thead>
                    <tr>
                        <th data-sort="number"><?php esc_html_e('Clue', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Chances', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Taken', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('%', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($guess_alt_by_clue as $grow):
                        $galt_pct = (int) $grow->chances > 0
                            ? ((int) $grow->taken / (int) $grow->chances) * 100
                            : 0;
                    ?>
                        <tr>
                            <td><?php echo esc_html((int) $grow->clue_number); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $grow->chances)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $grow->taken)); ?></td>
                            <td data-value="<?php echo esc_attr(number_format((float) $galt_pct, 2, '.', '')); ?>">
                                <?php echo Kealoa_Formatter::format_percentage($galt_pct); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>

            <?php $age_by_pos = $this->db->get_avg_clue_age_by_position(); ?>
            <?php if (!empty($age_by_pos)): ?>
            <h3><?php esc_html_e('Clue Position', 'kealoa-reference'); ?> <?php esc_html_e('vs Average Clue Age', 'kealoa-reference'); ?></h3>
            <p class="kealoa-section-description"><?php esc_html_e('The average age of the crossword puzzle at each clue position in the round.', 'kealoa-reference'); ?></p>
            <div class="kealoa-table-scroll">
            <table class="kealoa-table">
                <thead>
                    <tr>
                        <th data-sort="number"><?php esc_html_e('Clue #', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Clues', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Avg Age', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Min', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Max', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($age_by_pos as $ap): ?>
                        <tr>
                            <td><?php echo esc_html((int) $ap->clue_number); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $ap->rounds)); ?></td>
                            <td data-value="<?php echo esc_attr(number_format((float) $ap->avg_age, 0, '.', '')); ?>">
                                <?php echo esc_html(number_format_i18n(round((float) $ap->avg_age))); ?> <?php esc_html_e('days', 'kealoa-reference'); ?>
                            </td>
                            <td data-value="<?php echo esc_attr((int) $ap->min_age); ?>">
                                <?php echo esc_html(number_format_i18n((int) $ap->min_age)); ?>
                            </td>
                            <td data-value="<?php echo esc_attr((int) $ap->max_age); ?>">
                                <?php echo esc_html(number_format_i18n((int) $ap->max_age)); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>

                </div><!-- end Summary Stats tab -->

                <div class="kealoa-tab-panel" data-tab="detailed-stats">

            <h3><?php esc_html_e('Round Details', 'kealoa-reference'); ?></h3>
            <p class="kealoa-section-description"><?php esc_html_e('Detailed statistics for each round with filtering and sorting options.', 'kealoa-reference'); ?></p>
            <div class="kealoa-filter-controls" data-target="kealoa-detailed-stats-table">
                <div class="kealoa-filter-row">
                    <div class="kealoa-filter-group">
                        <label for="kealoa-ds-date-min"><?php esc_html_e('Date Range', 'kealoa-reference'); ?></label>
                        <div class="kealoa-filter-range">
                            <input type="date" id="kealoa-ds-date-min" class="kealoa-filter-input" data-filter="date-min" data-col="0">
                            <span class="kealoa-filter-range-sep">&ndash;</span>
                            <input type="date" id="kealoa-ds-date-max" class="kealoa-filter-input" data-filter="date-max" data-col="0">
                        </div>
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-ds-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                        <input type="text" id="kealoa-ds-search" class="kealoa-filter-input" data-filter="search" data-col="1" placeholder="<?php esc_attr_e('Solution words...', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-ds-type"><?php esc_html_e('Type', 'kealoa-reference'); ?></label>
                        <select id="kealoa-ds-type" class="kealoa-filter-select" data-filter="exact" data-col="2">
                            <option value=""><?php esc_html_e('All Types', 'kealoa-reference'); ?></option>
                            <option value="true"><?php esc_html_e('True', 'kealoa-reference'); ?></option>
                            <option value="near"><?php esc_html_e('Near', 'kealoa-reference'); ?></option>
                            <option value="free"><?php esc_html_e('Free', 'kealoa-reference'); ?></option>
                        </select>
                    </div>
                    <div class="kealoa-filter-group kealoa-filter-actions">
                        <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                        <span class="kealoa-filter-count"></span>
                    </div>
                </div>
                <div class="kealoa-filter-row">
                    <div class="kealoa-filter-group">
                        <label for="kealoa-ds-alt-min"><?php esc_html_e('Ans. Alternation', 'kealoa-reference'); ?> <?php esc_html_e('Range', 'kealoa-reference'); ?></label>
                        <div class="kealoa-filter-range">
                            <input type="number" id="kealoa-ds-alt-min" class="kealoa-filter-input" data-filter="range-min" data-col="3" min="0" max="100" placeholder="<?php esc_attr_e('0%', 'kealoa-reference'); ?>">
                            <span class="kealoa-filter-range-sep">&ndash;</span>
                            <input type="number" id="kealoa-ds-alt-max" class="kealoa-filter-input" data-filter="range-max" data-col="3" min="0" max="100" placeholder="<?php esc_attr_e('100%', 'kealoa-reference'); ?>">
                        </div>
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-ds-even-min"><?php esc_html_e('Ans. Evenness', 'kealoa-reference'); ?> <?php esc_html_e('Range', 'kealoa-reference'); ?></label>
                        <div class="kealoa-filter-range">
                            <input type="number" id="kealoa-ds-even-min" class="kealoa-filter-input" data-filter="range-min" data-col="4" min="0" max="100" placeholder="<?php esc_attr_e('0%', 'kealoa-reference'); ?>">
                            <span class="kealoa-filter-range-sep">&ndash;</span>
                            <input type="number" id="kealoa-ds-even-max" class="kealoa-filter-input" data-filter="range-max" data-col="4" min="0" max="100" placeholder="<?php esc_attr_e('100%', 'kealoa-reference'); ?>">
                        </div>
                    </div>
                </div>
                <div class="kealoa-filter-row">
                    <div class="kealoa-filter-group">
                        <label for="kealoa-ds-galt-min"><?php esc_html_e('Guess Alt.', 'kealoa-reference'); ?> <?php esc_html_e('Range', 'kealoa-reference'); ?></label>
                        <div class="kealoa-filter-range">
                            <input type="number" id="kealoa-ds-galt-min" class="kealoa-filter-input" data-filter="range-min" data-col="5" min="0" max="100" placeholder="<?php esc_attr_e('0%', 'kealoa-reference'); ?>">
                            <span class="kealoa-filter-range-sep">&ndash;</span>
                            <input type="number" id="kealoa-ds-galt-max" class="kealoa-filter-input" data-filter="range-max" data-col="5" min="0" max="100" placeholder="<?php esc_attr_e('100%', 'kealoa-reference'); ?>">
                        </div>
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-ds-geven-min"><?php esc_html_e('Guess Even.', 'kealoa-reference'); ?> <?php esc_html_e('Range', 'kealoa-reference'); ?></label>
                        <div class="kealoa-filter-range">
                            <input type="number" id="kealoa-ds-geven-min" class="kealoa-filter-input" data-filter="range-min" data-col="6" min="0" max="100" placeholder="<?php esc_attr_e('0%', 'kealoa-reference'); ?>">
                            <span class="kealoa-filter-range-sep">&ndash;</span>
                            <input type="number" id="kealoa-ds-geven-max" class="kealoa-filter-input" data-filter="range-max" data-col="6" min="0" max="100" placeholder="<?php esc_attr_e('100%', 'kealoa-reference'); ?>">
                        </div>
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-ds-age-min"><?php esc_html_e('Clue Age Range', 'kealoa-reference'); ?></label>
                        <div class="kealoa-filter-range">
                            <input type="number" id="kealoa-ds-age-min" class="kealoa-filter-input" data-filter="range-min" data-col="7" min="0" placeholder="<?php esc_attr_e('Min days', 'kealoa-reference'); ?>">
                            <span class="kealoa-filter-range-sep">&ndash;</span>
                            <input type="number" id="kealoa-ds-age-max" class="kealoa-filter-input" data-filter="range-max" data-col="7" min="0" placeholder="<?php esc_attr_e('Max days', 'kealoa-reference'); ?>">
                        </div>
                    </div>
                </div>
            </div>
            <div class="kealoa-table-scroll">
            <table class="kealoa-table kealoa-detailed-stats-table" id="kealoa-detailed-stats-table">
                <thead>
                    <tr>
                        <th data-sort="date" data-default-sort="desc"><?php esc_html_e('Date', 'kealoa-reference'); ?></th>
                        <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                        <th data-sort="text"><?php esc_html_e('Type', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Ans. Alt.', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Ans. Even.', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Guess Alt.', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Guess Even.', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Avg Clue Age', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Bulk pre-fetch data for all rounds
                    $ds_round_ids = array_map(function ($r) { return (int) $r->id; }, $rounds);
                    $ds_solutions = $this->db->get_round_solutions_bulk($ds_round_ids);
                    $ds_alternation = $this->db->get_round_alternation_pcts_bulk($ds_round_ids);
                    $ds_evenness = $this->db->get_round_evenness_bulk($ds_round_ids);
                    $ds_guess_alt = $this->db->get_round_guess_alternation_per_player_bulk($ds_round_ids);
                    $ds_guess_even = $this->db->get_round_guess_evenness_per_player_bulk($ds_round_ids);
                    $ds_clue_ages = $this->db->get_round_clue_age_stats_bulk($ds_round_ids);
                    $ds_rounds_per_date = $rounds_per_date ?? $this->db->get_rounds_per_date_counts();
                    ?>
                    <?php foreach ($rounds as $round): ?>
                        <?php
                        $rid = (int) $round->id;
                        $solutions = $ds_solutions[$rid] ?? [];
                        $alt_pct = $ds_alternation[$rid] ?? 0.0;
                        $even_pct = $ds_evenness[$rid] ?? 0.0;
                        $age_stats = $ds_clue_ages[$rid] ?? null;
                        // Compute average guess alternation across players
                        $ds_galt_players = $ds_guess_alt[$rid] ?? [];
                        $ds_galt_avg = 0.0;
                        if (!empty($ds_galt_players)) {
                            $ds_galt_sum = 0.0;
                            foreach ($ds_galt_players as $dgp) {
                                $ds_galt_sum += $dgp->alternation_pct;
                            }
                            $ds_galt_avg = $ds_galt_sum / count($ds_galt_players);
                        }
                        // Compute average guess evenness across players
                        $ds_geven_players = $ds_guess_even[$rid] ?? [];
                        $ds_geven_avg = 0.0;
                        if (!empty($ds_geven_players)) {
                            $ds_geven_sum = 0.0;
                            foreach ($ds_geven_players as $dgep) {
                                $ds_geven_sum += $dgep->evenness_pct;
                            }
                            $ds_geven_avg = $ds_geven_sum / count($ds_geven_players);
                        }
                        $round_num = (int) ($round->round_number ?? 1);
                        $date_count = $ds_rounds_per_date[$round->round_date] ?? 1;
                        ?>
                        <tr>
                            <td class="kealoa-date-cell" data-sort-value="<?php echo esc_attr(date('Ymd', strtotime($round->round_date)) * 100 + $round_num); ?>">
                                <?php
                                echo Kealoa_Formatter::format_round_date_link((int) $round->game_number, $round->round_date);
                                if ($date_count > 1) {
                                    echo ' <span class="kealoa-round-number">(#' . esc_html($round_num) . ')</span>';
                                }
                                ?>
                            </td>
                            <td class="kealoa-solutions-cell">
                                <?php echo Kealoa_Formatter::format_solution_words_link((int) $round->game_number, $solutions); ?>
                            </td>
                            <td><?php
                                $ds_type = Kealoa_Formatter::classify_kealoa_type($solutions);
                                echo esc_html(Kealoa_Formatter::format_kealoa_type_label($ds_type));
                            ?></td>
                            <td data-value="<?php echo esc_attr(number_format($alt_pct, 2, '.', '')); ?>">
                                <?php echo Kealoa_Formatter::format_percentage($alt_pct); ?>
                            </td>
                            <td data-value="<?php echo esc_attr(number_format($even_pct, 2, '.', '')); ?>">
                                <?php echo Kealoa_Formatter::format_percentage($even_pct); ?>
                            </td>
                            <td data-value="<?php echo esc_attr(number_format($ds_galt_avg, 2, '.', '')); ?>">
                                <?php echo !empty($ds_galt_players) ? Kealoa_Formatter::format_percentage($ds_galt_avg) : '—'; ?>
                            </td>
                            <td data-value="<?php echo esc_attr(number_format($ds_geven_avg, 2, '.', '')); ?>">
                                <?php echo !empty($ds_geven_players) ? Kealoa_Formatter::format_percentage($ds_geven_avg) : '—'; ?>
                            </td>
                            <td data-value="<?php echo esc_attr($age_stats ? number_format($age_stats->mean, 0, '.', '') : ''); ?>">
                                <?php echo $age_stats ? esc_html(number_format_i18n($age_stats->mean, 0) . ' days') : '—'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>

                </div><!-- end Detailed Stats tab -->

                <div class="kealoa-tab-panel" data-tab="rounds-curiosities">
                <?php
                $rounds_without_puzzles = $this->db->get_rounds_without_puzzles();
                $rounds_with_unused_answers = $this->db->get_rounds_with_unused_answers();
                $rounds_with_multiple_players = $this->db->get_rounds_with_multiple_players();
                $rounds_with_repeated_constructor = $this->db->get_rounds_with_repeated_constructor();
                $rounds_hit_for_cycle = $this->db->get_rounds_hit_for_cycle();
                $has_curiosities = !empty($rounds_without_puzzles) || !empty($rounds_with_unused_answers) || !empty($rounds_with_multiple_players) || !empty($rounds_with_repeated_constructor) || !empty($rounds_hit_for_cycle);

                if (!$has_curiosities):
                ?>
                    <p class="kealoa-no-data"><?php esc_html_e('No curiosities found yet.', 'kealoa-reference'); ?></p>
                <?php else: ?>
                    <?php if (!empty($rounds_without_puzzles)): ?>
                    <?php
                    // Bulk pre-fetch for rounds without puzzles
                    $np_round_ids = array_map(fn($r) => (int) $r->id, $rounds_without_puzzles);
                    $np_solutions_map = $this->db->get_round_solutions_bulk($np_round_ids);
                    $np_rounds_per_date = $this->db->get_rounds_per_date_counts();
                    ?>
                    <div class="kealoa-curiosities-section">
                    <h3><?php esc_html_e('Rounds with Text-Only Clues', 'kealoa-reference'); ?></h3>
                    <p class="kealoa-section-description"><?php esc_html_e('These rounds have clues with text descriptions but no linked crossword puzzles.', 'kealoa-reference'); ?></p>
                    <div class="kealoa-puzzles-table-wrapper">
                        <div class="kealoa-table-scroll">
                            <table class="kealoa-table kealoa-rounds-table" id="kealoa-rounds-curiosities-no-puzzle-table">
                                <thead>
                                    <tr>
                                        <th data-sort="date" data-default-sort="desc"><?php esc_html_e('Date', 'kealoa-reference'); ?></th>
                                        <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                                        <th data-sort="text"><?php esc_html_e('Description', 'kealoa-reference'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rounds_without_puzzles as $round):
                                        $rid = (int) $round->id;
                                        $gn = (int) $round->game_number;
                                        $solutions = $np_solutions_map[$rid] ?? [];
                                        $round_num = (int) ($round->round_number ?? 1);
                                        $date_count = $np_rounds_per_date[$round->round_date] ?? 1;
                                    ?>
                                    <tr>
                                        <td class="kealoa-date-cell" data-sort-value="<?php echo esc_attr(date('Ymd', strtotime($round->round_date)) * 100 + $round_num); ?>">
                                            <?php
                                            echo Kealoa_Formatter::format_round_date_link($gn, $round->round_date);
                                            if ($date_count > 1) {
                                                echo ' <span class="kealoa-round-number">(#' . esc_html($round_num) . ')</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="kealoa-solutions-cell">
                                            <?php echo Kealoa_Formatter::format_solution_words_link($gn, $solutions); ?>
                                        </td>
                                        <td class="kealoa-description-cell">
                                            <?php echo esc_html($round->description ?? ''); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($rounds_with_unused_answers)): ?>
                    <?php
                    // Bulk pre-fetch for rounds with unused answers
                    $ua_round_ids = array_map(fn($r) => (int) $r->id, $rounds_with_unused_answers);
                    $ua_solutions_map = $this->db->get_round_solutions_bulk($ua_round_ids);
                    if (!isset($np_rounds_per_date)) {
                        $np_rounds_per_date = $this->db->get_rounds_per_date_counts();
                    }
                    ?>
                    <div class="kealoa-curiosities-section">
                    <h3><?php esc_html_e('Rounds With Unused Answer Words', 'kealoa-reference'); ?></h3>
                    <p class="kealoa-section-description"><?php esc_html_e('These rounds have at least one solution word that was never used as a correct answer for any clue.', 'kealoa-reference'); ?></p>
                    <div class="kealoa-puzzles-table-wrapper">
                        <div class="kealoa-table-scroll">
                            <table class="kealoa-table kealoa-rounds-table" id="kealoa-rounds-curiosities-unused-answers-table">
                                <thead>
                                    <tr>
                                        <th data-sort="date" data-default-sort="desc"><?php esc_html_e('Date', 'kealoa-reference'); ?></th>
                                        <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                                        <th data-sort="text"><?php esc_html_e('Unused Words', 'kealoa-reference'); ?></th>
                                        <th data-sort="text"><?php esc_html_e('Description', 'kealoa-reference'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rounds_with_unused_answers as $round):
                                        $rid = (int) $round->id;
                                        $gn = (int) $round->game_number;
                                        $solutions = $ua_solutions_map[$rid] ?? [];
                                        $round_num = (int) ($round->round_number ?? 1);
                                        $date_count = $np_rounds_per_date[$round->round_date] ?? 1;
                                    ?>
                                    <tr>
                                        <td class="kealoa-date-cell" data-sort-value="<?php echo esc_attr(date('Ymd', strtotime($round->round_date)) * 100 + $round_num); ?>">
                                            <?php
                                            echo Kealoa_Formatter::format_round_date_link($gn, $round->round_date);
                                            if ($date_count > 1) {
                                                echo ' <span class="kealoa-round-number">(#' . esc_html($round_num) . ')</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="kealoa-solutions-cell">
                                            <?php echo Kealoa_Formatter::format_solution_words_link($gn, $solutions); ?>
                                        </td>
                                        <td class="kealoa-unused-words-cell">
                                            <?php echo esc_html($round->unused_words); ?>
                                        </td>
                                        <td class="kealoa-description-cell">
                                            <?php echo esc_html($round->description ?? ''); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($rounds_with_multiple_players)): ?>
                    <?php
                    // Bulk pre-fetch for rounds with multiple players
                    $mp_round_ids = array_map(fn($r) => (int) $r->id, $rounds_with_multiple_players);
                    $mp_solutions_map = $this->db->get_round_solutions_bulk($mp_round_ids);
                    if (!isset($np_rounds_per_date)) {
                        $np_rounds_per_date = $this->db->get_rounds_per_date_counts();
                    }
                    ?>
                    <div class="kealoa-curiosities-section">
                    <h3><?php esc_html_e('Rounds with Multiple Players', 'kealoa-reference'); ?></h3>
                    <p class="kealoa-section-description"><?php esc_html_e('These rounds had more than one player guessing answers.', 'kealoa-reference'); ?></p>
                    <div class="kealoa-puzzles-table-wrapper">
                        <div class="kealoa-table-scroll">
                            <table class="kealoa-table kealoa-rounds-table" id="kealoa-rounds-curiosities-multiple-players-table">
                                <thead>
                                    <tr>
                                        <th data-sort="date" data-default-sort="desc"><?php esc_html_e('Date', 'kealoa-reference'); ?></th>
                                        <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                                        <th data-sort="number"><?php esc_html_e('Players', 'kealoa-reference'); ?></th>
                                        <th data-sort="text"><?php esc_html_e('Player Names', 'kealoa-reference'); ?></th>
                                        <th data-sort="text"><?php esc_html_e('Description', 'kealoa-reference'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rounds_with_multiple_players as $round):
                                        $rid = (int) $round->id;
                                        $gn = (int) $round->game_number;
                                        $solutions = $mp_solutions_map[$rid] ?? [];
                                        $round_num = (int) ($round->round_number ?? 1);
                                        $date_count = $np_rounds_per_date[$round->round_date] ?? 1;
                                    ?>
                                    <tr>
                                        <td class="kealoa-date-cell" data-sort-value="<?php echo esc_attr(date('Ymd', strtotime($round->round_date)) * 100 + $round_num); ?>">
                                            <?php
                                            echo Kealoa_Formatter::format_round_date_link($gn, $round->round_date);
                                            if ($date_count > 1) {
                                                echo ' <span class="kealoa-round-number">(#' . esc_html($round_num) . ')</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="kealoa-solutions-cell">
                                            <?php echo Kealoa_Formatter::format_solution_words_link($gn, $solutions); ?>
                                        </td>
                                        <td><?php echo esc_html($round->player_count); ?></td>
                                        <td><?php echo esc_html($round->player_names); ?></td>
                                        <td class="kealoa-description-cell">
                                            <?php echo esc_html($round->description ?? ''); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($rounds_with_repeated_constructor)): ?>
                    <?php
                    // Bulk pre-fetch for rounds with repeated constructor
                    $rc_round_ids = array_map(fn($r) => (int) $r->id, $rounds_with_repeated_constructor);
                    $rc_solutions_map = $this->db->get_round_solutions_bulk($rc_round_ids);
                    if (!isset($np_rounds_per_date)) {
                        $np_rounds_per_date = $this->db->get_rounds_per_date_counts();
                    }
                    ?>
                    <div class="kealoa-curiosities-section">
                    <h3><?php esc_html_e('Repeated Constructors in a Round', 'kealoa-reference'); ?></h3>
                    <p class="kealoa-section-description"><?php esc_html_e('These rounds have a constructor who appears on more than one puzzle.', 'kealoa-reference'); ?></p>
                    <div class="kealoa-puzzles-table-wrapper">
                        <div class="kealoa-table-scroll">
                            <table class="kealoa-table kealoa-rounds-table" id="kealoa-rounds-curiosities-repeated-constructor-table">
                                <thead>
                                    <tr>
                                        <th data-sort="date" data-default-sort="desc"><?php esc_html_e('Date', 'kealoa-reference'); ?></th>
                                        <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                                        <th data-sort="text"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></th>
                                        <th data-sort="number"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></th>
                                        <th data-sort="text"><?php esc_html_e('Description', 'kealoa-reference'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rounds_with_repeated_constructor as $round):
                                        $rid = (int) $round->id;
                                        $gn = (int) $round->game_number;
                                        $solutions = $rc_solutions_map[$rid] ?? [];
                                        $round_num = (int) ($round->round_number ?? 1);
                                        $date_count = $np_rounds_per_date[$round->round_date] ?? 1;
                                    ?>
                                    <tr>
                                        <td class="kealoa-date-cell" data-sort-value="<?php echo esc_attr(date('Ymd', strtotime($round->round_date)) * 100 + $round_num); ?>">
                                            <?php
                                            echo Kealoa_Formatter::format_round_date_link($gn, $round->round_date);
                                            if ($date_count > 1) {
                                                echo ' <span class="kealoa-round-number">(#' . esc_html($round_num) . ')</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="kealoa-solutions-cell">
                                            <?php echo Kealoa_Formatter::format_solution_words_link($gn, $solutions); ?>
                                        </td>
                                        <td><?php echo esc_html($round->constructor_name); ?></td>
                                        <td><?php echo esc_html($round->puzzle_count); ?></td>
                                        <td class="kealoa-description-cell">
                                            <?php echo esc_html($round->description ?? ''); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($rounds_hit_for_cycle)): ?>
                    <?php
                    // Bulk pre-fetch for hit-for-the-cycle rounds
                    $hc_round_ids = array_map(fn($r) => (int) $r->id, $rounds_hit_for_cycle);
                    $hc_solutions_map = $this->db->get_round_solutions_bulk($hc_round_ids);
                    if (!isset($np_rounds_per_date)) {
                        $np_rounds_per_date = $this->db->get_rounds_per_date_counts();
                    }
                    ?>
                    <div class="kealoa-curiosities-section">
                    <h3><?php esc_html_e('Hit for the Cycle', 'kealoa-reference'); ?></h3>
                    <p class="kealoa-section-description"><?php esc_html_e('These rounds featured puzzles from every day of the week (Sunday through Saturday).', 'kealoa-reference'); ?></p>
                    <div class="kealoa-puzzles-table-wrapper">
                        <div class="kealoa-table-scroll">
                            <table class="kealoa-table kealoa-rounds-table" id="kealoa-rounds-curiosities-hit-cycle-table">
                                <thead>
                                    <tr>
                                        <th data-sort="date" data-default-sort="desc"><?php esc_html_e('Date', 'kealoa-reference'); ?></th>
                                        <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                                        <th data-sort="number"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></th>
                                        <th data-sort="text"><?php esc_html_e('Description', 'kealoa-reference'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rounds_hit_for_cycle as $round):
                                        $rid = (int) $round->id;
                                        $gn = (int) $round->game_number;
                                        $solutions = $hc_solutions_map[$rid] ?? [];
                                        $round_num = (int) ($round->round_number ?? 1);
                                        $date_count = $np_rounds_per_date[$round->round_date] ?? 1;
                                    ?>
                                    <tr>
                                        <td class="kealoa-date-cell" data-sort-value="<?php echo esc_attr(date('Ymd', strtotime($round->round_date)) * 100 + $round_num); ?>">
                                            <?php
                                            echo Kealoa_Formatter::format_round_date_link($gn, $round->round_date);
                                            if ($date_count > 1) {
                                                echo ' <span class="kealoa-round-number">(#' . esc_html($round_num) . ')</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="kealoa-solutions-cell">
                                            <?php echo Kealoa_Formatter::format_solution_words_link($gn, $solutions); ?>
                                        </td>
                                        <td><?php echo esc_html($round->puzzle_count); ?></td>
                                        <td class="kealoa-description-cell">
                                            <?php echo esc_html($round->description ?? ''); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                </div><!-- end Rounds Curiosities tab -->

            </div><!-- end kealoa-tabs -->
        </div>
        <?php
        return ob_get_clean();

        }); // end get_cached_or_render
    }

    /**
     * Render single round shortcode
     *
     * [kealoa_round id="X"]
     */
    public function render_round(array $atts = []): string {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts, 'kealoa_round');

        $round_id = (int) $atts['id'];
        if (!$round_id) {
            return '<p class="kealoa-error">' . esc_html__('Please specify a round ID.', 'kealoa-reference') . '</p>';
        }

        $round = $this->db->get_round($round_id);
        if (!$round) {
            return '<p class="kealoa-error">' . esc_html__('Round not found.', 'kealoa-reference') . '</p>';
        }

        $html = $this->get_cached_or_render('round_' . $round_id, function () use ($round_id, $round) {

        $solutions = $this->db->get_round_solutions($round_id);
        $guessers = $this->db->get_round_guessers($round_id);
        $guesser_results = $this->db->get_round_guesser_results($round_id);
        $clues = $this->db->get_round_clues($round_id);
        $clue_giver = $this->db->get_person((int) $round->clue_giver_id);
        $round_num = (int) ($round->round_number ?? 1);
        $rounds_on_date = $this->db->get_rounds_by_date($round->round_date);
        $show_round_num = count($rounds_on_date) > 1;

        ob_start();
        ?>
        <div class="kealoa-round-view">
            <div class="kealoa-round-header">

                <div class="kealoa-round-meta">
                    <?php if (!empty($solutions)): ?>
                    <p>
                        <strong class="kealoa-meta-label"><?php esc_html_e('Words', 'kealoa-reference'); ?></strong>
                        <span><?php
                        $word_links = [];
                        foreach ($solutions as $s) {
                            $word = $s->word;
                            $finder_word = str_replace(' ', '', $word);
                            $word_links[] = '<a href="' . esc_url('https://www.xwordinfo.com/Finder?word=' . rawurlencode($finder_word)) . '" target="_blank" rel="noopener noreferrer">' . esc_html(strtoupper($word)) . '</a>';
                        }
                        echo Kealoa_Formatter::format_list_with_and($word_links);
                        $round_type = Kealoa_Formatter::classify_kealoa_type($solutions);
                        if ($round_type !== '') {
                            echo ' (' . esc_html(Kealoa_Formatter::format_kealoa_type_label($round_type)) . ' KEALOA)';
                        }
                        ?></span>
                    </p>
                    <?php endif; ?>
                    <p>
                        <strong class="kealoa-meta-label"><?php esc_html_e('Date', 'kealoa-reference'); ?></strong>
                        <span><?php
                        echo esc_html(Kealoa_Formatter::format_date($round->round_date));
                        if ($show_round_num) {
                            echo ' <span class="kealoa-round-number">(Round #' . esc_html($round_num) . ')</span>';
                        }
                        ?></span>
                    </p>
                    <p>
                        <strong class="kealoa-meta-label"><?php esc_html_e('Episode', 'kealoa-reference'); ?></strong>
                        <span><?php echo Kealoa_Formatter::format_episode_link((int) $round->episode_number, $round->episode_url ?? null); ?></span>
                    </p>
                    <?php
                    // Count how many clues have each solution word as the correct answer
                    $answer_counts = [];
                    foreach ($clues as $clue) {
                        $answer = strtoupper($clue->correct_answer);
                        $answer_counts[$answer] = ($answer_counts[$answer] ?? 0) + 1;
                    }
                    ?>
                    <p>
                        <strong class="kealoa-meta-label"><?php esc_html_e('Host', 'kealoa-reference'); ?></strong>
                        <span><?php
                        if ($clue_giver) {
                            echo Kealoa_Formatter::format_person_link((int) $clue_giver->id, $clue_giver->full_name, 'host');
                        } else {
                            esc_html_e('Unknown', 'kealoa-reference');
                        }
                        ?></span>
                    </p>
                    <p>
                        <strong class="kealoa-meta-label"><?php esc_html_e('Players', 'kealoa-reference'); ?></strong>
                        <span><?php
                        $sorted_results = $guesser_results;
                        usort($sorted_results, function($a, $b) {
                            return (int) $b->correct_guesses - (int) $a->correct_guesses;
                        });
                        $guesser_parts = [];
                        foreach ($sorted_results as $gr) {
                            $link = Kealoa_Formatter::format_person_link((int) $gr->person_id, $gr->full_name);
                            $correct = (int) $gr->correct_guesses;
                            $total = (int) $gr->total_guesses;
                            $score = sprintf('%d/%d', $correct, $total);
                            $guesser_parts[] = $link . ' (' . esc_html($score) . ')';
                        }
                        echo implode('; ', $guesser_parts);
                        ?></span>
                    </p>
                    <?php if (!empty($round->description)): ?>
                        <p>
                            <strong class="kealoa-meta-label"><?php esc_html_e('Description', 'kealoa-reference'); ?></strong>
                            <span><?php echo esc_html($round->description); ?></span>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($round->description2)): ?>
                        <p>
                            <strong class="kealoa-meta-label"></strong>
                            <span><?php echo esc_html($round->description2); ?></span>
                        </p>
                    <?php endif; ?>
                    <?php if (!empty($clues)): ?>
                    <?php
                    $alternation_pct = $this->db->get_round_alternation_pct($round_id);
                    ?>
                    <p>
                        <strong class="kealoa-meta-label"><?php esc_html_e('Answer Alternation', 'kealoa-reference'); ?></strong>
                        <span><?php
                        echo Kealoa_Formatter::format_percentage($alternation_pct);
                        $n_clues = count($clues);
                        $answer_changes = 0;
                        for ($i = 1; $i < $n_clues; $i++) {
                            if ($clues[$i]->correct_answer !== $clues[$i - 1]->correct_answer) {
                                $answer_changes++;
                            }
                        }
                        /* translators: %d = number of answer changes */
                        echo ' ' . sprintf(
                            esc_html(_n('(%d answer change)', '(%d answer changes)', $answer_changes, 'kealoa-reference')),
                            $answer_changes
                        );
                        ?></span>
                    </p>
                    <?php
                    // Per-player guess alternation — compute total guess changes
                    $guess_alt_per_player = $this->db->get_round_guess_alternation_per_player($round_id);
                    if (!empty($guess_alt_per_player)):
                        $guess_alt_sum = 0.0;
                        foreach ($guess_alt_per_player as $gap) {
                            $guess_alt_sum += $gap->alternation_pct;
                        }
                        $guess_alt_avg = $guess_alt_sum / count($guess_alt_per_player);

                        // Compute total guess changes across all players from raw guesses
                        $clue_ids_for_meta = array_map(fn($c) => (int) $c->id, $clues);
                        $meta_guesses_map = !empty($clue_ids_for_meta) ? $this->db->get_clue_guesses_bulk($clue_ids_for_meta) : [];
                        // Build per-player guess sequences ordered by clue_number
                        $guess_sequences = [];
                        foreach ($clues as $ci => $clue) {
                            $cid = (int) $clue->id;
                            $cg_list = $meta_guesses_map[$cid] ?? [];
                            foreach ($cg_list as $cg) {
                                $pid = (int) $cg->guesser_person_id;
                                $guess_sequences[$pid][] = $cg->guessed_word;
                            }
                        }
                        $total_guess_changes = 0;
                        foreach ($guess_sequences as $seq) {
                            for ($gi = 1, $gn = count($seq); $gi < $gn; $gi++) {
                                if ($seq[$gi] !== $seq[$gi - 1]) {
                                    $total_guess_changes++;
                                }
                            }
                        }
                    ?>
                    <p>
                        <strong class="kealoa-meta-label"><?php esc_html_e('Guess Alternation', 'kealoa-reference'); ?></strong>
                        <span><?php
                        // Per-player guess alternation detail
                        $first_alt = true;
                        foreach ($guess_alt_per_player as $gap):
                            $player_pid = (int) $gap->person_id;
                            $player_changes = 0;
                            $player_seq = $guess_sequences[$player_pid] ?? [];
                            for ($gi = 1, $gn = count($player_seq); $gi < $gn; $gi++) {
                                if ($player_seq[$gi] !== $player_seq[$gi - 1]) {
                                    $player_changes++;
                                }
                            }
                            if (!$first_alt) echo '<br>';
                            $first_alt = false;
                            echo esc_html($gap->full_name) . ': ';
                            echo Kealoa_Formatter::format_percentage($gap->alternation_pct);
                            echo ' ' . sprintf(
                                esc_html(_n('(%d guess change)', '(%d guess changes)', $player_changes, 'kealoa-reference')),
                                $player_changes
                            );
                        endforeach;
                        ?></span>
                    </p>
                    <?php endif; ?>
                    <?php
                    // Pielou's Evenness Index: J' = H' / ln(S), scaled to 0-100%
                    // S = number of solution words (from round_solutions), not just those used as clue answers
                    $s_count = count($solutions);
                    if ($s_count <= 1) {
                        $evenness_pct = 100.0;
                    } else {
                        // Include solution words with zero clues in the distribution
                        $full_counts = [];
                        foreach ($solutions as $s) {
                            $word = strtoupper($s->word);
                            $full_counts[$word] = $answer_counts[$word] ?? 0;
                        }
                        $n_total = array_sum($full_counts);
                        if ($n_total === 0) {
                            $evenness_pct = 100.0;
                        } else {
                            $h_prime = 0.0;
                            foreach ($full_counts as $count) {
                                $p_i = $count / $n_total;
                                if ($p_i > 0) {
                                    $h_prime -= $p_i * log($p_i);
                                }
                            }
                            $evenness_pct = ($h_prime / log($s_count)) * 100;
                        }
                    }
                    ?>
                    <p>
                        <strong class="kealoa-meta-label"><?php esc_html_e('Answer Evenness', 'kealoa-reference'); ?></strong>
                        <span><?php
                        echo Kealoa_Formatter::format_percentage($evenness_pct);
                        $word_parts = [];
                        foreach ($solutions as $s) {
                            $word = strtoupper($s->word);
                            $count = $answer_counts[$word] ?? 0;
                            $word_parts[] = esc_html($word) . ': ' . $count;
                        }
                        echo ' (' . implode(', ', $word_parts) . ')';
                        ?></span>
                    </p>
                    <?php
                    // Per-player guess evenness — compute aggregate guess word distribution
                    $guess_even_map = $this->db->get_round_guess_evenness_per_player_bulk([$round_id]);
                    $guess_even_per_player = $guess_even_map[$round_id] ?? [];
                    if (!empty($guess_even_per_player)):
                        $guess_even_sum = 0.0;
                        foreach ($guess_even_per_player as $gep) {
                            $guess_even_sum += $gep->evenness_pct;
                        }
                        $guess_even_avg = $guess_even_sum / count($guess_even_per_player);

                        // Build aggregate guess word distribution from raw guesses
                        if (!isset($meta_guesses_map)) {
                            $clue_ids_for_meta = array_map(fn($c) => (int) $c->id, $clues);
                            $meta_guesses_map = !empty($clue_ids_for_meta) ? $this->db->get_clue_guesses_bulk($clue_ids_for_meta) : [];
                        }
                        $guess_word_counts = [];
                        foreach ($solutions as $s) {
                            $guess_word_counts[strtoupper($s->word)] = 0;
                        }
                        foreach ($meta_guesses_map as $cg_list) {
                            foreach ($cg_list as $cg) {
                                $gw = strtoupper($cg->guessed_word);
                                $guess_word_counts[$gw] = ($guess_word_counts[$gw] ?? 0) + 1;
                            }
                        }
                    ?>
                    <p>
                        <strong class="kealoa-meta-label"><?php esc_html_e('Guess Evenness', 'kealoa-reference'); ?></strong>
                        <span><?php
                        // Per-player guess evenness detail
                        $guesser_name_map = [];
                        foreach ($guessers as $g) {
                            $guesser_name_map[(int) $g->id] = $g->full_name;
                        }
                        $per_player_guess_counts = [];
                        foreach ($meta_guesses_map as $cg_list) {
                            foreach ($cg_list as $cg) {
                                $ppid = (int) $cg->guesser_person_id;
                                $gw = strtoupper($cg->guessed_word);
                                if (!isset($per_player_guess_counts[$ppid])) {
                                    $per_player_guess_counts[$ppid] = [];
                                    foreach ($solutions as $s) {
                                        $per_player_guess_counts[$ppid][strtoupper($s->word)] = 0;
                                    }
                                }
                                $per_player_guess_counts[$ppid][$gw] = ($per_player_guess_counts[$ppid][$gw] ?? 0) + 1;
                            }
                        }
                        $first_even = true;
                        foreach ($guess_even_per_player as $gep):
                            $ppid = (int) $gep->person_id;
                            $pp_name = $guesser_name_map[$ppid] ?? '?';
                            $pp_counts = $per_player_guess_counts[$ppid] ?? [];
                            if (!$first_even) echo '<br>';
                            $first_even = false;
                            echo esc_html($pp_name) . ': ';
                            echo Kealoa_Formatter::format_percentage($gep->evenness_pct);
                            $pp_word_parts = [];
                            foreach ($solutions as $s) {
                                $ppword = strtoupper($s->word);
                                $ppcount = $pp_counts[$ppword] ?? 0;
                                $pp_word_parts[] = esc_html($ppword) . ': ' . $ppcount;
                            }
                            echo ' (' . implode(', ', $pp_word_parts) . ')';
                        endforeach;
                        ?></span>
                    </p>
                    <?php endif; ?>
                    <?php
                    $clue_age_stats = $this->db->get_round_clue_age_stats($round_id);
                    if ($clue_age_stats): ?>
                    <p>
                        <strong class="kealoa-meta-label"><?php esc_html_e('Avg Clue Age', 'kealoa-reference'); ?></strong>
                        <span><?php
                            $total_days = (int) round($clue_age_stats->mean);
                            $years  = intdiv($total_days, 365);
                            $remain = $total_days % 365;
                            $months = intdiv($remain, 30);
                            $days   = $remain % 30;
                            $parts  = [];
                            if ($years > 0) {
                                $parts[] = sprintf(_n('%d year', '%d years', $years, 'kealoa-reference'), $years);
                            }
                            if ($months > 0) {
                                $parts[] = sprintf(_n('%d month', '%d months', $months, 'kealoa-reference'), $months);
                            }
                            $parts[] = sprintf(_n('%d day', '%d days', $days, 'kealoa-reference'), $days);
                            echo esc_html(implode(', ', $parts));
                        ?></span>
                    </p>
                    <?php endif; ?>
                    <?php endif; /* !empty($clues) */ ?>
                </div>

                <?php
                // Collect all players: clue giver first, then guessers in same order as Players header
                $all_players = [];
                if ($clue_giver) {
                    $all_players[] = $clue_giver;
                }
                foreach ($sorted_results as $gr) {
                    // Avoid duplicating clue giver if they're also a guesser
                    if (!$clue_giver || (int) $gr->person_id !== (int) $clue_giver->id) {
                        // Find the full person object from $guessers
                        foreach ($guessers as $guesser) {
                            if ((int) $guesser->id === (int) $gr->person_id) {
                                $all_players[] = $guesser;
                                break;
                            }
                        }
                    }
                }
                ?>
                <?php if (!empty($all_players)): ?>
                <div class="kealoa-round-players">
                    <?php foreach ($all_players as $player): ?>
                        <?php
                        $player_media_id = (int) ($player->media_id ?? 0);
                        $player_img_url = '';
                        $player_img_source = '';
                        if ($player_media_id > 0) {
                            $player_src = wp_get_attachment_image_src($player_media_id, 'medium');
                            if ($player_src) {
                                $player_img_url = $player_src[0];
                                $player_img_source = 'media';
                            }
                        }
                        if (empty($player_img_source)) {
                            $player_img_url = $player->xwordinfo_image_url ?? '';
                            $player_img_source = 'xwordinfo';
                        }
                        ?>
                        <?php
                        $player_slug = str_replace(' ', '_', $player->full_name);
                        $player_tab = ($clue_giver && (int) $player->id === (int) $clue_giver->id) ? 'host' : 'player';
                        $player_url = home_url('/kealoa/person/' . urlencode($player_slug) . '/') . '#kealoa-tab=' . $player_tab;
                        ?>
                        <div class="kealoa-round-player">
                            <a href="<?php echo esc_url($player_url); ?>">
                                <?php if ($player_img_source === 'media'): ?>
                                    <img src="<?php echo esc_url($player_img_url); ?>"
                                         alt="<?php echo esc_attr($player->full_name); ?>"
                                         class="kealoa-player-image" />
                                <?php else: ?>
                                    <?php echo Kealoa_Formatter::format_xwordinfo_image($player_img_url, $player->full_name); ?>
                                <?php endif; ?>
                            </a>
                            <span class="kealoa-round-player-name"><?php echo esc_html($player->full_name); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($round->episode_id)): ?>
                <div class="kealoa-audio-player">
                    <iframe title="Libsyn Player" style="border: none" src="//html5-player.libsyn.com/embed/episode/id/<?php echo esc_attr($round->episode_id); ?>/height/90/theme/custom/thumbnail/yes/direction/forward/render-playlist/no/custom-color/000000/time-start/<?php echo esc_attr(Kealoa_Formatter::seconds_to_time((int) $round->episode_start_seconds)); ?>/" height="90" width="100%" scrolling="no" allowfullscreen webkitallowfullscreen mozallowfullscreen oallowfullscreen msallowfullscreen></iframe>
                </div>
            <?php endif; ?>

            <?php if (!empty($clues)): ?>
                <?php
                // Bulk pre-fetch puzzle refs, constructors, and guesses for all clues
                $clue_ids_list   = array_map(fn($c) => (int) $c->id, $clues);
                $bulk_clue_puzzles = $this->db->get_clue_puzzles_bulk($clue_ids_list);
                $all_puzzle_ids  = [];
                foreach ($bulk_clue_puzzles as $cps) {
                    foreach ($cps as $cp) {
                        if ($cp->puzzle_id !== null) {
                            $all_puzzle_ids[] = (int) $cp->puzzle_id;
                        }
                    }
                }
                $all_puzzle_ids = array_unique($all_puzzle_ids);
                $bulk_constructors_map = !empty($all_puzzle_ids) ? $this->db->get_puzzle_constructors_bulk($all_puzzle_ids) : [];
                $bulk_guesses_map      = !empty($clue_ids_list) ? $this->db->get_clue_guesses_bulk($clue_ids_list) : [];
                ?>
                <div class="kealoa-clues-section">
                    <h2><?php esc_html_e('Clues', 'kealoa-reference'); ?></h2>
                    <p class="kealoa-section-description"><?php esc_html_e('The crossword clues presented in this round with their answers and guess results.', 'kealoa-reference'); ?></p>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-clues-table">
                        <thead>
                            <tr>
                                <th data-sort="number"><?php esc_html_e('#', 'kealoa-reference'); ?></th>
                                <th data-sort="weekday" class="kealoa-day-cell"><?php esc_html_e('Day', 'kealoa-reference'); ?></th>
                                <th data-sort="date"><?php esc_html_e('Puzzle Date', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Constructors', 'kealoa-reference'); ?></th>
                                <th data-sort="text" class="kealoa-editor"><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
                                <th data-sort="clue"><?php esc_html_e('Clue #', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Clue Text', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Answer', 'kealoa-reference'); ?></th>
                                <?php foreach ($guessers as $guesser): ?>
                                    <th class="kealoa-guesser-col">
                                        <?php echo Kealoa_Formatter::format_person_link((int) $guesser->id, $guesser->full_name); ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clues as $clue): ?>
                                <?php
                                $clue_pzs = $bulk_clue_puzzles[(int) $clue->id] ?? [];
                                $clue_guesses = $bulk_guesses_map[(int) $clue->id] ?? [];
                                ?>
                                <tr>
                                    <td class="kealoa-clue-number"><?php echo esc_html($clue->clue_number); ?></td>
                                    <td class="kealoa-day-cell"><?php
                                        if (!empty($clue_pzs)) {
                                            echo implode('<br>', array_map(fn($cp) => $cp->puzzle_id ? esc_html(Kealoa_Formatter::format_day_abbrev($cp->puzzle_date)) : '—', $clue_pzs));
                                        } else {
                                            echo '—';
                                        }
                                    ?></td>
                                    <td class="kealoa-puzzle-date"><?php
                                        if (!empty($clue_pzs)) {
                                            echo implode('<br>', array_map(fn($cp) => $cp->puzzle_id ? Kealoa_Formatter::format_puzzle_date_link($cp->puzzle_date) : '—', $clue_pzs));
                                        } else {
                                            echo '—';
                                        }
                                    ?></td>
                                    <td class="kealoa-constructors"><?php
                                        if (!empty($clue_pzs)) {
                                            $con_lines = [];
                                            foreach ($clue_pzs as $cp) {
                                                if ($cp->puzzle_id) {
                                                    $cons = $bulk_constructors_map[(int) $cp->puzzle_id] ?? [];
                                                    $con_lines[] = Kealoa_Formatter::format_constructor_list($cons);
                                                } else {
                                                    $con_lines[] = '—';
                                                }
                                            }
                                            echo implode('<br>', $con_lines);
                                        } else {
                                            echo '—';
                                        }
                                    ?></td>
                                    <td class="kealoa-editor"><?php
                                        if (!empty($clue_pzs)) {
                                            $editor_lines = [];
                                            foreach ($clue_pzs as $cp) {
                                                if ($cp->puzzle_id && !empty($cp->editor_id)) {
                                                    $editor_lines[] = Kealoa_Formatter::format_editor_link((int) $cp->editor_id, $cp->editor_name);
                                                } else {
                                                    $editor_lines[] = '—';
                                                }
                                            }
                                            echo implode('<br>', $editor_lines);
                                        } else {
                                            echo '—';
                                        }
                                    ?></td>
                                    <td class="kealoa-clue-ref"><?php
                                        if (!empty($clue_pzs)) {
                                            echo implode('<br>', array_map(fn($cp) => $cp->puzzle_id ? esc_html(Kealoa_Formatter::format_clue_direction((int) $cp->puzzle_clue_number, $cp->puzzle_clue_direction)) : '—', $clue_pzs));
                                        } else {
                                            echo '—';
                                        }
                                    ?></td>
                                    <td class="kealoa-clue-text"><?php
                                        if (!empty($clue_pzs)) {
                                            echo implode('<br>', array_map(fn($cp) => esc_html($cp->clue_text ?? ''), $clue_pzs));
                                        } else {
                                            echo '—';
                                        }
                                    ?></td>
                                    <td class="kealoa-correct-answer">
                                        <strong><?php echo esc_html($clue->correct_answer); ?></strong>
                                    </td>
                                    <?php foreach ($guessers as $guesser): ?>
                                        <td class="kealoa-guess">
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
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php else: ?>
                <p class="kealoa-no-data"><?php esc_html_e('No clues recorded for this round.', 'kealoa-reference'); ?></p>
            <?php endif; ?>

            <?php
            $prev_round = $this->db->get_previous_round($round_id, $round);
            $next_round = $this->db->get_next_round($round_id, $round);
            ?>
            <?php if ($prev_round || $next_round): ?>
            <div class="kealoa-round-nav">
                <?php if ($prev_round): ?>
                    <a href="<?php echo esc_url(home_url('/kealoa/round/' . (int) $prev_round->game_number . '/')); ?>" class="kealoa-round-nav-btn kealoa-round-nav-prev">
                        &larr; <?php esc_html_e('Previous Round', 'kealoa-reference'); ?>
                    </a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <?php if ($next_round): ?>
                    <a href="<?php echo esc_url(home_url('/kealoa/round/' . (int) $next_round->game_number . '/')); ?>" class="kealoa-round-nav-btn kealoa-round-nav-next">
                        <?php esc_html_e('Next Round', 'kealoa-reference'); ?> &rarr;
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();

        }); // end get_cached_or_render

        // Append play-round links that navigate to the play-game page
        $play_page_url = kealoa_get_play_page_url();
        if ($play_page_url) {
            $show_url = add_query_arg(['round' => $round_id, 'order' => 'show'], $play_page_url);
            $random_url = add_query_arg(['round' => $round_id, 'order' => 'random'], $play_page_url);
            $html .= '<div class="kealoa-play-links">';
            $html .= '<h2 class="kealoa-play-links__title">' . esc_html__('Play KEALOA!', 'kealoa-reference') . '</h2>';
            $html .= '<div class="kealoa-play-links__buttons">';
            $html .= '<a href="' . esc_url($show_url) . '" class="kealoa-play-links__btn">' . esc_html__('In Show Order', 'kealoa-reference') . '</a>';
            $html .= '<a href="' . esc_url($random_url) . '" class="kealoa-play-links__btn">' . esc_html__('In Random Order', 'kealoa-reference') . '</a>';
            $html .= '</div></div>';
        }

        return $html;
    }

    /**
     * Render person view shortcode
     *
     * [kealoa_person id="X"]
     */
    public function render_person(array $atts = []): string {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts, 'kealoa_person');

        $person_id = (int) $atts['id'];
        if (!$person_id) {
            return '<p class="kealoa-error">' . esc_html__('Please specify a person ID.', 'kealoa-reference') . '</p>';
        }

        $person = $this->db->get_person($person_id);
        if (!$person) {
            return '<p class="kealoa-error">' . esc_html__('Person not found.', 'kealoa-reference') . '</p>';
        }

        // Resolve alias group — merged persons share data in the detail view
        $person_ids = $this->db->get_alias_person_ids($person_id);

        return $this->get_cached_or_render('person_' . $person_id . '_m' . implode('_', $person_ids), function () use ($person_id, $person_ids, $person) {

        $stats = $this->db->get_person_stats($person_ids);

        // Check roles via unified persons schema
        $roles = $this->db->get_person_roles($person_ids);
        $is_player = in_array('player', $roles, true);
        $is_constructor = in_array('constructor', $roles, true);
        $is_editor = in_array('editor', $roles, true);

        // Constructor/editor role data (loaded lazily)
        $constructor_stats = $is_constructor ? $this->db->get_person_constructor_stats($person_ids) : null;
        $constructor_puzzles = $is_constructor ? $this->db->get_person_constructor_puzzles($person_ids) : [];
        $constructor_player_results = $is_constructor ? $this->db->get_person_constructor_player_results($person_ids) : [];
        $constructor_editor_results = $is_constructor ? $this->db->get_person_constructor_editor_results($person_ids) : [];
        $editor_stats = $is_editor ? $this->db->get_person_editor_stats($person_ids) : null;
        $editor_puzzles = $is_editor ? $this->db->get_person_editor_puzzles($person_ids) : [];
        $editor_player_results = $is_editor ? $this->db->get_person_editor_player_results($person_ids) : [];
        $editor_constructor_results = $is_editor ? $this->db->get_person_editor_constructor_results($person_ids) : [];

        $is_clue_giver = in_array('clue_giver', $roles, true);
        $clue_giver_stats = $is_clue_giver ? $this->db->get_clue_giver_stats($person_ids) : null;
        $clue_giver_stats_by_year = $is_clue_giver ? $this->db->get_clue_giver_stats_by_year($person_ids) : [];
        $clue_giver_stats_by_day = $is_clue_giver ? $this->db->get_clue_giver_stats_by_day($person_ids) : [];
        $clue_giver_stats_by_guesser = $is_clue_giver ? $this->db->get_clue_giver_stats_by_guesser($person_ids) : [];
        $clue_giver_rounds = $is_clue_giver ? $this->db->get_clue_giver_rounds($person_ids) : [];
        $clue_giver_streaks = $is_clue_giver ? $this->db->get_clue_giver_streaks($person_ids) : null;
        $clue_giver_unique_players = $is_clue_giver ? $this->db->get_clue_giver_unique_players($person_ids) : 0;

        $player_streaks = $is_player ? $this->db->get_player_streaks($person_ids) : null;

        $person_puzzles = $is_player ? $this->db->get_person_puzzles($person_ids) : [];
        $clue_number_results = $this->db->get_person_results_by_clue_number($person_ids);
        $answer_length_results = $this->db->get_person_results_by_answer_length($person_ids);
        $answer_word_count_results = $this->db->get_person_results_by_answer_word_count($person_ids);
        $direction_results = $this->db->get_person_results_by_direction($person_ids);
        $day_of_week_results = $this->db->get_person_results_by_day_of_week($person_ids);
        $decade_results = $this->db->get_person_results_by_decade($person_ids);
        $year_results = $this->db->get_person_results_by_year($person_ids);
        $best_streaks_by_year = $this->db->get_person_best_streaks_by_year($person_ids);
        $constructor_results = $this->db->get_person_results_by_constructor($person_ids);
        $editor_results = $this->db->get_person_results_by_editor($person_ids);
        $round_history = $this->db->get_person_round_history($person_ids);
        $streak_per_round = $this->db->get_person_streak_per_round($person_ids);
        $correct_clue_rounds = $this->db->get_person_correct_clue_rounds($person_ids);

        // Collect ALL round IDs needed across all tabs for bulk pre-fetching
        $all_person_round_ids = array_map(function ($rh) { return (int) $rh->round_id; }, $round_history);
        $cg_round_ids = array_map(function ($cgr) { return (int) $cgr->round_id; }, $clue_giver_rounds);
        $puzzle_sources = [$person_puzzles, $constructor_puzzles, $editor_puzzles];
        $puzzle_round_ids = [];
        foreach ($puzzle_sources as $pz_list) {
            foreach ($pz_list as $pz) {
                if (!empty($pz->round_ids)) {
                    foreach (explode(',', $pz->round_ids) as $rid) {
                        $puzzle_round_ids[] = (int) $rid;
                    }
                }
            }
        }
        $every_round_id = array_values(array_unique(array_merge($all_person_round_ids, $cg_round_ids, $puzzle_round_ids)));

        // Bulk pre-fetch: solutions, rounds-per-date, alternation percentages
        $bulk_solutions_map = $this->db->get_round_solutions_bulk($every_round_id);
        $rounds_per_date_map = $this->db->get_rounds_per_date_counts();
        $bulk_alternation_map = $this->db->get_round_alternation_pcts_bulk($every_round_id);
        $bulk_guess_alt_map = $this->db->get_round_guess_alternation_per_player_bulk($all_person_round_ids);
        // Bulk pre-fetch guessers for round history co-players
        $bulk_guessers_map = $this->db->get_round_guessers_bulk($all_person_round_ids);

        // Pre-compute results by KEALOA type (True/Near/Free)
        $kealoa_type_stats = [];
        foreach ($round_history as $rh) {
            $rh_rid = (int) $rh->round_id;
            $rh_solutions = $bulk_solutions_map[$rh_rid] ?? [];
            $rh_type = Kealoa_Formatter::classify_kealoa_type($rh_solutions);
            if ($rh_type === '') {
                continue;
            }
            if (!isset($kealoa_type_stats[$rh_type])) {
                $kealoa_type_stats[$rh_type] = ['rounds' => 0, 'guesses' => 0, 'correct' => 0];
            }
            $kealoa_type_stats[$rh_type]['rounds']++;
            $kealoa_type_stats[$rh_type]['guesses'] += (int) $rh->total_clues;
            $kealoa_type_stats[$rh_type]['correct'] += (int) $rh->correct_count;
        }

        // Bulk pre-fetch guess stats per puzzle for constructor/editor tables
        $all_puzzle_ids = [];
        foreach ($puzzle_sources as $pz_list) {
            foreach ($pz_list as $pz) {
                $all_puzzle_ids[] = (int) $pz->puzzle_id;
            }
        }
        $all_puzzle_ids = array_values(array_unique($all_puzzle_ids));
        $bulk_puzzle_stats_map = !empty($all_puzzle_ids) ? $this->db->get_puzzle_guess_stats_bulk($all_puzzle_ids) : [];

        // Bulk pre-fetch co-constructors for constructor puzzles
        $con_puzzle_ids = array_map(function ($p) { return (int) $p->puzzle_id; }, $constructor_puzzles);
        $bulk_co_constructors_map = !empty($con_puzzle_ids)
            ? $this->db->get_puzzle_co_constructors_bulk($con_puzzle_ids, $person_ids)
            : [];
        $has_co_constructors = false;
        foreach ($bulk_co_constructors_map as $co_list) {
            if (!empty($co_list)) {
                $has_co_constructors = true;
                break;
            }
        }

        // Build clue giver round info lookup for round picker links
        $cg_round_info = [];
        foreach ($clue_giver_rounds as $cgr) {
            $cgr_id = (int) $cgr->round_id;
            $cgr_solutions = $bulk_solutions_map[$cgr_id] ?? [];
            $cgr_pct = (int) $cgr->total_guesses > 0
                ? ((int) $cgr->correct_guesses / (int) $cgr->total_guesses) * 100
                : 0;
            $cg_round_info[$cgr_id] = [
                'url'   => home_url('/kealoa/round/' . (int) $cgr->game_number . '/'),
                'date'  => Kealoa_Formatter::format_date($cgr->round_date),
                'words' => Kealoa_Formatter::format_solution_words($cgr_solutions),
                'score' => round($cgr_pct, 1) . '%',
            ];
        }

        // Helper: build JSON for clue giver round picker
        $build_cg_picker_json = function(array $round_ids) use ($cg_round_info): string {
            $rounds = [];
            foreach ($round_ids as $rid) {
                if (isset($cg_round_info[$rid])) {
                    $rounds[] = $cg_round_info[$rid];
                }
            }
            return esc_attr(wp_json_encode($rounds));
        };

        // Build round info lookup: round_id => {url, date, words, score}
        $round_info = [];
        foreach ($round_history as $rh) {
            $rid = (int) $rh->round_id;
            $rh_solutions = $bulk_solutions_map[$rid] ?? [];
            $round_info[$rid] = [
                'url' => home_url('/kealoa/round/' . (int) $rh->game_number . '/'),
                'date' => Kealoa_Formatter::format_date($rh->round_date),
                'words' => Kealoa_Formatter::format_solution_words($rh_solutions),
                'score' => (int) $rh->correct_count . '/' . (int) $rh->total_clues,
                'year' => (int) date('Y', strtotime($rh->round_date)),
                'correct_count' => (int) $rh->correct_count,
                'total_clues' => (int) $rh->total_clues,
            ];
        }

        // Helper: build JSON for round picker from array of round_ids
        $build_picker_json = function(array $round_ids) use ($round_info): string {
            $rounds = [];
            foreach ($round_ids as $rid) {
                if (isset($round_info[$rid])) {
                    $ri = $round_info[$rid];
                    $rounds[] = [
                        'url' => $ri['url'],
                        'date' => $ri['date'],
                        'words' => $ri['words'],
                        'score' => $ri['score'],
                    ];
                }
            }
            return esc_attr(wp_json_encode($rounds));
        };

        // Pre-compute: rounds grouped by score
        $rounds_by_score = [];
        foreach ($round_info as $rid => $ri) {
            $rounds_by_score[$ri['correct_count']][] = $rid;
        }

        // Pre-compute: rounds grouped by year
        $round_ids_by_year = [];
        foreach ($round_info as $rid => $ri) {
            $round_ids_by_year[$ri['year']][] = $rid;
        }

        // Pre-compute: rounds with best score overall
        $best_score_round_ids = $rounds_by_score[$stats->max_correct] ?? [];

        // Pre-compute: rounds with best streak overall (cross-round streak, same as badge)
        $best_streak_round_ids = [];
        if ($player_streaks && !empty($player_streaks->streaks)) {
            foreach ($player_streaks->streaks as $streak) {
                if ($streak->type === 'correct' && $streak->length === $player_streaks->best_correct_streak) {
                    $best_streak_round_ids = array_merge($best_streak_round_ids, $streak->round_ids);
                }
            }
            $best_streak_round_ids = array_unique($best_streak_round_ids);
        }

        // Pre-compute: rounds with best score per year
        $best_score_rounds_by_year = [];
        foreach ($year_results as $yr) {
            $year = (int) $yr->year;
            $best = (int) $yr->best_score;
            $best_score_rounds_by_year[$year] = [];
            foreach ($round_ids_by_year[$year] ?? [] as $rid) {
                if ($round_info[$rid]['correct_count'] === $best) {
                    $best_score_rounds_by_year[$year][] = $rid;
                }
            }
        }

        // Pre-compute: rounds with best streak per year
        $best_streak_rounds_by_year = [];
        foreach ($best_streaks_by_year as $year => $data) {
            $best_streak_rounds_by_year[$year] = $data['round_ids'];
        }

        // Compute achievement badges
        $badge_metrics = [];
        if ($is_clue_giver && $clue_giver_stats) {
            $badge_metrics['host_rounds']  = (int) $clue_giver_stats->round_count;
            $badge_metrics['host_players'] = $clue_giver_unique_players;
            if ($clue_giver_streaks) {
                $badge_metrics['host_streak'] = (int) $clue_giver_streaks->best_correct_streak;
            }
            $badge_metrics['host_accuracy'] = (int) $clue_giver_stats->total_guesses > 0
                ? round((int) $clue_giver_stats->correct_guesses / (int) $clue_giver_stats->total_guesses * 100, 1)
                : 0.0;
        }
        if ($is_player && $stats) {
            $badge_metrics['player_rounds']   = (int) $stats->rounds_played;
            $badge_metrics['player_accuracy'] = (float) $stats->overall_percentage;
            $badge_metrics['player_correct']  = (int) $stats->max_correct;
            $badge_metrics['player_streak']   = $player_streaks ? (int) $player_streaks->best_correct_streak : 0;
        }
        if ($is_constructor && $constructor_stats) {
            $badge_metrics['constructor_puzzles']  = (int) $constructor_stats->puzzle_count;
            $badge_metrics['constructor_clues']    = (int) $constructor_stats->clue_count;
            $badge_metrics['constructor_accuracy'] = (int) $constructor_stats->total_guesses > 0
                ? round((int) $constructor_stats->correct_guesses / (int) $constructor_stats->total_guesses * 100, 1)
                : 0.0;
        }
        if ($is_editor && $editor_stats) {
            $badge_metrics['editor_puzzles']  = (int) $editor_stats->puzzle_count;
            $badge_metrics['editor_accuracy'] = (int) $editor_stats->total_guesses > 0
                ? round((int) $editor_stats->correct_guesses / (int) $editor_stats->total_guesses * 100, 1)
                : 0.0;
        }
        if ($is_constructor) {
            $con_cycle_count = $this->db->get_person_constructor_cycle_count($person_ids);
            if ($con_cycle_count > 0) {
                $badge_metrics['constructor_cycle'] = $con_cycle_count;
            }
        }
        if ($is_editor) {
            $ed_cycle_count = $this->db->get_person_editor_cycle_count($person_ids);
            if ($ed_cycle_count > 0) {
                $badge_metrics['editor_cycle'] = $ed_cycle_count;
            }
        }
        // Add player accuracy badge for non-player roles that also have player stats
        if (!$is_player && $stats && !empty($stats->overall_percentage)) {
            $badge_metrics['player_accuracy'] = (float) $stats->overall_percentage;
        }
        $person_badges = Kealoa_Badges::compute_badges($badge_metrics);

        // Determine image to display: media library > XWordInfo constructor image (with nophoto fallback)
        $person_media_id = (int) ($person->media_id ?? 0);
        $person_media_url = '';
        $person_image_source = ''; // 'media' or 'xwordinfo'

        if ($person_media_id > 0) {
            $media_src = wp_get_attachment_image_src($person_media_id, 'medium');
            if ($media_src) {
                $person_media_url = $media_src[0];
                $person_image_source = 'media';
            }
        }

        if (empty($person_image_source)) {
            $person_media_url = $person->xwordinfo_image_url ?? '';
            $person_image_source = 'xwordinfo';
        }

        ob_start();
        $default_tab = $is_player ? 'player' : ($is_clue_giver ? 'host' : ($is_constructor ? 'constructor' : 'editor'));
        $tab_active = function(string $tab) use ($default_tab): string {
            return $tab === $default_tab ? ' active' : '';
        };
        ?>
        <div class="kealoa-person-view">
            <div class="kealoa-person-header">
                <div class="kealoa-person-info">
                    <?php if ($person_image_source !== ''): ?>
                        <div class="kealoa-person-image">
                            <?php if ($person_image_source === 'media'): ?>
                                <img src="<?php echo esc_url($person_media_url); ?>"
                                     alt="<?php echo esc_attr($person->full_name); ?>"
                                     class="kealoa-entity-image" />
                            <?php else: ?>
                                <?php echo Kealoa_Formatter::format_xwordinfo_image($person_media_url, $person->full_name); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="kealoa-person-details">
                        <h2 class="kealoa-person-name"><?php echo esc_html($person->full_name); ?></h2>

                        <?php if (!empty($person->nicknames)): ?>
                            <p class="kealoa-person-nicknames"><?php echo esc_html($person->nicknames); ?></p>
                        <?php endif; ?>

                        <?php if (empty($person->hide_xwordinfo)): ?>
                        <p class="kealoa-person-xwordinfo">
                            <?php echo Kealoa_Formatter::format_xwordinfo_link($person->full_name, 'XWordInfo Profile'); ?>
                        </p>
                        <?php endif; ?>

                        <?php if (!empty($person->home_page_url)): ?>
                            <p class="kealoa-person-homepage">
                                <?php echo Kealoa_Formatter::format_home_page_link($person->home_page_url); ?>
                            </p>
                        <?php endif; ?>

                        <?php echo Kealoa_Badges::render_badges($person_badges); ?>
                    </div>
                </div>
            </div>

            <div class="kealoa-tabs" data-role="<?php echo esc_attr($default_tab); ?>">
                <div class="kealoa-tab-nav">
                    <?php if ($is_player): ?>
                    <button class="kealoa-tab-button kealoa-tab-button--player<?php echo $tab_active('player'); ?>" data-tab="player"><?php esc_html_e('Player', 'kealoa-reference'); ?></button>
                    <?php endif; ?>
                    <?php if ($is_clue_giver): ?>
                    <button class="kealoa-tab-button kealoa-tab-button--host<?php echo $tab_active('host'); ?>" data-tab="host"><?php esc_html_e('Host', 'kealoa-reference'); ?></button>
                    <?php endif; ?>
                    <?php if ($is_constructor): ?>
                    <button class="kealoa-tab-button kealoa-tab-button--constructor<?php echo $tab_active('constructor'); ?>" data-tab="constructor"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></button>
                    <?php endif; ?>
                    <?php if ($is_editor): ?>
                    <button class="kealoa-tab-button kealoa-tab-button--editor<?php echo $tab_active('editor'); ?>" data-tab="editor"><?php esc_html_e('Editor', 'kealoa-reference'); ?></button>
                    <?php endif; ?>
                </div>

                <?php if ($is_player): ?>
                <div class="kealoa-tab-panel<?php echo $tab_active('player'); ?>" data-tab="player">

                <div class="kealoa-tabs">
                    <div class="kealoa-tab-nav">
                        <button class="kealoa-tab-button active" data-tab="player-stats"><?php esc_html_e('Overall Stats', 'kealoa-reference'); ?></button>
                        <button class="kealoa-tab-button" data-tab="round"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></button>
                        <button class="kealoa-tab-button" data-tab="player-streaks"><?php esc_html_e('Streaks', 'kealoa-reference'); ?></button>
                        <button class="kealoa-tab-button" data-tab="puzzles"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></button>
                        <button class="kealoa-tab-button" data-tab="puzzle"><?php esc_html_e('Puzzle Stats', 'kealoa-reference'); ?></button>
                        <button class="kealoa-tab-button" data-tab="by-constructor"><?php esc_html_e('By Constructor', 'kealoa-reference'); ?></button>
                        <button class="kealoa-tab-button" data-tab="by-editor"><?php esc_html_e('By Editor', 'kealoa-reference'); ?></button>
                    </div>

                <div class="kealoa-tab-panel active" data-tab="player-stats">

            <div class="kealoa-person-stats">
                <h2><?php esc_html_e('KEALOA Statistics', 'kealoa-reference'); ?></h2>
                <p class="kealoa-section-description"><?php esc_html_e('Summary of this player\'s overall KEALOA performance.', 'kealoa-reference'); ?></p>

                <div class="kealoa-stats-grid">
                    <div class="kealoa-stat-card">
                        <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $stats->rounds_played)); ?></span>
                        <span class="kealoa-stat-label"><?php esc_html_e('Rounds Played', 'kealoa-reference'); ?></span>
                    </div>
                    <div class="kealoa-stat-card">
                        <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $stats->total_clues_answered)); ?></span>
                        <span class="kealoa-stat-label"><?php esc_html_e('Clues Answered', 'kealoa-reference'); ?></span>
                    </div>
                    <div class="kealoa-stat-card">
                        <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $stats->total_correct)); ?></span>
                        <span class="kealoa-stat-label"><?php esc_html_e('Correct Answers', 'kealoa-reference'); ?></span>
                    </div>
                    <div class="kealoa-stat-card">
                        <span class="kealoa-stat-value"><?php echo Kealoa_Formatter::format_percentage($stats->overall_percentage); ?></span>
                        <span class="kealoa-stat-label"><?php esc_html_e('Overall Accuracy', 'kealoa-reference'); ?></span>
                    </div>
                    <div class="kealoa-stat-card">
                        <span class="kealoa-stat-value"><a class="kealoa-round-picker-link" data-rounds="<?php echo $build_picker_json($best_score_round_ids); ?>"><?php echo esc_html($stats->max_correct); ?></a></span>
                        <span class="kealoa-stat-label"><?php esc_html_e('Best Score', 'kealoa-reference'); ?></span>
                    </div>
                    <div class="kealoa-stat-card">
                        <span class="kealoa-stat-value"><a class="kealoa-round-picker-link" data-rounds="<?php echo $build_picker_json($best_streak_round_ids); ?>"><?php echo esc_html($player_streaks ? $player_streaks->best_correct_streak : 0); ?></a></span>
                        <span class="kealoa-stat-label"><?php esc_html_e('Best Streak', 'kealoa-reference'); ?></span>
                    </div>
                </div>

            <?php if (!empty($round_history)): ?>
                <div class="kealoa-score-distribution-section">
                    <h2><?php esc_html_e('Accuracy Distribution', 'kealoa-reference'); ?></h2>
                    <p class="kealoa-section-description"><?php esc_html_e('Distribution of rounds by accuracy percentage.', 'kealoa-reference'); ?></p>
                    <div class="kealoa-chart-container">
                        <canvas id="kealoa-score-distribution-chart"></canvas>
                    </div>
                    <?php
                    // Build score distribution: count rounds per accuracy bucket (0%, 10%, ..., 100%)
                    $score_counts = array_fill(0, 11, 0);
                    foreach ($round_history as $rh) {
                        $total = (int) $rh->total_clues;
                        $correct = (int) $rh->correct_count;
                        $pct = $total > 0 ? ($correct / $total) * 100 : 0;
                        $bucket = min(10, (int) floor($pct / 10));
                        // 100% goes into the 100% bucket (index 10)
                        if ($pct >= 100) {
                            $bucket = 10;
                        }
                        $score_counts[$bucket]++;
                    }
                    // Labels from 100% down to 0% (Y-axis top to bottom)
                    $score_labels = [];
                    for ($i = 100; $i >= 0; $i -= 10) {
                        $score_labels[] = $i . '%';
                    }
                    $score_data = array_reverse(array_values($score_counts));

                    // Build round picker data per bucket (100%→0% order matching labels)
                    // Group round IDs by accuracy bucket
                    $rounds_by_bucket = array_fill(0, 11, []);
                    foreach ($round_history as $rh) {
                        $rid = (int) $rh->round_id;
                        $total = (int) $rh->total_clues;
                        $correct = (int) $rh->correct_count;
                        $pct = $total > 0 ? ($correct / $total) * 100 : 0;
                        $bucket = min(10, (int) floor($pct / 10));
                        if ($pct >= 100) {
                            $bucket = 10;
                        }
                        $rounds_by_bucket[$bucket][] = $rid;
                    }
                    $score_rounds_json = [];
                    for ($b = 10; $b >= 0; $b--) {
                        $rids = $rounds_by_bucket[$b] ?? [];
                        $rounds_for_bucket = [];
                        foreach ($rids as $rid) {
                            if (isset($round_info[$rid])) {
                                $ri = $round_info[$rid];
                                $rounds_for_bucket[] = [
                                    'url' => $ri['url'],
                                    'date' => $ri['date'],
                                    'words' => $ri['words'],
                                    'score' => $ri['score'],
                                ];
                            }
                        }
                        $score_rounds_json[] = $rounds_for_bucket;
                    }
                    ?>
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var ctx = document.getElementById('kealoa-score-distribution-chart');
                        if (ctx && typeof Chart !== 'undefined') {
                            var scoreRounds = <?php echo wp_json_encode($score_rounds_json); ?>;
                            var chart = new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: <?php echo wp_json_encode($score_labels); ?>,
                                    datasets: [{
                                        label: <?php echo wp_json_encode(__('Rounds', 'kealoa-reference')); ?>,
                                        data: <?php echo wp_json_encode($score_data); ?>,
                                        backgroundColor: [
                                            '#1B5E20',
                                            '#2E8E2E',
                                            '#5AA32A',
                                            '#8AAE2A',
                                            '#C0B02A',
                                            '#F9A825',
                                            '#F57C00',
                                            '#E64A19',
                                            '#D32F2F',
                                            '#C62828',
                                            '#B71C1C'
                                        ],
                                        borderColor: [
                                            '#1B5E20',
                                            '#2E8E2E',
                                            '#5AA32A',
                                            '#8AAE2A',
                                            '#C0B02A',
                                            '#F9A825',
                                            '#F57C00',
                                            '#E64A19',
                                            '#D32F2F',
                                            '#C62828',
                                            '#B71C1C'
                                        ],
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    indexAxis: 'y',
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        x: {
                                            title: {
                                                display: true,
                                                text: <?php echo wp_json_encode(__('Rounds', 'kealoa-reference')); ?>
                                            },
                                            beginAtZero: true,
                                            ticks: {
                                                stepSize: 1,
                                                precision: 0,
                                                autoSkip: false
                                            }
                                        },
                                        y: {
                                            title: {
                                                display: true,
                                                text: <?php echo wp_json_encode(__('Accuracy', 'kealoa-reference')); ?>
                                            },
                                            ticks: {
                                                autoSkip: false
                                            }
                                        }
                                    },
                                    plugins: {
                                        legend: {
                                            display: false
                                        }
                                    }
                                },
                                onClick: function(evt) {
                                    var points = chart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, false);
                                    if (points.length > 0) {
                                        var idx = points[0].index;
                                        if (scoreRounds[idx] && scoreRounds[idx].length > 0) {
                                            window.kealoaOpenRoundPicker(scoreRounds[idx]);
                                        }
                                    }
                                },
                                onHover: function(evt, elements) {
                                    ctx.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                                }
                            });
                        }
                    });
                    </script>
                </div>
            <?php endif; ?>

            <?php if (!empty($round_history)): ?>
                <div class="kealoa-accuracy-chart-section">
                    <h2><?php esc_html_e('Accuracy by Round', 'kealoa-reference'); ?></h2>
                    <p class="kealoa-section-description"><?php esc_html_e('Accuracy trend across all rounds played over time.', 'kealoa-reference'); ?></p>
                    <div class="kealoa-chart-container">
                        <canvas id="kealoa-accuracy-chart"></canvas>
                    </div>
                    <?php
                    // Build chart data in chronological order (round_history is DESC)
                    $chart_history = array_reverse($round_history);
                    $chart_labels = [];
                    $chart_data = [];
                    $chart_words = [];
                    $chart_urls = [];
                    foreach ($chart_history as $ch) {
                        $rid = (int) $ch->round_id;
                        $ri = $round_info[$rid] ?? null;
                        $chart_labels[] = Kealoa_Formatter::format_date($ch->round_date);
                        $total = (int) $ch->total_clues;
                        $correct = (int) $ch->correct_count;
                        $chart_data[] = $total > 0 ? round(($correct / $total) * 100, 1) : 0;
                        $chart_words[] = $ri ? $ri['words'] : '';
                        $chart_urls[] = $ri ? $ri['url'] : home_url('/kealoa/round/' . (int) $ch->game_number . '/');
                    }
                    ?>
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var ctx = document.getElementById('kealoa-accuracy-chart');
                        if (ctx && typeof Chart !== 'undefined') {
                            var chartWords = <?php echo wp_json_encode($chart_words); ?>;
                            var chartUrls = <?php echo wp_json_encode($chart_urls); ?>;
                            var chart = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: <?php echo wp_json_encode($chart_labels); ?>,
                                    datasets: [{
                                        label: <?php echo wp_json_encode(__('Correct', 'kealoa-reference')); ?>,
                                        data: <?php echo wp_json_encode($chart_data); ?>,
                                        borderColor: '#2271b1',
                                        backgroundColor: 'rgba(34, 113, 177, 0.1)',
                                        fill: true,
                                        tension: 0.3,
                                        pointRadius: 3,
                                        pointHoverRadius: 6
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        x: {
                                            title: {
                                                display: true,
                                                text: <?php echo wp_json_encode(__('Round Date', 'kealoa-reference')); ?>
                                            }
                                        },
                                        y: {
                                            title: {
                                                display: true,
                                                text: <?php echo wp_json_encode(__('Accuracy', 'kealoa-reference')); ?>
                                            },
                                            min: 0,
                                            max: 100,
                                            ticks: {
                                                stepSize: 10,
                                                precision: 0,
                                                autoSkip: false,
                                                callback: function(value) { return value + '%'; }
                                            }
                                        }
                                    },
                                    plugins: {
                                        legend: {
                                            display: false
                                        },
                                        tooltip: {
                                            callbacks: {
                                                title: function(items) {
                                                    if (items.length > 0) {
                                                        var idx = items[0].dataIndex;
                                                        return chartWords[idx] || items[0].label;
                                                    }
                                                    return '';
                                                },
                                                label: function(item) {
                                                    return item.parsed.y + '% accuracy';
                                                }
                                            }
                                        }
                                    }
                                },
                                onClick: function(evt) {
                                    var points = chart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, false);
                                    if (points.length > 0) {
                                        var idx = points[0].index;
                                        if (chartUrls[idx]) {
                                            window.location.href = chartUrls[idx];
                                        }
                                    }
                                },
                                onHover: function(evt, elements) {
                                    ctx.style.cursor = elements.length > 0 ? 'pointer' : 'default';
                                }
                            });
                        }
                    });
                    </script>
                </div>
            <?php endif; ?>

            <?php if (!empty($year_results)): ?>
                <div class="kealoa-year-stats">
                    <h2><?php esc_html_e('Results by Year of Round', 'kealoa-reference'); ?></h2>
                    <p class="kealoa-section-description"><?php esc_html_e('Year-by-year breakdown of this player\'s results.', 'kealoa-reference'); ?></p>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-year-table">
                        <thead>
                            <tr>
                                <th data-sort="number"><?php esc_html_e('Year', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Best Score', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Best Streak', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($year_results as $result): ?>
                                <?php
                                $yr = (int) $result->year;
                                $yr_round_ids = $round_ids_by_year[$yr] ?? [];
                                $yr_best_score_ids = $best_score_rounds_by_year[$yr] ?? [];
                                $yr_best_streak_ids = $best_streak_rounds_by_year[$yr] ?? [];
                                $yr_best_streak_val = $best_streaks_by_year[$yr]['streak'] ?? 0;
                                ?>
                                <tr>
                                    <td><a class="kealoa-year-tab-link" data-year="<?php echo esc_attr($yr); ?>" data-tab-target="round" data-filter-target="kealoa-rh-year"><?php echo esc_html($yr); ?></a></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->rounds_played)); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->total_answered)); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->correct_count)); ?></td>
                                    <?php
                                    $pct = $result->total_answered > 0
                                        ? ($result->correct_count / $result->total_answered) * 100
                                        : 0;
                                    ?>
                                    <td data-value="<?php echo esc_attr(number_format((float) $pct, 2, '.', '')); ?>">
                                        <?php echo Kealoa_Formatter::format_percentage($pct); ?>
                                    </td>
                                    <td><a class="kealoa-round-picker-link" data-rounds="<?php echo $build_picker_json($yr_best_score_ids); ?>"><?php echo esc_html((int) $result->best_score); ?></a></td>
                                    <td><a class="kealoa-round-picker-link" data-rounds="<?php echo $build_picker_json($yr_best_streak_ids); ?>"><?php echo esc_html($yr_best_streak_val); ?></a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($clue_number_results)): ?>
                <div class="kealoa-clue-number-stats">
                    <h2><?php esc_html_e('Results by Clue Number', 'kealoa-reference'); ?></h2>
                    <p class="kealoa-section-description"><?php esc_html_e('How this player performed at each clue position in the round.', 'kealoa-reference'); ?></p>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-clue-number-table">
                        <thead>
                            <tr>
                                <th data-sort="number"><?php esc_html_e('Clue #', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clue_number_results as $result): ?>
                                <?php $cn = (int) $result->clue_number; ?>
                                <tr>
                                    <td><?php echo esc_html($cn); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->total_answered)); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->correct_count)); ?></td>
                                    <?php
                                    $pct = $result->total_answered > 0
                                        ? ($result->correct_count / $result->total_answered) * 100
                                        : 0;
                                    ?>
                                    <td data-value="<?php echo esc_attr(number_format((float) $pct, 2, '.', '')); ?>">
                                        <?php echo Kealoa_Formatter::format_percentage($pct); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($answer_length_results)): ?>
                <div class="kealoa-answer-length-stats">
                    <h2><?php esc_html_e('Results by Answer Length', 'kealoa-reference'); ?></h2>
                    <p class="kealoa-section-description"><?php esc_html_e('How this player performed based on the number of letters in the correct answer.', 'kealoa-reference'); ?></p>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-answer-length-table">
                        <thead>
                            <tr>
                                <th data-sort="number"><?php esc_html_e('Length', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($answer_length_results as $result): ?>
                                <tr>
                                    <td><?php echo esc_html($result->answer_length); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->total_answered)); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->correct_count)); ?></td>
                                    <?php
                                    $pct = $result->total_answered > 0
                                        ? ($result->correct_count / $result->total_answered) * 100
                                        : 0;
                                    ?>
                                    <td data-value="<?php echo esc_attr(number_format((float) $pct, 2, '.', '')); ?>">
                                        <?php echo Kealoa_Formatter::format_percentage($pct); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($round_history)): ?>
                <?php
                // Build Alternation vs Accuracy table for this player
                $player_alternation_buckets = [];
                foreach ($round_history as $rh) {
                    $rid = (int) $rh->round_id;
                    $alternation = $bulk_alternation_map[$rid] ?? 0.0;
                    $bucket = (int) floor($alternation / 10) * 10;
                    if ($bucket >= 100) {
                        $bucket = 90; // merge 100% into 90-100 bucket
                    }
                    if (!isset($player_alternation_buckets[$bucket])) {
                        $player_alternation_buckets[$bucket] = ['rounds' => 0, 'guesses' => 0, 'correct' => 0];
                    }
                    $player_alternation_buckets[$bucket]['rounds']++;
                    $player_alternation_buckets[$bucket]['guesses'] += (int) $rh->total_clues;
                    $player_alternation_buckets[$bucket]['correct'] += (int) $rh->correct_count;
                }
                ksort($player_alternation_buckets);
                ?>
                <?php if (!empty($player_alternation_buckets)): ?>
                <div class="kealoa-alternation-accuracy-section">
                    <h2><?php esc_html_e('Answer Alternation', 'kealoa-reference'); ?> <?php esc_html_e('vs Accuracy', 'kealoa-reference'); ?></h2>
                    <p class="kealoa-section-description"><?php esc_html_e('How this player\'s accuracy varied based on how evenly the answers were distributed.', 'kealoa-reference'); ?></p>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-alternation-table">
                        <thead>
                            <tr>
                                <th data-sort="text"><?php esc_html_e('Answer Alternation', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($player_alternation_buckets as $bucket => $mbstats): ?>
                                <?php
                                $bucket_label = $bucket . '%–' . ($bucket + 10) . '%';
                                $bucket_acc = $mbstats['guesses'] > 0
                                    ? ($mbstats['correct'] / $mbstats['guesses']) * 100
                                    : 0;
                                ?>
                                <tr>
                                    <td><?php echo esc_html($bucket_label); ?></td>
                                    <td><?php echo esc_html(number_format_i18n($mbstats['rounds'])); ?></td>
                                    <td><?php echo esc_html(number_format_i18n($mbstats['guesses'])); ?></td>
                                    <td><?php echo esc_html(number_format_i18n($mbstats['correct'])); ?></td>
                                    <td data-value="<?php echo esc_attr(number_format((float) $bucket_acc, 2, '.', '')); ?>">
                                        <?php echo Kealoa_Formatter::format_percentage($bucket_acc); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
                <?php endif; ?>

                <?php
                // Build Guess Alternation vs Accuracy table for this player
                $player_guess_alt_buckets = [];
                foreach ($round_history as $rh) {
                    $rid = (int) $rh->round_id;
                    $player_alts = $bulk_guess_alt_map[$rid] ?? [];
                    // Find this player's guess alternation for the round
                    $my_alt = null;
                    foreach ($player_alts as $pa) {
                        if (in_array((int) $pa->person_id, $person_ids, true)) {
                            $my_alt = $pa->alternation_pct;
                            break;
                        }
                    }
                    if ($my_alt === null) {
                        continue;
                    }
                    $bucket = (int) floor($my_alt / 10) * 10;
                    if ($bucket >= 100) {
                        $bucket = 90;
                    }
                    if (!isset($player_guess_alt_buckets[$bucket])) {
                        $player_guess_alt_buckets[$bucket] = ['rounds' => 0, 'guesses' => 0, 'correct' => 0];
                    }
                    $player_guess_alt_buckets[$bucket]['rounds']++;
                    $player_guess_alt_buckets[$bucket]['guesses'] += (int) $rh->total_clues;
                    $player_guess_alt_buckets[$bucket]['correct'] += (int) $rh->correct_count;
                }
                ksort($player_guess_alt_buckets);
                ?>
                <?php if (!empty($player_guess_alt_buckets)): ?>
                <div class="kealoa-alternation-accuracy-section">
                    <h2><?php esc_html_e('Guess Alternation', 'kealoa-reference'); ?> <?php esc_html_e('vs Accuracy', 'kealoa-reference'); ?></h2>
                    <p class="kealoa-section-description"><?php esc_html_e('How this player\'s accuracy varied based on how evenly their guesses were spread.', 'kealoa-reference'); ?></p>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-alternation-table">
                        <thead>
                            <tr>
                                <th data-sort="text"><?php esc_html_e('Guess Alternation', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($player_guess_alt_buckets as $bucket => $gmbstats): ?>
                                <?php
                                $bucket_label = $bucket . '%–' . ($bucket + 10) . '%';
                                $gbucket_acc = $gmbstats['guesses'] > 0
                                    ? ($gmbstats['correct'] / $gmbstats['guesses']) * 100
                                    : 0;
                                ?>
                                <tr>
                                    <td><?php echo esc_html($bucket_label); ?></td>
                                    <td><?php echo esc_html(number_format_i18n($gmbstats['rounds'])); ?></td>
                                    <td><?php echo esc_html(number_format_i18n($gmbstats['guesses'])); ?></td>
                                    <td><?php echo esc_html(number_format_i18n($gmbstats['correct'])); ?></td>
                                    <td data-value="<?php echo esc_attr(number_format((float) $gbucket_acc, 2, '.', '')); ?>">
                                        <?php echo Kealoa_Formatter::format_percentage($gbucket_acc); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($answer_word_count_results)): ?>
                <div class="kealoa-answer-word-count-stats">
                    <h2><?php esc_html_e('Results by Number of Answer Words', 'kealoa-reference'); ?></h2>
                    <p class="kealoa-section-description"><?php esc_html_e('How this player performed based on how many possible answer words the round had.', 'kealoa-reference'); ?></p>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-answer-word-count-table">
                        <thead>
                            <tr>
                                <th data-sort="number"><?php esc_html_e('Words', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($answer_word_count_results as $result): ?>
                                <tr>
                                    <td><?php echo esc_html($result->answer_word_count); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->total_answered)); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->correct_count)); ?></td>
                                    <?php
                                    $pct = $result->total_answered > 0
                                        ? ($result->correct_count / $result->total_answered) * 100
                                        : 0;
                                    ?>
                                    <td data-value="<?php echo esc_attr(number_format((float) $pct, 2, '.', '')); ?>">
                                        <?php echo Kealoa_Formatter::format_percentage($pct); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($kealoa_type_stats)): ?>
                <div class="kealoa-type-stats">
                    <h2><?php esc_html_e('Results by KEALOA Type', 'kealoa-reference'); ?></h2>
                    <p class="kealoa-section-description"><?php esc_html_e('How this player performed based on the letter relationship between answer words: True (shared positional letters), Near (shared letters in different positions), or Free (no shared letters).', 'kealoa-reference'); ?></p>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-type-table">
                        <thead>
                            <tr>
                                <th data-sort="text"><?php esc_html_e('Type', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (['true', 'near', 'free'] as $kt):
                                if (!isset($kealoa_type_stats[$kt])) continue;
                                $ts = $kealoa_type_stats[$kt];
                                $ts_pct = $ts['guesses'] > 0 ? ($ts['correct'] / $ts['guesses']) * 100 : 0;
                            ?>
                                <tr>
                                    <td><?php echo esc_html(Kealoa_Formatter::format_kealoa_type_label($kt)); ?></td>
                                    <td><?php echo esc_html(number_format_i18n($ts['rounds'])); ?></td>
                                    <td><?php echo esc_html(number_format_i18n($ts['guesses'])); ?></td>
                                    <td><?php echo esc_html(number_format_i18n($ts['correct'])); ?></td>
                                    <td data-value="<?php echo esc_attr(number_format((float) $ts_pct, 2, '.', '')); ?>">
                                        <?php echo Kealoa_Formatter::format_percentage($ts_pct); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endif; ?>

            </div>

                </div><!-- end Overall Stats sub-tab -->

                <div class="kealoa-tab-panel" data-tab="puzzles">

            <?php if (!empty($person_puzzles)): ?>
                <div class="kealoa-person-puzzles">
                    <h2><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></h2>
                    <p class="kealoa-section-description"><?php esc_html_e('Crossword puzzles this player encountered as clues, with individual results.', 'kealoa-reference'); ?></p>

                    <div class="kealoa-filter-controls" data-target="kealoa-person-puzzles-table">
                        <div class="kealoa-filter-row">
                            <div class="kealoa-filter-group">
                                <label for="kealoa-pp-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                                <input type="text" id="kealoa-pp-search" class="kealoa-filter-input" data-filter="search" data-col="2" placeholder="<?php esc_attr_e('Constructor name...', 'kealoa-reference'); ?>">
                            </div>
                            <div class="kealoa-filter-group">
                                <label for="kealoa-pp-day"><?php esc_html_e('Day', 'kealoa-reference'); ?></label>
                                <select id="kealoa-pp-day" class="kealoa-filter-select" data-filter="exact" data-col="0">
                                    <option value=""><?php esc_html_e('All Days', 'kealoa-reference'); ?></option>
                                    <option value="Mon"><?php esc_html_e('Monday', 'kealoa-reference'); ?></option>
                                    <option value="Tue"><?php esc_html_e('Tuesday', 'kealoa-reference'); ?></option>
                                    <option value="Wed"><?php esc_html_e('Wednesday', 'kealoa-reference'); ?></option>
                                    <option value="Thu"><?php esc_html_e('Thursday', 'kealoa-reference'); ?></option>
                                    <option value="Fri"><?php esc_html_e('Friday', 'kealoa-reference'); ?></option>
                                    <option value="Sat"><?php esc_html_e('Saturday', 'kealoa-reference'); ?></option>
                                    <option value="Sun"><?php esc_html_e('Sunday', 'kealoa-reference'); ?></option>
                                </select>
                            </div>
                            <div class="kealoa-filter-group">
                                <label for="kealoa-pp-editor-search"><?php esc_html_e('Editor', 'kealoa-reference'); ?></label>
                                <input type="text" id="kealoa-pp-editor-search" class="kealoa-filter-input" data-filter="search" data-col="3" placeholder="<?php esc_attr_e('Editor name...', 'kealoa-reference'); ?>">
                            </div>
                            <div class="kealoa-filter-group">
                                <label for="kealoa-pp-search-words"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></label>
                                <input type="text" id="kealoa-pp-search-words" class="kealoa-filter-input" data-filter="search" data-col="5" placeholder="<?php esc_attr_e('e.g. KEALOA', 'kealoa-reference'); ?>">
                            </div>
                            <div class="kealoa-filter-group">
                                <label for="kealoa-pp-date-from"><?php esc_html_e('Puzzle Date', 'kealoa-reference'); ?></label>
                                <div class="kealoa-filter-range">
                                    <input type="date" id="kealoa-pp-date-from" class="kealoa-filter-input" data-filter="date-min" data-col="1">
                                    <span class="kealoa-filter-range-sep">&ndash;</span>
                                    <input type="date" id="kealoa-pp-date-to" class="kealoa-filter-input" data-filter="date-max" data-col="1">
                                </div>
                            </div>
                            <div class="kealoa-filter-group kealoa-filter-actions">
                                <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                                <span class="kealoa-filter-count"></span>
                            </div>
                        </div>
                    </div>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-person-puzzles-table" id="kealoa-person-puzzles-table">
                        <thead>
                            <tr>
                                <th data-sort="weekday"><?php esc_html_e('Day', 'kealoa-reference'); ?></th>
                                <th data-sort="date"><?php esc_html_e('Puzzle Date', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
                                <th data-sort="date"><?php esc_html_e('Round Date', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($person_puzzles as $puzzle): ?>
                                <?php
                                $constructor_ids = !empty($puzzle->constructor_ids) ? explode(',', $puzzle->constructor_ids) : [];
                                $constructor_names = !empty($puzzle->constructor_names) ? explode(', ', $puzzle->constructor_names) : [];
                                $round_ids = !empty($puzzle->round_ids) ? explode(',', $puzzle->round_ids) : [];
                                $round_dates = !empty($puzzle->round_dates) ? explode(',', $puzzle->round_dates) : [];
                                $round_numbers = !empty($puzzle->round_numbers) ? explode(',', $puzzle->round_numbers) : [];
                                $round_game_numbers = !empty($puzzle->round_game_numbers) ? explode(',', $puzzle->round_game_numbers) : [];
                                ?>
                                <tr>
                                    <td class="kealoa-day-cell"><?php echo esc_html(Kealoa_Formatter::format_day_abbrev($puzzle->publication_date)); ?></td>
                                    <td>
                                        <?php echo Kealoa_Formatter::format_puzzle_date_link($puzzle->publication_date); ?>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($constructor_ids)) {
                                            $links = [];
                                            for ($i = 0; $i < count($constructor_ids); $i++) {
                                                $cid = (int) $constructor_ids[$i];
                                                $cname = $constructor_names[$i] ?? '';
                                                if ($cid && $cname) {
                                                    $links[] = Kealoa_Formatter::format_constructor_link($cid, $cname);
                                                }
                                            }
                                            echo Kealoa_Formatter::format_list_with_and($links);
                                        } else {
                                            echo '&#x2014;';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($puzzle->editor_id)) {
                                            echo Kealoa_Formatter::format_editor_link((int) $puzzle->editor_id, $puzzle->editor_name);
                                        } else {
                                            echo '&#x2014;';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($round_ids)) {
                                            $round_links = [];
                                            for ($i = 0; $i < count($round_ids); $i++) {
                                                $rid = (int) $round_ids[$i];
                                                $rdate = $round_dates[$i] ?? '';
                                                $gn = (int) ($round_game_numbers[$i] ?? $rid);
                                                if ($rid && $rdate) {
                                                    $round_links[] = Kealoa_Formatter::format_round_date_link($gn, $rdate);
                                                }
                                            }
                                            echo implode('<br>', $round_links);
                                        } else {
                                            echo '&#x2014;';
                                        }
                                        ?>
                                    </td>
                                    <td class="kealoa-solutions-cell">
                                        <?php
                                        if (!empty($round_ids)) {
                                            $solution_links = [];
                                            for ($i = 0; $i < count($round_ids); $i++) {
                                                $rid = (int) $round_ids[$i];
                                                if ($rid) {
                                                    $solutions = $bulk_solutions_map[$rid] ?? [];
                                                    $gn = (int) ($round_game_numbers[$i] ?? $rid);
                                                    $solution_links[] = Kealoa_Formatter::format_solution_words_link($gn, $solutions);
                                                }
                                            }
                                            echo implode('<br>', $solution_links);
                                        } else {
                                            echo '&#x2014;';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php else: ?>
                <p class="kealoa-no-data"><?php esc_html_e('No puzzles found for this player.', 'kealoa-reference'); ?></p>
            <?php endif; ?>

                </div><!-- end Puzzles tab -->

                <div class="kealoa-tab-panel" data-tab="puzzle">

            <?php if (!empty($day_of_week_results)): ?>
                <div class="kealoa-day-of-week-stats">
                    <h2><?php esc_html_e('Results by Puzzle Day of Week', 'kealoa-reference'); ?></h2>
                    <p class="kealoa-section-description"><?php esc_html_e('How this player performed based on the day of the week the puzzle was published.', 'kealoa-reference'); ?></p>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-day-of-week-table">
                        <thead>
                            <tr>
                                <th data-sort="weekday"><?php esc_html_e('Day', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($day_of_week_results as $result): ?>
                                <tr>
                                    <td><?php echo esc_html(Kealoa_Formatter::get_day_name((int) $result->day_of_week)); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->total_answered)); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->correct_count)); ?></td>
                                    <?php
                                    $pct = $result->total_answered > 0
                                        ? ($result->correct_count / $result->total_answered) * 100
                                        : 0;
                                    ?>
                                    <td data-value="<?php echo esc_attr(number_format((float) $pct, 2, '.', '')); ?>">
                                        <?php echo Kealoa_Formatter::format_percentage($pct); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($decade_results)): ?>
                <div class="kealoa-decade-stats">
                    <h2><?php esc_html_e('Results by Puzzle Decade', 'kealoa-reference'); ?></h2>
                    <p class="kealoa-section-description"><?php esc_html_e('How this player performed based on the decade the puzzle was published.', 'kealoa-reference'); ?></p>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-decade-table">
                        <thead>
                            <tr>
                                <th data-sort="number"><?php esc_html_e('Decade', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($decade_results as $result): ?>
                                <tr>
                                    <td><?php echo esc_html($result->decade . 's'); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->total_answered)); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->correct_count)); ?></td>
                                    <?php
                                    $pct = $result->total_answered > 0
                                        ? ($result->correct_count / $result->total_answered) * 100
                                        : 0;
                                    ?>
                                    <td data-value="<?php echo esc_attr(number_format((float) $pct, 2, '.', '')); ?>">
                                        <?php echo Kealoa_Formatter::format_percentage($pct); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($direction_results)): ?>
                <div class="kealoa-direction-stats">
                    <h2><?php esc_html_e('Results by Clue Direction', 'kealoa-reference'); ?></h2>
                    <p class="kealoa-section-description"><?php esc_html_e('How this player performed based on whether the clue was an across or down entry.', 'kealoa-reference'); ?></p>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-direction-table">
                        <thead>
                            <tr>
                                <th data-sort="text"><?php esc_html_e('Direction', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($direction_results as $result): ?>
                                <tr>
                                    <td><?php
                                        if ($result->direction === 'A') {
                                            esc_html_e('Across', 'kealoa-reference');
                                        } elseif ($result->direction === 'D') {
                                            esc_html_e('Down', 'kealoa-reference');
                                        } else {
                                            esc_html_e('No Direction', 'kealoa-reference');
                                        }
                                    ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->total_answered)); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->correct_count)); ?></td>
                                    <?php
                                    $pct = $result->total_answered > 0
                                        ? ($result->correct_count / $result->total_answered) * 100
                                        : 0;
                                    ?>
                                    <td data-value="<?php echo esc_attr(number_format((float) $pct, 2, '.', '')); ?>">
                                        <?php echo Kealoa_Formatter::format_percentage($pct); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endif; ?>

                </div><!-- end Puzzle tab -->

                <div class="kealoa-tab-panel" data-tab="by-constructor">

            <?php if (!empty($constructor_results)): ?>
                <div class="kealoa-constructor-stats">
                    <h2><?php esc_html_e('Results by Constructor', 'kealoa-reference'); ?></h2>
                    <p class="kealoa-section-description"><?php esc_html_e('How this player performed on puzzles by each constructor.', 'kealoa-reference'); ?></p>

                    <div class="kealoa-filter-controls" data-target="kealoa-person-constructor-table">
                        <div class="kealoa-filter-row">
                            <div class="kealoa-filter-group">
                                <label for="kealoa-constructor-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                                <input type="text" id="kealoa-constructor-search" class="kealoa-filter-input" data-filter="search" data-col="0" placeholder="<?php esc_attr_e('Constructor name...', 'kealoa-reference'); ?>">
                            </div>
                            <div class="kealoa-filter-group">
                                <label for="kealoa-constructor-min-guesses"><?php esc_html_e('Min. Guesses', 'kealoa-reference'); ?></label>
                                <input type="number" id="kealoa-constructor-min-guesses" class="kealoa-filter-input" data-filter="min" data-col="1" min="1" placeholder="<?php esc_attr_e('e.g. 5', 'kealoa-reference'); ?>">
                            </div>
                            <div class="kealoa-filter-group">
                                <label for="kealoa-constructor-acc-min"><?php esc_html_e('Accuracy Range', 'kealoa-reference'); ?></label>
                                <div class="kealoa-filter-range">
                                    <input type="number" id="kealoa-constructor-acc-min" class="kealoa-filter-input" data-filter="range-min" data-col="3" min="0" max="100" placeholder="<?php esc_attr_e('0%', 'kealoa-reference'); ?>">
                                    <span class="kealoa-filter-range-sep">&ndash;</span>
                                    <input type="number" id="kealoa-constructor-acc-max" class="kealoa-filter-input" data-filter="range-max" data-col="3" min="0" max="100" placeholder="<?php esc_attr_e('100%', 'kealoa-reference'); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="kealoa-filter-row">
                            <div class="kealoa-filter-group">
                                <label for="kealoa-constructor-topn"><?php esc_html_e('Show Top/Bottom', 'kealoa-reference'); ?></label>
                                <div class="kealoa-filter-topn">
                                    <select id="kealoa-constructor-topn-dir" class="kealoa-filter-select" data-filter="topn-dir">
                                        <option value=""><?php esc_html_e('All', 'kealoa-reference'); ?></option>
                                        <option value="top"><?php esc_html_e('Top', 'kealoa-reference'); ?></option>
                                        <option value="bottom"><?php esc_html_e('Bottom', 'kealoa-reference'); ?></option>
                                    </select>
                                    <input type="number" id="kealoa-constructor-topn" class="kealoa-filter-input" data-filter="topn-count" min="1" placeholder="N">
                                </div>
                            </div>
                            <div class="kealoa-filter-group">
                                <label for="kealoa-constructor-avg"><?php esc_html_e('vs. Average', 'kealoa-reference'); ?></label>
                                <select id="kealoa-constructor-avg" class="kealoa-filter-select" data-filter="vs-avg" data-avg="<?php echo esc_attr($stats->overall_percentage); ?>">
                                    <option value=""><?php esc_html_e('All', 'kealoa-reference'); ?></option>
                                    <option value="above"><?php echo esc_html(sprintf(
                                        /* translators: %s is the player's overall accuracy percentage */
                                        __('Above Average (%s)', 'kealoa-reference'),
                                        Kealoa_Formatter::format_percentage((float) $stats->overall_percentage)
                                    )); ?></option>
                                    <option value="below"><?php echo esc_html(sprintf(
                                        /* translators: %s is the player's overall accuracy percentage */
                                        __('Below Average (%s)', 'kealoa-reference'),
                                        Kealoa_Formatter::format_percentage((float) $stats->overall_percentage)
                                    )); ?></option>
                                </select>
                            </div>
                            <div class="kealoa-filter-group kealoa-filter-actions">
                                <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                                <span class="kealoa-filter-count"></span>
                            </div>
                        </div>
                    </div>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-constructor-table" id="kealoa-person-constructor-table">
                        <thead>
                            <tr>
                                <th data-sort="text"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($constructor_results as $result): ?>
                                <tr>
                                    <td><?php echo Kealoa_Formatter::format_constructor_link((int) $result->constructor_id, $result->full_name); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->total_answered)); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->correct_count)); ?></td>
                                    <?php
                                    $pct = $result->total_answered > 0
                                        ? ($result->correct_count / $result->total_answered) * 100
                                        : 0;
                                    ?>
                                    <td data-value="<?php echo esc_attr(number_format((float) $pct, 2, '.', '')); ?>">
                                        <?php echo Kealoa_Formatter::format_percentage($pct); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endif; ?>

                </div><!-- end By Constructor tab -->

                <div class="kealoa-tab-panel" data-tab="by-editor">

            <?php if (!empty($editor_results)): ?>
                <div class="kealoa-editor-stats">
                    <h2><?php esc_html_e('Results by Editor', 'kealoa-reference'); ?></h2>
                    <p class="kealoa-section-description"><?php esc_html_e('How this player performed on puzzles by each editor.', 'kealoa-reference'); ?></p>

                    <div class="kealoa-filter-controls" data-target="kealoa-person-editor-table">
                        <div class="kealoa-filter-row">
                            <div class="kealoa-filter-group">
                                <label for="kealoa-pe-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                                <input type="text" id="kealoa-pe-search" class="kealoa-filter-input" data-filter="search" data-col="0" placeholder="<?php esc_attr_e('Editor name...', 'kealoa-reference'); ?>">
                            </div>
                            <div class="kealoa-filter-group">
                                <label for="kealoa-pe-min-guesses"><?php esc_html_e('Min. Guesses', 'kealoa-reference'); ?></label>
                                <input type="number" id="kealoa-pe-min-guesses" class="kealoa-filter-input" data-filter="min" data-col="1" min="1" placeholder="<?php esc_attr_e('e.g. 5', 'kealoa-reference'); ?>">
                            </div>
                            <div class="kealoa-filter-group kealoa-filter-actions">
                                <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                                <span class="kealoa-filter-count"></span>
                            </div>
                        </div>
                    </div>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-editor-table" id="kealoa-person-editor-table">
                        <thead>
                            <tr>
                                <th data-sort="text"><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($editor_results as $result): ?>
                                <tr>
                                    <td><?php echo Kealoa_Formatter::format_editor_link((int) $result->editor_id, $result->editor_name); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->total_answered)); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->correct_count)); ?></td>
                                    <?php
                                    $pct = $result->total_answered > 0
                                        ? ($result->correct_count / $result->total_answered) * 100
                                        : 0;
                                    ?>
                                    <td data-value="<?php echo esc_attr(number_format((float) $pct, 2, '.', '')); ?>">
                                        <?php echo Kealoa_Formatter::format_percentage($pct); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endif; ?>

                </div><!-- end By Editor tab -->

                <div class="kealoa-tab-panel" data-tab="round">

            <?php if (!empty($round_history)): ?>
                <?php
                // Pre-compute co-players for each round to determine if "Played With" column is needed
                $round_co_players = [];
                $has_co_players = false;
                foreach ($round_history as $rh_check) {
                    $rh_guessers = $bulk_guessers_map[(int) $rh_check->round_id] ?? [];
                    $co_players = array_filter($rh_guessers, function($g) use ($person_ids) {
                        return !in_array((int) $g->id, $person_ids, true);
                    });
                    $round_co_players[(int) $rh_check->round_id] = $co_players;
                    if (!empty($co_players)) {
                        $has_co_players = true;
                    }
                }
                ?>
                <div class="kealoa-round-history">
                    <h2><?php esc_html_e('Round History', 'kealoa-reference'); ?></h2>
                    <p class="kealoa-section-description"><?php esc_html_e('Complete list of all rounds this player has participated in.', 'kealoa-reference'); ?></p>

                    <div class="kealoa-filter-controls" data-target="kealoa-person-round-history-table">
                        <div class="kealoa-filter-row">
                            <div class="kealoa-filter-group">
                                <label for="kealoa-rh-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                                <input type="text" id="kealoa-rh-search" class="kealoa-filter-input" data-filter="search" data-col="1" placeholder="<?php esc_attr_e('Solution words...', 'kealoa-reference'); ?>">
                            </div>
                            <div class="kealoa-filter-group">
                                <label for="kealoa-rh-type"><?php esc_html_e('Type', 'kealoa-reference'); ?></label>
                                <select id="kealoa-rh-type" class="kealoa-filter-select" data-filter="exact" data-col="2">
                                    <option value=""><?php esc_html_e('All Types', 'kealoa-reference'); ?></option>
                                    <option value="true"><?php esc_html_e('True', 'kealoa-reference'); ?></option>
                                    <option value="near"><?php esc_html_e('Near', 'kealoa-reference'); ?></option>
                                    <option value="free"><?php esc_html_e('Free', 'kealoa-reference'); ?></option>
                                </select>
                            </div>
                            <div class="kealoa-filter-group">
                                <label for="kealoa-rh-year"><?php esc_html_e('Year', 'kealoa-reference'); ?></label>
                                <select id="kealoa-rh-year" class="kealoa-filter-select" data-filter="year" data-col="0">
                                    <option value=""><?php esc_html_e('All Years', 'kealoa-reference'); ?></option>
                                    <?php
                                    $rh_years = [];
                                    foreach ($round_history as $rh) {
                                        $rh_years[(int) date('Y', strtotime($rh->round_date))] = true;
                                    }
                                    krsort($rh_years);
                                    foreach (array_keys($rh_years) as $rh_year): ?>
                                        <option value="<?php echo esc_attr($rh_year); ?>"><?php echo esc_html($rh_year); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="kealoa-filter-group">
                                <label for="kealoa-rh-min-correct"><?php esc_html_e('Min. Correct', 'kealoa-reference'); ?></label>
                                <input type="number" id="kealoa-rh-min-correct" class="kealoa-filter-input" data-filter="min" data-col="<?php echo $has_co_players ? '4' : '3'; ?>" min="0" placeholder="<?php esc_attr_e('e.g. 5', 'kealoa-reference'); ?>">
                            </div>
                        </div>
                        <div class="kealoa-filter-row">
                            <div class="kealoa-filter-group">
                                <label for="kealoa-rh-min-streak"><?php esc_html_e('Min. Streak', 'kealoa-reference'); ?></label>
                                <input type="number" id="kealoa-rh-min-streak" class="kealoa-filter-input" data-filter="min" data-col="<?php echo $has_co_players ? '5' : '4'; ?>" min="0" placeholder="<?php esc_attr_e('e.g. 3', 'kealoa-reference'); ?>">
                            </div>
                            <div class="kealoa-filter-group">
                                <label for="kealoa-rh-perfect"><?php esc_html_e('Score', 'kealoa-reference'); ?></label>
                                <select id="kealoa-rh-perfect" class="kealoa-filter-select" data-filter="perfect" data-col="<?php echo $has_co_players ? '4' : '3'; ?>">
                                    <option value=""><?php esc_html_e('All Scores', 'kealoa-reference'); ?></option>
                                    <option value="perfect"><?php esc_html_e('Perfect Only', 'kealoa-reference'); ?></option>
                                    <option value="imperfect"><?php esc_html_e('Not Perfect', 'kealoa-reference'); ?></option>
                                </select>
                            </div>
                            <div class="kealoa-filter-group kealoa-filter-actions">
                                <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                                <span class="kealoa-filter-count"></span>
                            </div>
                        </div>
                    </div>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-history-table" id="kealoa-person-round-history-table">
                        <thead>
                            <tr>
                                <th data-sort="date" data-default-sort="desc"><?php esc_html_e('Date', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Type', 'kealoa-reference'); ?></th>
                                <?php if ($has_co_players): ?>
                                <th data-sort="text"><?php esc_html_e('Played With', 'kealoa-reference'); ?></th>
                                <?php endif; ?>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Streak', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($round_history as $history): ?>
                                <?php
                                $hist_rid = (int) $history->round_id;
                                $solutions = $bulk_solutions_map[$hist_rid] ?? [];
                                $history_round_num = (int) ($history->round_number ?? 1);
                                $history_date_count = $rounds_per_date_map[$history->round_date] ?? 1;
                                ?>
                                <tr data-year="<?php echo esc_attr(date('Y', strtotime($history->round_date))); ?>">
                                    <td data-sort-value="<?php echo esc_attr(date('Ymd', strtotime($history->round_date)) * 100 + $history_round_num); ?>">
                                        <?php
                                        echo Kealoa_Formatter::format_round_date_link((int) $history->game_number, $history->round_date);
                                        if ($history_date_count > 1) {
                                            echo ' <span class="kealoa-round-number">(#' . esc_html($history_round_num) . ')</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo Kealoa_Formatter::format_solution_words_link((int) $history->game_number, $solutions); ?></td>
                                    <td class="kealoa-type-cell"><?php
                                        $hist_type = Kealoa_Formatter::classify_kealoa_type($solutions);
                                        echo esc_html(Kealoa_Formatter::format_kealoa_type_label($hist_type));
                                    ?></td>
                                    <?php if ($has_co_players): ?>
                                    <td><?php
                                        $co = $round_co_players[(int) $history->round_id] ?? [];
                                        if (!empty($co)) {
                                            $co_links = array_map(function($p) {
                                                return Kealoa_Formatter::format_person_link((int) $p->id, $p->full_name);
                                            }, $co);
                                            echo implode(', ', $co_links);
                                        } else {
                                            echo '—';
                                        }
                                    ?></td>
                                    <?php endif; ?>
                                    <td data-total="<?php echo esc_attr((int) $history->total_clues); ?>"><?php echo esc_html(number_format_i18n((int) $history->correct_count)); ?></td>
                                    <td><?php echo esc_html($streak_per_round[$hist_rid] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endif; ?>

                </div><!-- end Round sub-tab -->

                <div class="kealoa-tab-panel" data-tab="player-streaks">

                    <?php if ($player_streaks && !empty($player_streaks->streaks)): ?>
                    <div class="kealoa-player-streaks">
                        <h2><?php esc_html_e('Streaks', 'kealoa-reference'); ?></h2>
                        <p class="kealoa-section-description"><?php esc_html_e('Consecutive correct and incorrect answer streaks across rounds played.', 'kealoa-reference'); ?></p>

                        <div class="kealoa-filter-controls" data-target="kealoa-person-player-streaks-table">
                            <div class="kealoa-filter-row">
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pps-type"><?php esc_html_e('Type', 'kealoa-reference'); ?></label>
                                    <select id="kealoa-pps-type" class="kealoa-filter-input" data-filter="exact" data-col="1">
                                        <option value=""><?php esc_html_e('All', 'kealoa-reference'); ?></option>
                                        <option value="Correct"><?php esc_html_e('Correct', 'kealoa-reference'); ?></option>
                                        <option value="Incorrect"><?php esc_html_e('Incorrect', 'kealoa-reference'); ?></option>
                                    </select>
                                </div>
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pps-min-length"><?php esc_html_e('Min Length', 'kealoa-reference'); ?></label>
                                    <input type="number" id="kealoa-pps-min-length" class="kealoa-filter-input" data-filter="min" data-col="0" min="2" placeholder="2">
                                </div>
                                <div class="kealoa-filter-group kealoa-filter-actions">
                                    <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                                    <span class="kealoa-filter-count"></span>
                                </div>
                            </div>
                        </div>

                        <div class="kealoa-table-scroll">
                        <table class="kealoa-table" id="kealoa-person-player-streaks-table">
                            <thead>
                                <tr>
                                    <th data-sort="number" class="sorted desc"><?php esc_html_e('Length', 'kealoa-reference'); ?></th>
                                    <th data-sort="text"><?php esc_html_e('Type', 'kealoa-reference'); ?></th>
                                    <th data-sort="date"><?php esc_html_e('Start', 'kealoa-reference'); ?></th>
                                    <th data-sort="date"><?php esc_html_e('End', 'kealoa-reference'); ?></th>
                                    <th><?php esc_html_e('Rounds', 'kealoa-reference'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($player_streaks->streaks as $streak): ?>
                                <tr>
                                    <td data-value="<?php echo esc_attr($streak->length); ?>"><?php echo esc_html(number_format_i18n($streak->length)); ?></td>
                                    <td><?php echo esc_html($streak->type === 'correct' ? __('Correct', 'kealoa-reference') : __('Incorrect', 'kealoa-reference')); ?></td>
                                    <td data-value="<?php echo esc_attr($streak->start_date); ?>"><?php echo esc_html(Kealoa_Formatter::format_date($streak->start_date)); ?></td>
                                    <td data-value="<?php echo esc_attr($streak->end_date); ?>"><?php echo esc_html(Kealoa_Formatter::format_date($streak->end_date)); ?></td>
                                    <td><a class="kealoa-round-picker-link" data-rounds="<?php echo $build_picker_json($streak->round_ids); ?>"><?php echo esc_html(number_format_i18n(count($streak->round_ids))); ?></a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                    <?php else: ?>
                    <p><?php esc_html_e('No streaks of 2 or more clues found.', 'kealoa-reference'); ?></p>
                    <?php endif; ?>

                </div><!-- end player-streaks sub-tab -->

                </div><!-- end secondary kealoa-tabs -->
                </div><!-- end Player primary tab -->
                <?php endif; ?><!-- end $is_player -->

                <?php if ($is_clue_giver): ?>
                <div class="kealoa-tab-panel<?php echo $tab_active('host'); ?>" data-tab="host">

                <div class="kealoa-tabs">
                    <div class="kealoa-tab-nav">
                        <button class="kealoa-tab-button active" data-tab="host-rounds"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></button>
                        <button class="kealoa-tab-button" data-tab="host-stats"><?php esc_html_e('Stats', 'kealoa-reference'); ?></button>
                        <button class="kealoa-tab-button" data-tab="host-streaks"><?php esc_html_e('Streaks', 'kealoa-reference'); ?></button>
                    </div>

                <div class="kealoa-tab-panel" data-tab="host-stats">

                    <?php if ($clue_giver_stats): ?>
                    <div class="kealoa-person-clue-giver-overview">
                        <h2><?php esc_html_e('Host Statistics', 'kealoa-reference'); ?></h2>
                        <p class="kealoa-section-description"><?php esc_html_e('Summary of performance across all rounds hosted by this person.', 'kealoa-reference'); ?></p>
                        <div class="kealoa-stats-grid">
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $clue_giver_stats->round_count)); ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></span>
                            </div>
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $clue_giver_stats->clue_count)); ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Clues Given', 'kealoa-reference'); ?></span>
                            </div>
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $clue_giver_stats->guesser_count)); ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Players', 'kealoa-reference'); ?></span>
                            </div>
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $clue_giver_stats->total_guesses)); ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></span>
                            </div>
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $clue_giver_stats->correct_guesses)); ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Correct', 'kealoa-reference'); ?></span>
                            </div>
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php
                                    $cg_accuracy = (int) $clue_giver_stats->total_guesses > 0
                                        ? ((int) $clue_giver_stats->correct_guesses / (int) $clue_giver_stats->total_guesses) * 100
                                        : 0;
                                    echo Kealoa_Formatter::format_percentage($cg_accuracy);
                                ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- end host-stats sub-tab -->

                <div class="kealoa-tab-panel" data-tab="host-streaks">

                    <?php if ($clue_giver_streaks && !empty($clue_giver_streaks->streaks)): ?>
                    <div class="kealoa-clue-giver-streaks">
                        <h2><?php esc_html_e('Streaks', 'kealoa-reference'); ?></h2>
                        <p class="kealoa-section-description"><?php esc_html_e('Consecutive correct and incorrect answer streaks across hosted rounds.', 'kealoa-reference'); ?></p>

                        <div class="kealoa-filter-controls" data-target="kealoa-person-cg-streaks-table">
                            <div class="kealoa-filter-row">
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pcgs-type"><?php esc_html_e('Type', 'kealoa-reference'); ?></label>
                                    <select id="kealoa-pcgs-type" class="kealoa-filter-input" data-filter="exact" data-col="1">
                                        <option value=""><?php esc_html_e('All', 'kealoa-reference'); ?></option>
                                        <option value="Correct"><?php esc_html_e('Correct', 'kealoa-reference'); ?></option>
                                        <option value="Incorrect"><?php esc_html_e('Incorrect', 'kealoa-reference'); ?></option>
                                    </select>
                                </div>
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pcgs-min-length"><?php esc_html_e('Min Length', 'kealoa-reference'); ?></label>
                                    <input type="number" id="kealoa-pcgs-min-length" class="kealoa-filter-input" data-filter="min" data-col="0" min="2" placeholder="2">
                                </div>
                                <div class="kealoa-filter-group kealoa-filter-actions">
                                    <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                                    <span class="kealoa-filter-count"></span>
                                </div>
                            </div>
                        </div>

                        <div class="kealoa-table-scroll">
                        <table class="kealoa-table" id="kealoa-person-cg-streaks-table">
                            <thead>
                                <tr>
                                    <th data-sort="number" class="sorted desc"><?php esc_html_e('Length', 'kealoa-reference'); ?></th>
                                    <th data-sort="text"><?php esc_html_e('Type', 'kealoa-reference'); ?></th>
                                    <th data-sort="date"><?php esc_html_e('Start', 'kealoa-reference'); ?></th>
                                    <th data-sort="date"><?php esc_html_e('End', 'kealoa-reference'); ?></th>
                                    <th><?php esc_html_e('Rounds', 'kealoa-reference'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clue_giver_streaks->streaks as $streak): ?>
                                <tr>
                                    <td data-value="<?php echo esc_attr($streak->length); ?>"><?php echo esc_html(number_format_i18n($streak->length)); ?></td>
                                    <td><?php echo esc_html($streak->type === 'correct' ? __('Correct', 'kealoa-reference') : __('Incorrect', 'kealoa-reference')); ?></td>
                                    <td data-value="<?php echo esc_attr($streak->start_date); ?>"><?php echo esc_html(Kealoa_Formatter::format_date($streak->start_date)); ?></td>
                                    <td data-value="<?php echo esc_attr($streak->end_date); ?>"><?php echo esc_html(Kealoa_Formatter::format_date($streak->end_date)); ?></td>
                                    <td><a class="kealoa-round-picker-link" data-rounds="<?php echo $build_cg_picker_json($streak->round_ids); ?>"><?php echo esc_html(number_format_i18n(count($streak->round_ids))); ?></a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                    <?php else: ?>
                    <p><?php esc_html_e('No streaks of 2 or more clues found.', 'kealoa-reference'); ?></p>
                    <?php endif; ?>

                </div><!-- end host-streaks sub-tab -->

                <div class="kealoa-tab-panel active" data-tab="host-rounds">

                    <?php if (!empty($clue_giver_rounds)): ?>
                    <div class="kealoa-clue-giver-rounds">
                        <h2><?php esc_html_e('Rounds', 'kealoa-reference'); ?></h2>
                        <p class="kealoa-section-description"><?php esc_html_e('All rounds where this person served as the clue giver.', 'kealoa-reference'); ?></p>

                        <div class="kealoa-filter-controls" data-target="kealoa-person-cg-rounds-table">
                            <div class="kealoa-filter-row">
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pcgr-date-from"><?php esc_html_e('Date', 'kealoa-reference'); ?></label>
                                    <div class="kealoa-filter-range">
                                        <input type="date" id="kealoa-pcgr-date-from" class="kealoa-filter-input" data-filter="date-min" data-col="0">
                                        <span class="kealoa-filter-range-sep">&ndash;</span>
                                        <input type="date" id="kealoa-pcgr-date-to" class="kealoa-filter-input" data-filter="date-max" data-col="0">
                                    </div>
                                </div>
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pcgr-solution"><?php esc_html_e('Solution Word', 'kealoa-reference'); ?></label>
                                    <input type="text" id="kealoa-pcgr-solution" class="kealoa-filter-input" data-filter="search" data-col="1" placeholder="<?php esc_attr_e('Solution word...', 'kealoa-reference'); ?>">
                                </div>
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pcgr-guesser"><?php esc_html_e('Player', 'kealoa-reference'); ?></label>
                                    <input type="text" id="kealoa-pcgr-guesser" class="kealoa-filter-input" data-filter="search" data-col="2" placeholder="<?php esc_attr_e('Player name...', 'kealoa-reference'); ?>">
                                </div>
                                <div class="kealoa-filter-group kealoa-filter-actions">
                                    <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                                    <span class="kealoa-filter-count"></span>
                                </div>
                            </div>
                        </div>

                        <div class="kealoa-table-scroll">
                        <table class="kealoa-table" id="kealoa-person-cg-rounds-table">
                            <thead>
                                <tr>
                                    <th data-sort="date"><?php esc_html_e('Date', 'kealoa-reference'); ?></th>
                                    <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                                    <th data-sort="text"><?php esc_html_e('Players', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Clues', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clue_giver_rounds as $cgr): ?>
                                    <?php
                                    $cgr_id = (int) $cgr->round_id;
                                    $cgr_pct = (int) $cgr->total_guesses > 0
                                        ? ((int) $cgr->correct_guesses / (int) $cgr->total_guesses) * 100
                                        : 0;
                                    $cgr_solutions = $bulk_solutions_map[$cgr_id] ?? [];
                                    $cgr_guesser_ids = !empty($cgr->guesser_ids) ? array_map('intval', explode(',', $cgr->guesser_ids)) : [];
                                    $cgr_guesser_names = !empty($cgr->guesser_names) ? explode(', ', $cgr->guesser_names) : [];
                                    $cgr_alternation_pct = $bulk_alternation_map[$cgr_id] ?? 0.0;
                                    $cgr_date_count = $rounds_per_date_map[$cgr->round_date] ?? 1;
                                    $cgr_round_num = (int) ($cgr->round_number ?? 1);
                                    ?>
                                    <tr>
                                        <td data-sort-value="<?php echo esc_attr(date('Ymd', strtotime($cgr->round_date)) * 100 + $cgr_round_num); ?>">
                                            <?php
                                            echo Kealoa_Formatter::format_round_date_link((int) $cgr->game_number, $cgr->round_date);
                                            if ($cgr_date_count > 1) {
                                                echo ' <span class="kealoa-round-number">(#' . esc_html($cgr_round_num) . ')</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="kealoa-solutions-cell"><?php echo Kealoa_Formatter::format_solution_words_link((int) $cgr->game_number, $cgr_solutions); ?></td>
                                        <td><?php
                                            if (!empty($cgr_guesser_ids)) {
                                                $cgr_guesser_links = [];
                                                for ($i = 0; $i < count($cgr_guesser_ids); $i++) {
                                                    $gid = $cgr_guesser_ids[$i];
                                                    $gname = $cgr_guesser_names[$i] ?? '';
                                                    if ($gid && $gname) {
                                                        $cgr_guesser_links[] = Kealoa_Formatter::format_person_link($gid, $gname);
                                                    }
                                                }
                                                echo implode(', ', $cgr_guesser_links);
                                            } else {
                                                echo '—';
                                            }
                                        ?></td>
                                        <td><?php echo esc_html($cgr->clue_count); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((int) $cgr->total_guesses)); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((int) $cgr->correct_guesses)); ?></td>
                                        <td data-value="<?php echo esc_attr(number_format((float) $cgr_pct, 2, '.', '')); ?>">
                                            <?php echo Kealoa_Formatter::format_percentage($cgr_pct); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- end host-rounds sub-tab -->

                </div><!-- end host secondary kealoa-tabs -->
                </div><!-- end Host primary tab -->
                <?php endif; ?><!-- end $is_clue_giver -->

                <?php if ($is_constructor): ?>
                <div class="kealoa-tab-panel<?php echo $tab_active('constructor'); ?>" data-tab="constructor">

                <div class="kealoa-tabs">
                    <div class="kealoa-tab-nav">
                        <button class="kealoa-tab-button active" data-tab="constructor-puzzles"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></button>
                        <button class="kealoa-tab-button" data-tab="constructor-results"><?php esc_html_e('Results', 'kealoa-reference'); ?></button>
                        <button class="kealoa-tab-button" data-tab="constructor-stats"><?php esc_html_e('Stats', 'kealoa-reference'); ?></button>
                    </div>

                <div class="kealoa-tab-panel" data-tab="constructor-stats">

                    <?php if ($constructor_stats): ?>
                    <div class="kealoa-person-constructor-overview">
                        <h2><?php esc_html_e('Constructor Statistics', 'kealoa-reference'); ?></h2>
                        <p class="kealoa-section-description"><?php esc_html_e('Summary of player performance on puzzles constructed by this person.', 'kealoa-reference'); ?></p>
                        <div class="kealoa-stats-grid">
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $constructor_stats->puzzle_count)); ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></span>
                            </div>
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $constructor_stats->clue_count)); ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Clues', 'kealoa-reference'); ?></span>
                            </div>
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $constructor_stats->player_count)); ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Players', 'kealoa-reference'); ?></span>
                            </div>
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $constructor_stats->total_guesses)); ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></span>
                            </div>
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $constructor_stats->correct_guesses)); ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Correct', 'kealoa-reference'); ?></span>
                            </div>
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php
                                    $con_accuracy = (int) $constructor_stats->total_guesses > 0
                                        ? ((int) $constructor_stats->correct_guesses / (int) $constructor_stats->total_guesses) * 100
                                        : 0;
                                    echo Kealoa_Formatter::format_percentage($con_accuracy);
                                ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- end constructor-stats sub-tab -->

                <div class="kealoa-tab-panel active" data-tab="constructor-puzzles">

                    <?php if (!empty($constructor_puzzles)): ?>
                    <div class="kealoa-constructor-puzzles">
                        <h2><?php esc_html_e('Puzzles Constructed', 'kealoa-reference'); ?></h2>
                        <p class="kealoa-section-description"><?php esc_html_e('All puzzles constructed by this person that have been used as clues.', 'kealoa-reference'); ?></p>

                        <div class="kealoa-filter-controls" data-target="kealoa-person-con-puzzles-table">
                            <div class="kealoa-filter-row">
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pcon-day"><?php esc_html_e('Day', 'kealoa-reference'); ?></label>
                                    <select id="kealoa-pcon-day" class="kealoa-filter-select" data-filter="exact" data-col="0">
                                        <option value=""><?php esc_html_e('All Days', 'kealoa-reference'); ?></option>
                                        <option value="Mon"><?php esc_html_e('Monday', 'kealoa-reference'); ?></option>
                                        <option value="Tue"><?php esc_html_e('Tuesday', 'kealoa-reference'); ?></option>
                                        <option value="Wed"><?php esc_html_e('Wednesday', 'kealoa-reference'); ?></option>
                                        <option value="Thu"><?php esc_html_e('Thursday', 'kealoa-reference'); ?></option>
                                        <option value="Fri"><?php esc_html_e('Friday', 'kealoa-reference'); ?></option>
                                        <option value="Sat"><?php esc_html_e('Saturday', 'kealoa-reference'); ?></option>
                                        <option value="Sun"><?php esc_html_e('Sunday', 'kealoa-reference'); ?></option>
                                    </select>
                                </div>
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pcon-editor"><?php esc_html_e('Editor', 'kealoa-reference'); ?></label>
                                    <input type="text" id="kealoa-pcon-editor" class="kealoa-filter-input" data-filter="search" data-col="<?php echo $has_co_constructors ? '3' : '2'; ?>" placeholder="<?php esc_attr_e('Editor name...', 'kealoa-reference'); ?>">
                                </div>
                                <div class="kealoa-filter-group kealoa-filter-actions">
                                    <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                                    <span class="kealoa-filter-count"></span>
                                </div>
                            </div>
                        </div>

                        <div class="kealoa-table-scroll">
                        <table class="kealoa-table kealoa-constructor-puzzles-table" id="kealoa-person-con-puzzles-table">
                            <thead>
                                <tr>
                                    <th data-sort="weekday"><?php esc_html_e('Day', 'kealoa-reference'); ?></th>
                                    <th data-sort="date"><?php esc_html_e('Puzzle Date', 'kealoa-reference'); ?></th>
                                    <?php if ($has_co_constructors): ?>
                                    <th data-sort="text"><?php esc_html_e('Co-Constructors', 'kealoa-reference'); ?></th>
                                    <?php endif; ?>
                                    <th data-sort="text"><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
                                    <th data-sort="date"><?php esc_html_e('Round Date', 'kealoa-reference'); ?></th>
                                    <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($constructor_puzzles as $cpuzzle): ?>
                                    <?php
                                    $co_constructors = $bulk_co_constructors_map[(int) $cpuzzle->puzzle_id] ?? [];
                                    $cp_round_ids = !empty($cpuzzle->round_ids) ? explode(',', $cpuzzle->round_ids) : [];
                                    $cp_round_dates = !empty($cpuzzle->round_dates) ? explode(',', $cpuzzle->round_dates) : [];
                                    $cp_round_numbers = !empty($cpuzzle->round_numbers) ? explode(',', $cpuzzle->round_numbers) : [];
                                    $cp_round_game_numbers = !empty($cpuzzle->round_game_numbers) ? explode(',', $cpuzzle->round_game_numbers) : [];
                                    ?>
                                    <tr>
                                        <td class="kealoa-day-cell"><?php echo esc_html(Kealoa_Formatter::format_day_abbrev($cpuzzle->publication_date)); ?></td>
                                        <td>
                                            <?php echo Kealoa_Formatter::format_puzzle_date_link($cpuzzle->publication_date); ?>
                                        </td>
                                        <?php if ($has_co_constructors): ?>
                                        <td>
                                            <?php
                                            if (!empty($co_constructors)) {
                                                $co_links = array_map(function($co) {
                                                    return Kealoa_Formatter::format_constructor_link((int) $co->id, $co->full_name);
                                                }, $co_constructors);
                                                echo Kealoa_Formatter::format_list_with_and($co_links);
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                        <?php endif; ?>
                                        <td>
                                            <?php
                                            if (!empty($cpuzzle->editor_id)) {
                                                echo Kealoa_Formatter::format_editor_link((int) $cpuzzle->editor_id, $cpuzzle->editor_name);
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if (!empty($cp_round_ids)) {
                                                $cp_round_links = [];
                                                for ($i = 0; $i < count($cp_round_ids); $i++) {
                                                    $rid = (int) $cp_round_ids[$i];
                                                    $rdate = $cp_round_dates[$i] ?? '';
                                                    $gn = (int) ($cp_round_game_numbers[$i] ?? $rid);
                                                    if ($rid && $rdate) {
                                                        $cp_round_links[] = Kealoa_Formatter::format_round_date_link($gn, $rdate);
                                                    }
                                                }
                                                echo implode('<br>', $cp_round_links);
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                        <td class="kealoa-solutions-cell">
                                            <?php
                                            if (!empty($cp_round_ids)) {
                                                $cp_sol_links = [];
                                                for ($i = 0; $i < count($cp_round_ids); $i++) {
                                                    $rid = (int) $cp_round_ids[$i];
                                                    if ($rid) {
                                                        $solutions = $bulk_solutions_map[$rid] ?? [];
                                                        $gn = (int) ($cp_round_game_numbers[$i] ?? $rid);
                                                        $cp_sol_links[] = Kealoa_Formatter::format_solution_words_link($gn, $solutions);
                                                    }
                                                }
                                                echo implode('<br>', $cp_sol_links);
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                        <?php
                                        $cp_stats = $bulk_puzzle_stats_map[(int) $cpuzzle->puzzle_id] ?? (object) ['total_guesses' => 0, 'correct_guesses' => 0];
                                        $cp_total   = (int) $cp_stats->total_guesses;
                                        $cp_correct = (int) $cp_stats->correct_guesses;
                                        $cp_pct     = $cp_total > 0 ? ($cp_correct / $cp_total) * 100 : 0;
                                        ?>
                                        <td class="kealoa-clue-guesses"><?php echo esc_html(number_format_i18n($cp_total)); ?></td>
                                        <td class="kealoa-clue-correct"><?php echo esc_html(number_format_i18n($cp_correct)); ?></td>
                                        <td class="kealoa-clue-accuracy" data-value="<?php echo esc_attr(number_format((float) $cp_pct, 2, '.', '')); ?>">
                                            <?php echo Kealoa_Formatter::format_percentage($cp_pct); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- end constructor-puzzles sub-tab -->

                <div class="kealoa-tab-panel" data-tab="constructor-results">

                    <?php if (!empty($constructor_player_results)): ?>
                    <div class="kealoa-constructor-player-stats">
                        <h2><?php esc_html_e('Player Results', 'kealoa-reference'); ?></h2>
                        <p class="kealoa-section-description"><?php esc_html_e('How each player performed on this constructor\'s puzzles.', 'kealoa-reference'); ?></p>

                        <div class="kealoa-filter-controls" data-target="kealoa-person-con-player-table">
                            <div class="kealoa-filter-row">
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pcpl-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                                    <input type="text" id="kealoa-pcpl-search" class="kealoa-filter-input" data-filter="search" data-col="0" placeholder="<?php esc_attr_e('Player name...', 'kealoa-reference'); ?>">
                                </div>
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pcpl-min-guesses"><?php esc_html_e('Min. Guesses', 'kealoa-reference'); ?></label>
                                    <input type="number" id="kealoa-pcpl-min-guesses" class="kealoa-filter-input" data-filter="min" data-col="1" min="1" placeholder="<?php esc_attr_e('e.g. 5', 'kealoa-reference'); ?>">
                                </div>
                                <div class="kealoa-filter-group kealoa-filter-actions">
                                    <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                                    <span class="kealoa-filter-count"></span>
                                </div>
                            </div>
                        </div>

                        <div class="kealoa-table-scroll">
                        <table class="kealoa-table kealoa-constructor-player-table" id="kealoa-person-con-player-table">
                            <thead>
                                <tr>
                                    <th data-sort="text"><?php esc_html_e('Player', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($constructor_player_results as $cpr): ?>
                                    <tr>
                                        <td><?php echo Kealoa_Formatter::format_person_link((int) $cpr->person_id, $cpr->full_name); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((int) $cpr->total_answered)); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((int) $cpr->correct_count)); ?></td>
                                        <?php
                                        $pct = $cpr->total_answered > 0
                                            ? ($cpr->correct_count / $cpr->total_answered) * 100
                                            : 0;
                                        ?>
                                        <td data-value="<?php echo esc_attr(number_format((float) $pct, 2, '.', '')); ?>">
                                            <?php echo Kealoa_Formatter::format_percentage($pct); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($constructor_editor_results)): ?>
                    <div class="kealoa-constructor-editor-stats">
                        <h2><?php esc_html_e('Editor Results', 'kealoa-reference'); ?></h2>
                        <p class="kealoa-section-description"><?php esc_html_e('How players performed on this constructor\'s puzzles broken down by editor.', 'kealoa-reference'); ?></p>

                        <div class="kealoa-filter-controls" data-target="kealoa-person-con-editor-table">
                            <div class="kealoa-filter-row">
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pced-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                                    <input type="text" id="kealoa-pced-search" class="kealoa-filter-input" data-filter="search" data-col="0" placeholder="<?php esc_attr_e('Editor name...', 'kealoa-reference'); ?>">
                                </div>
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pced-min-guesses"><?php esc_html_e('Min. Guesses', 'kealoa-reference'); ?></label>
                                    <input type="number" id="kealoa-pced-min-guesses" class="kealoa-filter-input" data-filter="min" data-col="3" min="1" placeholder="<?php esc_attr_e('e.g. 5', 'kealoa-reference'); ?>">
                                </div>
                                <div class="kealoa-filter-group kealoa-filter-actions">
                                    <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                                    <span class="kealoa-filter-count"></span>
                                </div>
                            </div>
                        </div>

                        <div class="kealoa-table-scroll">
                        <table class="kealoa-table kealoa-constructor-editor-table" id="kealoa-person-con-editor-table">
                            <thead>
                                <tr>
                                    <th data-sort="text"><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
                                    <th data-sort="number" data-default-sort="desc"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Clues', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($constructor_editor_results as $cer): ?>
                                    <tr>
                                        <td><?php echo Kealoa_Formatter::format_editor_link((int) $cer->editor_id, $cer->editor_name); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((int) $cer->puzzle_count)); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((int) $cer->clue_count)); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((int) $cer->total_guesses)); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((int) $cer->correct_guesses)); ?></td>
                                        <?php
                                        $pct = $cer->total_guesses > 0
                                            ? ($cer->correct_guesses / $cer->total_guesses) * 100
                                            : 0;
                                        ?>
                                        <td data-value="<?php echo esc_attr(number_format((float) $pct, 2, '.', '')); ?>">
                                            <?php echo Kealoa_Formatter::format_percentage($pct); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- end constructor-results sub-tab -->

                </div><!-- end constructor secondary kealoa-tabs -->
                </div><!-- end Constructor primary tab -->
                <?php endif; ?><!-- end $is_constructor -->

                <?php if ($is_editor): ?>
                <div class="kealoa-tab-panel<?php echo $tab_active('editor'); ?>" data-tab="editor">

                <div class="kealoa-tabs">
                    <div class="kealoa-tab-nav">
                        <button class="kealoa-tab-button active" data-tab="editor-puzzles"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></button>
                        <button class="kealoa-tab-button" data-tab="editor-results"><?php esc_html_e('Results', 'kealoa-reference'); ?></button>
                        <button class="kealoa-tab-button" data-tab="editor-stats"><?php esc_html_e('Stats', 'kealoa-reference'); ?></button>
                    </div>

                <div class="kealoa-tab-panel" data-tab="editor-stats">

                    <?php if ($editor_stats): ?>
                    <div class="kealoa-person-editor-overview">
                        <h2><?php esc_html_e('Editor Statistics', 'kealoa-reference'); ?></h2>
                        <p class="kealoa-section-description"><?php esc_html_e('Summary of player performance on puzzles edited by this person.', 'kealoa-reference'); ?></p>
                        <div class="kealoa-stats-grid">
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $editor_stats->puzzle_count)); ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></span>
                            </div>
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $editor_stats->clue_count)); ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Clues', 'kealoa-reference'); ?></span>
                            </div>
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $editor_stats->player_count)); ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Players', 'kealoa-reference'); ?></span>
                            </div>
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $editor_stats->total_guesses)); ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></span>
                            </div>
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $editor_stats->correct_guesses)); ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Correct', 'kealoa-reference'); ?></span>
                            </div>
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php
                                    $ed_accuracy = (int) $editor_stats->total_guesses > 0
                                        ? ((int) $editor_stats->correct_guesses / (int) $editor_stats->total_guesses) * 100
                                        : 0;
                                    echo Kealoa_Formatter::format_percentage($ed_accuracy);
                                ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- end editor-stats sub-tab -->

                <div class="kealoa-tab-panel active" data-tab="editor-puzzles">

                    <?php if (!empty($editor_puzzles)): ?>
                    <div class="kealoa-editor-puzzles">
                        <h2><?php esc_html_e('Puzzles Edited', 'kealoa-reference'); ?></h2>
                        <p class="kealoa-section-description"><?php esc_html_e('All puzzles edited by this person that have been used as clues.', 'kealoa-reference'); ?></p>

                        <div class="kealoa-filter-controls" data-target="kealoa-person-ed-puzzles-table">
                            <div class="kealoa-filter-row">
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-ped-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                                    <input type="text" id="kealoa-ped-search" class="kealoa-filter-input" data-filter="search" data-col="1" placeholder="<?php esc_attr_e('Constructor name...', 'kealoa-reference'); ?>">
                                </div>
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-ped-day"><?php esc_html_e('Day', 'kealoa-reference'); ?></label>
                                    <select id="kealoa-ped-day" class="kealoa-filter-select" data-filter="exact" data-col="0">
                                        <option value=""><?php esc_html_e('All Days', 'kealoa-reference'); ?></option>
                                        <option value="Mon"><?php esc_html_e('Monday', 'kealoa-reference'); ?></option>
                                        <option value="Tue"><?php esc_html_e('Tuesday', 'kealoa-reference'); ?></option>
                                        <option value="Wed"><?php esc_html_e('Wednesday', 'kealoa-reference'); ?></option>
                                        <option value="Thu"><?php esc_html_e('Thursday', 'kealoa-reference'); ?></option>
                                        <option value="Fri"><?php esc_html_e('Friday', 'kealoa-reference'); ?></option>
                                        <option value="Sat"><?php esc_html_e('Saturday', 'kealoa-reference'); ?></option>
                                        <option value="Sun"><?php esc_html_e('Sunday', 'kealoa-reference'); ?></option>
                                    </select>
                                </div>
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-ped-search-words"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></label>
                                    <input type="text" id="kealoa-ped-search-words" class="kealoa-filter-input" data-filter="search" data-col="3" placeholder="<?php esc_attr_e('e.g. KEALOA', 'kealoa-reference'); ?>">
                                </div>
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-ped-date-from"><?php esc_html_e('Puzzle Date', 'kealoa-reference'); ?></label>
                                    <div class="kealoa-filter-range">
                                        <input type="date" id="kealoa-ped-date-from" class="kealoa-filter-input" data-filter="date-min" data-col="0">
                                        <span class="kealoa-filter-range-sep">&ndash;</span>
                                        <input type="date" id="kealoa-ped-date-to" class="kealoa-filter-input" data-filter="date-max" data-col="0">
                                    </div>
                                </div>
                                <div class="kealoa-filter-group kealoa-filter-actions">
                                    <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                                    <span class="kealoa-filter-count"></span>
                                </div>
                            </div>
                        </div>

                        <div class="kealoa-table-scroll">
                        <table class="kealoa-table kealoa-editor-puzzles-table" id="kealoa-person-ed-puzzles-table">
                            <thead>
                                <tr>
                                    <th data-sort="weekday"><?php esc_html_e('Day', 'kealoa-reference'); ?></th>
                                    <th data-sort="date"><?php esc_html_e('Puzzle Date', 'kealoa-reference'); ?></th>
                                    <th data-sort="text"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></th>
                                    <th data-sort="date"><?php esc_html_e('Round Date', 'kealoa-reference'); ?></th>
                                    <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($editor_puzzles as $epuzzle): ?>
                                    <?php
                                    $ep_con_ids = !empty($epuzzle->constructor_ids) ? explode(',', $epuzzle->constructor_ids) : [];
                                    $ep_con_names = !empty($epuzzle->constructor_names) ? explode(', ', $epuzzle->constructor_names) : [];
                                    $ep_round_ids = !empty($epuzzle->round_ids) ? explode(',', $epuzzle->round_ids) : [];
                                    $ep_round_dates = !empty($epuzzle->round_dates) ? explode(',', $epuzzle->round_dates) : [];
                                    $ep_round_numbers = !empty($epuzzle->round_numbers) ? explode(',', $epuzzle->round_numbers) : [];
                                    $ep_round_game_numbers = !empty($epuzzle->round_game_numbers) ? explode(',', $epuzzle->round_game_numbers) : [];
                                    ?>
                                    <tr>
                                        <td class="kealoa-day-cell"><?php echo esc_html(Kealoa_Formatter::format_day_abbrev($epuzzle->publication_date)); ?></td>
                                        <td>
                                            <?php echo Kealoa_Formatter::format_puzzle_date_link($epuzzle->publication_date); ?>
                                        </td>
                                        <td>
                                            <?php
                                            if (!empty($ep_con_ids)) {
                                                $ep_con_links = [];
                                                for ($i = 0; $i < count($ep_con_ids); $i++) {
                                                    $cid = (int) $ep_con_ids[$i];
                                                    $cname = $ep_con_names[$i] ?? '';
                                                    if ($cid && $cname) {
                                                        $ep_con_links[] = Kealoa_Formatter::format_constructor_link($cid, $cname);
                                                    }
                                                }
                                                echo Kealoa_Formatter::format_list_with_and($ep_con_links);
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if (!empty($ep_round_ids)) {
                                                $ep_round_links = [];
                                                for ($i = 0; $i < count($ep_round_ids); $i++) {
                                                    $rid = (int) $ep_round_ids[$i];
                                                    $rdate = $ep_round_dates[$i] ?? '';
                                                    $gn = (int) ($ep_round_game_numbers[$i] ?? $rid);
                                                    if ($rid && $rdate) {
                                                        $ep_round_links[] = Kealoa_Formatter::format_round_date_link($gn, $rdate);
                                                    }
                                                }
                                                echo implode('<br>', $ep_round_links);
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                        <td class="kealoa-solutions-cell">
                                            <?php
                                            if (!empty($ep_round_ids)) {
                                                $ep_sol_links = [];
                                                for ($i = 0; $i < count($ep_round_ids); $i++) {
                                                    $rid = (int) $ep_round_ids[$i];
                                                    if ($rid) {
                                                        $solutions = $bulk_solutions_map[$rid] ?? [];
                                                        $gn = (int) ($ep_round_game_numbers[$i] ?? $rid);
                                                        $ep_sol_links[] = Kealoa_Formatter::format_solution_words_link($gn, $solutions);
                                                    }
                                                }
                                                echo implode('<br>', $ep_sol_links);
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                        <?php
                                        $ep_stats = $bulk_puzzle_stats_map[(int) $epuzzle->puzzle_id] ?? (object) ['total_guesses' => 0, 'correct_guesses' => 0];
                                        $ep_total   = (int) $ep_stats->total_guesses;
                                        $ep_correct = (int) $ep_stats->correct_guesses;
                                        $ep_pct     = $ep_total > 0 ? ($ep_correct / $ep_total) * 100 : 0;
                                        ?>
                                        <td class="kealoa-clue-guesses"><?php echo esc_html(number_format_i18n($ep_total)); ?></td>
                                        <td class="kealoa-clue-correct"><?php echo esc_html(number_format_i18n($ep_correct)); ?></td>
                                        <td class="kealoa-clue-accuracy" data-value="<?php echo esc_attr(number_format((float) $ep_pct, 2, '.', '')); ?>">
                                            <?php echo Kealoa_Formatter::format_percentage($ep_pct); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- end editor-puzzles sub-tab -->

                <div class="kealoa-tab-panel" data-tab="editor-results">

                    <?php if (!empty($editor_player_results)): ?>
                    <div class="kealoa-editor-player-stats">
                        <h2><?php esc_html_e('Player Results', 'kealoa-reference'); ?></h2>
                        <p class="kealoa-section-description"><?php esc_html_e('How each player performed on puzzles edited by this person.', 'kealoa-reference'); ?></p>

                        <div class="kealoa-filter-controls" data-target="kealoa-person-ed-player-table">
                            <div class="kealoa-filter-row">
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pepl-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                                    <input type="text" id="kealoa-pepl-search" class="kealoa-filter-input" data-filter="search" data-col="0" placeholder="<?php esc_attr_e('Player name...', 'kealoa-reference'); ?>">
                                </div>
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pepl-min-guesses"><?php esc_html_e('Min. Guesses', 'kealoa-reference'); ?></label>
                                    <input type="number" id="kealoa-pepl-min-guesses" class="kealoa-filter-input" data-filter="min" data-col="1" min="1" placeholder="<?php esc_attr_e('e.g. 5', 'kealoa-reference'); ?>">
                                </div>
                                <div class="kealoa-filter-group kealoa-filter-actions">
                                    <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                                    <span class="kealoa-filter-count"></span>
                                </div>
                            </div>
                        </div>

                        <div class="kealoa-table-scroll">
                        <table class="kealoa-table kealoa-editor-player-table" id="kealoa-person-ed-player-table">
                            <thead>
                                <tr>
                                    <th data-sort="text"><?php esc_html_e('Player', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($editor_player_results as $epr): ?>
                                    <tr>
                                        <td><?php echo Kealoa_Formatter::format_person_link((int) $epr->person_id, $epr->full_name); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((int) $epr->total_answered)); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((int) $epr->correct_count)); ?></td>
                                        <?php
                                        $pct = $epr->total_answered > 0
                                            ? ($epr->correct_count / $epr->total_answered) * 100
                                            : 0;
                                        ?>
                                        <td data-value="<?php echo esc_attr(number_format((float) $pct, 2, '.', '')); ?>">
                                            <?php echo Kealoa_Formatter::format_percentage($pct); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($editor_constructor_results)): ?>
                    <div class="kealoa-editor-constructor-stats">
                        <h2><?php esc_html_e('Constructor Results', 'kealoa-reference'); ?></h2>
                        <p class="kealoa-section-description"><?php esc_html_e('How players performed on puzzles edited by this person broken down by constructor.', 'kealoa-reference'); ?></p>

                        <div class="kealoa-filter-controls" data-target="kealoa-person-ed-constructor-table">
                            <div class="kealoa-filter-row">
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pec-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                                    <input type="text" id="kealoa-pec-search" class="kealoa-filter-input" data-filter="search" data-col="0" placeholder="<?php esc_attr_e('Constructor name...', 'kealoa-reference'); ?>">
                                </div>
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pec-min-clues"><?php esc_html_e('Min. Clues', 'kealoa-reference'); ?></label>
                                    <input type="number" id="kealoa-pec-min-clues" class="kealoa-filter-input" data-filter="min" data-col="2" min="1" placeholder="<?php esc_attr_e('e.g. 5', 'kealoa-reference'); ?>">
                                </div>
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pec-acc-min"><?php esc_html_e('Accuracy Range', 'kealoa-reference'); ?></label>
                                    <div class="kealoa-filter-range">
                                        <input type="number" id="kealoa-pec-acc-min" class="kealoa-filter-input" data-filter="range-min" data-col="5" min="0" max="100" placeholder="<?php esc_attr_e('0%', 'kealoa-reference'); ?>">
                                        <span class="kealoa-filter-range-sep">&ndash;</span>
                                        <input type="number" id="kealoa-pec-acc-max" class="kealoa-filter-input" data-filter="range-max" data-col="5" min="0" max="100" placeholder="<?php esc_attr_e('100%', 'kealoa-reference'); ?>">
                                    </div>
                                </div>
                                <div class="kealoa-filter-group kealoa-filter-actions">
                                    <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                                    <span class="kealoa-filter-count"></span>
                                </div>
                            </div>
                        </div>

                        <div class="kealoa-table-scroll">
                        <table class="kealoa-table kealoa-editor-constructor-table" id="kealoa-person-ed-constructor-table">
                            <thead>
                                <tr>
                                    <th data-sort="text"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Clues', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($editor_constructor_results as $ecr): ?>
                                    <tr>
                                        <td><?php echo Kealoa_Formatter::format_constructor_link((int) $ecr->constructor_id, $ecr->full_name); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((int) $ecr->puzzle_count)); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((int) $ecr->clue_count)); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((int) $ecr->total_guesses)); ?></td>
                                        <td><?php echo esc_html(number_format_i18n((int) $ecr->correct_guesses)); ?></td>
                                        <?php
                                        $pct = $ecr->total_guesses > 0
                                            ? ($ecr->correct_guesses / $ecr->total_guesses) * 100
                                            : 0;
                                        ?>
                                        <td data-value="<?php echo esc_attr(number_format((float) $pct, 2, '.', '')); ?>">
                                            <?php echo Kealoa_Formatter::format_percentage($pct); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- end editor-results sub-tab -->

                </div><!-- end editor secondary kealoa-tabs -->
                </div><!-- end Editor primary tab -->
                <?php endif; ?><!-- end $is_editor -->

            </div><!-- end kealoa-tabs -->
        </div>
        <?php
        return ob_get_clean();

        }); // end get_cached_or_render
    }

    /**
     * Render persons (players) table shortcode
     *
     * [kealoa_persons_table]
     */
    public function render_persons_table(array $atts = []): string {
        return $this->get_cached_or_render('persons_table', function () {

        $persons = $this->db->get_persons_with_stats();

        if (empty($persons)) {
            return '<p class="kealoa-no-data">' . esc_html__('No players found.', 'kealoa-reference') . '</p>';
        }

        $scores_combined = $this->db->get_all_persons_highest_round_scores_with_round_ids();
        $streaks_combined = $this->db->get_all_persons_longest_streaks_with_round_ids();

        $highest_scores = [];
        $best_score_round_ids = [];
        foreach ($scores_combined as $pid => $data) {
            $highest_scores[$pid] = $data['score'];
            $best_score_round_ids[$pid] = $data['round_ids'];
        }

        $longest_streaks = [];
        $best_streak_round_ids = [];
        foreach ($streaks_combined as $pid => $data) {
            $longest_streaks[$pid] = $data['streak'];
            $best_streak_round_ids[$pid] = $data['round_ids'];
        }

        // Collect all unique round IDs needed for picker links
        $all_round_ids = [];
        foreach ($best_score_round_ids as $ids) {
            $all_round_ids = array_merge($all_round_ids, $ids);
        }
        foreach ($best_streak_round_ids as $ids) {
            $all_round_ids = array_merge($all_round_ids, $ids);
        }
        $all_round_ids = array_unique($all_round_ids);

        // Bulk-fetch rounds and solutions for all needed round IDs
        $bulk_rounds_map    = !empty($all_round_ids) ? $this->db->get_rounds_bulk($all_round_ids) : [];
        $bulk_solutions_map = !empty($all_round_ids) ? $this->db->get_round_solutions_bulk($all_round_ids) : [];

        // Build round info for all needed rounds
        $round_info = [];
        foreach ($all_round_ids as $rid) {
            $round = $bulk_rounds_map[(int) $rid] ?? null;
            if ($round) {
                $solutions = $bulk_solutions_map[(int) $rid] ?? [];
                $round_info[$rid] = [
                    'url' => home_url('/kealoa/round/' . (int) $round->game_number . '/'),
                    'date' => Kealoa_Formatter::format_date($round->round_date),
                    'words' => Kealoa_Formatter::format_solution_words($solutions),
                ];
            }
        }

        // Get per-person scores for all needed rounds
        $person_round_scores = $this->db->get_person_scores_for_rounds($all_round_ids);

        // Helper: build JSON for round picker from array of round_ids and a person_id
        $build_picker_json = function(array $round_ids, int $person_id) use ($round_info, $person_round_scores): string {
            $rounds = [];
            foreach ($round_ids as $rid) {
                if (isset($round_info[$rid])) {
                    $ri = $round_info[$rid];
                    $score_data = $person_round_scores[$person_id][$rid] ?? null;
                    $score = $score_data ? ($score_data['correct'] . '/' . $score_data['total']) : '';
                    $rounds[] = [
                        'url' => $ri['url'],
                        'date' => $ri['date'],
                        'words' => $ri['words'],
                        'score' => $score,
                    ];
                }
            }
            return esc_attr(wp_json_encode($rounds));
        };

        ob_start();
        ?>
        <div class="kealoa-persons-table-wrapper">
            <div class="kealoa-filter-controls" data-target="kealoa-persons-table">
                <div class="kealoa-filter-row">
                    <div class="kealoa-filter-group">
                        <label for="kealoa-ps-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                        <input type="text" id="kealoa-ps-search" class="kealoa-filter-input" data-filter="search" data-col="0" placeholder="<?php esc_attr_e('Player name...', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-ps-min-rounds"><?php esc_html_e('Min. Rounds', 'kealoa-reference'); ?></label>
                        <input type="number" id="kealoa-ps-min-rounds" class="kealoa-filter-input" data-filter="min" data-col="1" min="1" placeholder="<?php esc_attr_e('e.g. 3', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-ps-min-guesses"><?php esc_html_e('Min. Guesses', 'kealoa-reference'); ?></label>
                        <input type="number" id="kealoa-ps-min-guesses" class="kealoa-filter-input" data-filter="min" data-col="2" min="1" placeholder="<?php esc_attr_e('e.g. 10', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-ps-acc-min"><?php esc_html_e('Accuracy Range', 'kealoa-reference'); ?></label>
                        <div class="kealoa-filter-range">
                            <input type="number" id="kealoa-ps-acc-min" class="kealoa-filter-input" data-filter="range-min" data-col="4" min="0" max="100" placeholder="<?php esc_attr_e('0%', 'kealoa-reference'); ?>">
                            <span class="kealoa-filter-range-sep">&ndash;</span>
                            <input type="number" id="kealoa-ps-acc-max" class="kealoa-filter-input" data-filter="range-max" data-col="4" min="0" max="100" placeholder="<?php esc_attr_e('100%', 'kealoa-reference'); ?>">
                        </div>
                    </div>
                    <div class="kealoa-filter-group kealoa-filter-actions">
                        <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                        <span class="kealoa-filter-count"></span>
                    </div>
                </div>
            </div>

            <div class="kealoa-table-scroll">
            <table class="kealoa-table kealoa-persons-table" id="kealoa-persons-table">
                <thead>
                    <tr>
                        <th data-sort="text"><?php esc_html_e('Player', 'kealoa-reference'); ?></th>
                        <th data-sort="number" data-default-sort="desc"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Best Score', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Best Streak', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($persons as $person): ?>
                        <?php $pid = (int) $person->id; ?>
                        <tr>
                            <td>
                                <?php echo Kealoa_Formatter::format_person_link($pid, $person->full_name); ?>
                            </td>
                            <td><?php echo esc_html(number_format_i18n((int) $person->rounds_played)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $person->clues_guessed)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $person->correct_guesses)); ?></td>
                            <?php
                            $accuracy = $person->clues_guessed > 0
                                ? ($person->correct_guesses / $person->clues_guessed) * 100
                                : 0;
                            ?>
                            <td data-value="<?php echo esc_attr(number_format((float) $accuracy, 2, '.', '')); ?>">
                                <?php echo Kealoa_Formatter::format_percentage((float) $accuracy); ?>
                            </td>
                            <td><a class="kealoa-round-picker-link" data-rounds="<?php echo $build_picker_json($best_score_round_ids[$pid] ?? [], $pid); ?>"><?php echo esc_html($highest_scores[$pid] ?? 0); ?></a></td>
                            <td><a class="kealoa-round-picker-link" data-rounds="<?php echo $build_picker_json($best_streak_round_ids[$pid] ?? [], $pid); ?>"><?php echo esc_html($longest_streaks[$pid] ?? 0); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php
        return ob_get_clean();

        }); // end get_cached_or_render
    }


    /**
     * Render constructors table shortcode
     *
     * [kealoa_constructors_table]
     */
    public function render_constructors_table(array $atts = []): string {
        return $this->get_cached_or_render('constructors_table', function () {

        $constructors = $this->db->get_persons_who_are_constructors();

        if (empty($constructors)) {
            return '<p class="kealoa-no-data">' . esc_html__('No constructors found.', 'kealoa-reference') . '</p>';
        }

        $cycle_constructors = $this->db->get_constructors_who_hit_for_cycle();

        ob_start();
        ?>
        <div class="kealoa-constructors-table-wrapper">

            <div class="kealoa-tabs">
                <div class="kealoa-tab-nav">
                    <button class="kealoa-tab-button kealoa-tab-button--constructor active" data-tab="constructors"><?php esc_html_e('Constructors', 'kealoa-reference'); ?></button>
                    <button class="kealoa-tab-button kealoa-tab-button--constructor" data-tab="constructors-curiosities"><?php esc_html_e('Curiosities', 'kealoa-reference'); ?></button>
                </div>

                <div class="kealoa-tab-panel active" data-tab="constructors">

            <div class="kealoa-filter-controls" data-target="kealoa-constructors-table">
                <div class="kealoa-filter-row">
                    <div class="kealoa-filter-group">
                        <label for="kealoa-con-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                        <input type="text" id="kealoa-con-search" class="kealoa-filter-input" data-filter="search" data-col="0" placeholder="<?php esc_attr_e('Constructor name...', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-con-min-puzzles"><?php esc_html_e('Min. Puzzles', 'kealoa-reference'); ?></label>
                        <input type="number" id="kealoa-con-min-puzzles" class="kealoa-filter-input" data-filter="min" data-col="1" min="1" placeholder="<?php esc_attr_e('e.g. 3', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group kealoa-filter-actions">
                        <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                        <span class="kealoa-filter-count"></span>
                    </div>
                </div>
            </div>

            <div class="kealoa-table-scroll">
            <table class="kealoa-table kealoa-constructors-table" id="kealoa-constructors-table">
                <thead>
                    <tr>
                        <th data-sort="text"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Clues', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($constructors as $con): ?>
                        <tr>
                            <td>
                                <?php echo Kealoa_Formatter::format_constructor_link((int) $con->id, $con->full_name, 'constructor'); ?>
                            </td>
                            <td><?php echo esc_html(number_format_i18n((int) $con->puzzle_count)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $con->clue_count)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $con->total_guesses)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $con->correct_guesses)); ?></td>
                            <?php
                            $accuracy = $con->total_guesses > 0
                                ? ($con->correct_guesses / $con->total_guesses) * 100
                                : 0;
                            ?>
                            <td data-value="<?php echo esc_attr(number_format((float) $accuracy, 2, '.', '')); ?>">
                                <?php echo Kealoa_Formatter::format_percentage((float) $accuracy); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>

                </div><!-- end Constructors tab -->

                <div class="kealoa-tab-panel" data-tab="constructors-curiosities">
                <?php if (empty($cycle_constructors)): ?>
                    <p class="kealoa-no-data"><?php esc_html_e('No curiosities found yet.', 'kealoa-reference'); ?></p>
                <?php else: ?>
                    <div class="kealoa-curiosities-section">
                    <h3><?php esc_html_e('Hit for the Cycle', 'kealoa-reference'); ?></h3>
                    <p class="kealoa-section-description"><?php esc_html_e('Constructors whose puzzles have been used in rounds on all seven days of the week.', 'kealoa-reference'); ?></p>
                    <div class="kealoa-table-scroll">
                        <table class="kealoa-table" id="kealoa-constructors-cycle-table">
                            <thead>
                                <tr>
                                    <th data-sort="text"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></th>
                                    <th data-sort="number" data-default-sort="desc"><?php esc_html_e('Cycles', 'kealoa-reference'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cycle_constructors as $con): ?>
                                <tr>
                                    <td><?php echo Kealoa_Formatter::format_constructor_link((int) $con->id, $con->full_name, 'constructor'); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $con->cycle_count)); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    </div>
                <?php endif; ?>
                </div><!-- end Curiosities tab -->

            </div><!-- end kealoa-tabs -->

        </div>
        <?php
        return ob_get_clean();

        }); // end get_cached_or_render
    }

    /**
     * Render editors table shortcode
     *
     * [kealoa_editors_table]
     */
    public function render_editors_table(array $atts = []): string {
        return $this->get_cached_or_render('editors_table', function () {

        $editors = $this->db->get_persons_who_are_editors();

        if (empty($editors)) {
            return '<p class="kealoa-no-data">' . esc_html__('No editors found.', 'kealoa-reference') . '</p>';
        }

        $cycle_editors = $this->db->get_editors_who_hit_for_cycle();

        ob_start();
        ?>
        <div class="kealoa-editors-table-wrapper">

            <div class="kealoa-tabs">
                <div class="kealoa-tab-nav">
                    <button class="kealoa-tab-button kealoa-tab-button--editor active" data-tab="editors"><?php esc_html_e('Editors', 'kealoa-reference'); ?></button>
                    <button class="kealoa-tab-button kealoa-tab-button--editor" data-tab="editors-curiosities"><?php esc_html_e('Curiosities', 'kealoa-reference'); ?></button>
                </div>

                <div class="kealoa-tab-panel active" data-tab="editors">

            <div class="kealoa-filter-controls" data-target="kealoa-editors-table">
                <div class="kealoa-filter-row">
                    <div class="kealoa-filter-group">
                        <label for="kealoa-ed-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                        <input type="text" id="kealoa-ed-search" class="kealoa-filter-input" data-filter="search" data-col="0" placeholder="<?php esc_attr_e('Editor name...', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group kealoa-filter-actions">
                        <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                        <span class="kealoa-filter-count"></span>
                    </div>
                </div>
            </div>

            <div class="kealoa-table-scroll">
            <table class="kealoa-table kealoa-editors-table" id="kealoa-editors-table">
                <thead>
                    <tr>
                        <th data-sort="text"><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
                        <th data-sort="number" data-default-sort="desc"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Clues', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($editors as $ed): ?>
                        <tr>
                            <td>
                                <?php echo Kealoa_Formatter::format_editor_link((int) $ed->id, $ed->editor_name, 'editor'); ?>
                            </td>
                            <td><?php echo esc_html(number_format_i18n((int) $ed->puzzle_count)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $ed->clue_count)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $ed->total_guesses)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $ed->correct_guesses)); ?></td>
                            <?php
                            $accuracy = $ed->total_guesses > 0
                                ? ($ed->correct_guesses / $ed->total_guesses) * 100
                                : 0;
                            ?>
                            <td data-value="<?php echo esc_attr(number_format((float) $accuracy, 2, '.', '')); ?>">
                                <?php echo Kealoa_Formatter::format_percentage((float) $accuracy); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>

                </div><!-- end Editors tab -->

                <div class="kealoa-tab-panel" data-tab="editors-curiosities">
                <?php if (empty($cycle_editors)): ?>
                    <p class="kealoa-no-data"><?php esc_html_e('No curiosities found yet.', 'kealoa-reference'); ?></p>
                <?php else: ?>
                    <div class="kealoa-curiosities-section">
                    <h3><?php esc_html_e('Hit for the Cycle', 'kealoa-reference'); ?></h3>
                    <p class="kealoa-section-description"><?php esc_html_e('Editors whose puzzles have been used in rounds on all seven days of the week.', 'kealoa-reference'); ?></p>
                    <div class="kealoa-table-scroll">
                        <table class="kealoa-table" id="kealoa-editors-cycle-table">
                            <thead>
                                <tr>
                                    <th data-sort="text"><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
                                    <th data-sort="number" data-default-sort="desc"><?php esc_html_e('Cycles', 'kealoa-reference'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cycle_editors as $ed): ?>
                                <tr>
                                    <td><?php echo Kealoa_Formatter::format_editor_link((int) $ed->id, $ed->full_name, 'editor'); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $ed->cycle_count)); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    </div>
                <?php endif; ?>
                </div><!-- end Curiosities tab -->

            </div><!-- end kealoa-tabs -->

        </div>
        <?php
        return ob_get_clean();

        }); // end get_cached_or_render
    }

    /**
     * Render clue givers table shortcode
     *
     * [kealoa_clue_givers_table]
     */
    public function render_clue_givers_table(array $atts = []): string {
        return $this->get_cached_or_render('clue_givers_table', function () {

        $clue_givers = $this->db->get_persons_who_are_clue_givers();

        if (empty($clue_givers)) {
            return '<p class="kealoa-no-data">' . esc_html__('No hosts found.', 'kealoa-reference') . '</p>';
        }

        ob_start();
        ?>
        <div class="kealoa-clue-givers-table-wrapper">
            <div class="kealoa-filter-controls" data-target="kealoa-clue-givers-table">
                <div class="kealoa-filter-row">
                    <div class="kealoa-filter-group">
                        <label for="kealoa-cg-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                        <input type="text" id="kealoa-cg-search" class="kealoa-filter-input" data-filter="search" data-col="0" placeholder="<?php esc_attr_e('Host name...', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group kealoa-filter-actions">
                        <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                        <span class="kealoa-filter-count"></span>
                    </div>
                </div>
            </div>

            <div class="kealoa-table-scroll">
            <table class="kealoa-table kealoa-clue-givers-table" id="kealoa-clue-givers-table">
                <thead>
                    <tr>
                        <th data-sort="text"><?php esc_html_e('Host', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Clues', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Players', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clue_givers as $cg): ?>
                        <tr>
                            <td>
                                <?php echo Kealoa_Formatter::format_person_link((int) $cg->id, $cg->clue_giver_name, 'host'); ?>
                            </td>
                            <td><?php echo esc_html(number_format_i18n((int) $cg->round_count)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $cg->clue_count)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $cg->guesser_count)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $cg->total_guesses)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $cg->correct_guesses)); ?></td>
                            <?php
                            $accuracy = $cg->total_guesses > 0
                                ? ($cg->correct_guesses / $cg->total_guesses) * 100
                                : 0;
                            ?>
                            <td data-value="<?php echo esc_attr(number_format((float) $accuracy, 2, '.', '')); ?>">
                                <?php echo Kealoa_Formatter::format_percentage((float) $accuracy); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php
        return ob_get_clean();

        }); // end get_cached_or_render
    }

    /**
     * Render hosts table shortcode (alias for clue givers table)
     *
     * [kealoa_hosts_table]
     */
    public function render_hosts_table(array $atts = []): string {
        return $this->render_clue_givers_table($atts);
    }

    /**
     * Render puzzles table shortcode
     *
     * [kealoa_puzzles_table]
     */
    public function render_puzzles_table(array $atts = []): string {
        return $this->get_cached_or_render('puzzles_table', function () {

        $puzzles = $this->db->get_all_puzzles_with_details();
        $multi_round_puzzles = $this->db->get_puzzles_used_in_multiple_rounds();
        $same_constructor_editor_puzzles = $this->db->get_puzzles_same_constructor_editor();

        ob_start();
        ?>
        <div class="kealoa-puzzles-page">
        <div class="kealoa-tabs">
            <nav class="kealoa-tab-nav" role="tablist">
                <button class="kealoa-tab-button active" role="tab" aria-selected="true" data-tab="kealoa-puzzles-tab-all"><?php esc_html_e('All Puzzles', 'kealoa-reference'); ?></button>
                <button class="kealoa-tab-button" role="tab" aria-selected="false" data-tab="kealoa-puzzles-tab-curiosities"><?php esc_html_e('Curiosities', 'kealoa-reference'); ?></button>
            </nav>
            <div class="kealoa-tab-panel active" id="kealoa-puzzles-tab-all" data-tab="kealoa-puzzles-tab-all" role="tabpanel">
        <?php if (empty($puzzles)): ?>
            <p class="kealoa-no-data"><?php esc_html_e('No puzzles found.', 'kealoa-reference'); ?></p>
        <?php else: ?>
        <?php
        // Bulk pre-fetch solutions for all round IDs across all puzzles
        $all_puzzle_round_ids = [];
        foreach ($puzzles as $puzzle) {
            if (!empty($puzzle->round_ids)) {
                foreach (explode(',', $puzzle->round_ids) as $rid) {
                    $rid = (int) $rid;
                    if ($rid) {
                        $all_puzzle_round_ids[] = $rid;
                    }
                }
            }
        }
        $all_puzzle_round_ids = array_unique($all_puzzle_round_ids);
        $bulk_puzzle_solutions_map = !empty($all_puzzle_round_ids) ? $this->db->get_round_solutions_bulk($all_puzzle_round_ids) : [];
        ?>
        <div class="kealoa-puzzles-table-wrapper">
            <div class="kealoa-filter-controls" data-target="kealoa-puzzles-table">
                <div class="kealoa-filter-row">
                    <div class="kealoa-filter-group">
                        <label for="kealoa-pz-search"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></label>
                        <input type="text" id="kealoa-pz-search" class="kealoa-filter-input" data-filter="search" data-col="2" placeholder="<?php esc_attr_e('Constructor name...', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-pz-editor"><?php esc_html_e('Editor', 'kealoa-reference'); ?></label>
                        <input type="text" id="kealoa-pz-editor" class="kealoa-filter-input" data-filter="search" data-col="3" placeholder="<?php esc_attr_e('Editor name...', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-pz-day"><?php esc_html_e('Day', 'kealoa-reference'); ?></label>
                        <select id="kealoa-pz-day" class="kealoa-filter-select" data-filter="exact" data-col="0">
                            <option value=""><?php esc_html_e('All Days', 'kealoa-reference'); ?></option>
                            <option value="Mon"><?php esc_html_e('Monday', 'kealoa-reference'); ?></option>
                            <option value="Tue"><?php esc_html_e('Tuesday', 'kealoa-reference'); ?></option>
                            <option value="Wed"><?php esc_html_e('Wednesday', 'kealoa-reference'); ?></option>
                            <option value="Thu"><?php esc_html_e('Thursday', 'kealoa-reference'); ?></option>
                            <option value="Fri"><?php esc_html_e('Friday', 'kealoa-reference'); ?></option>
                            <option value="Sat"><?php esc_html_e('Saturday', 'kealoa-reference'); ?></option>
                            <option value="Sun"><?php esc_html_e('Sunday', 'kealoa-reference'); ?></option>
                        </select>
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-pz-date-from"><?php esc_html_e('Puzzle Date', 'kealoa-reference'); ?></label>
                        <div class="kealoa-filter-range">
                            <input type="date" id="kealoa-pz-date-from" class="kealoa-filter-input" data-filter="date-min" data-col="1">
                            <span class="kealoa-filter-range-sep">&ndash;</span>
                            <input type="date" id="kealoa-pz-date-to" class="kealoa-filter-input" data-filter="date-max" data-col="1">
                        </div>
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-pz-search-words"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></label>
                        <input type="text" id="kealoa-pz-search-words" class="kealoa-filter-input" data-filter="search" data-col="5" placeholder="<?php esc_attr_e('e.g. KEALOA', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group kealoa-filter-actions">
                        <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                        <span class="kealoa-filter-count"></span>
                    </div>
                </div>
            </div>

            <div class="kealoa-table-scroll">
            <table class="kealoa-table kealoa-puzzles-table" id="kealoa-puzzles-table">
                <thead>
                    <tr>
                        <th data-sort="weekday"><?php esc_html_e('Day', 'kealoa-reference'); ?></th>
                        <th data-sort="date"><?php esc_html_e('Puzzle Date', 'kealoa-reference'); ?></th>
                        <th data-sort="text"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></th>
                        <th data-sort="text"><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
                        <th data-sort="date"><?php esc_html_e('Round Date', 'kealoa-reference'); ?></th>
                        <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($puzzles as $puzzle): ?>
                        <?php
                        $constructor_ids = !empty($puzzle->constructor_ids) ? explode(',', $puzzle->constructor_ids) : [];
                        $constructor_names = !empty($puzzle->constructor_names) ? explode(', ', $puzzle->constructor_names) : [];
                        $round_ids = !empty($puzzle->round_ids) ? explode(',', $puzzle->round_ids) : [];
                        $round_dates = !empty($puzzle->round_dates) ? explode(',', $puzzle->round_dates) : [];
                        $round_numbers = !empty($puzzle->round_numbers) ? explode(',', $puzzle->round_numbers) : [];
                        $round_game_numbers = !empty($puzzle->round_game_numbers) ? explode(',', $puzzle->round_game_numbers) : [];
                        ?>
                        <tr>
                            <td class="kealoa-day-cell"><?php echo esc_html(Kealoa_Formatter::format_day_abbrev($puzzle->publication_date)); ?></td>
                            <td>
                                <?php echo Kealoa_Formatter::format_puzzle_date_link($puzzle->publication_date); ?>
                            </td>
                            <td>
                                <?php
                                if (!empty($constructor_ids)) {
                                    $links = [];
                                    for ($i = 0; $i < count($constructor_ids); $i++) {
                                        $cid = (int) $constructor_ids[$i];
                                        $cname = $constructor_names[$i] ?? '';
                                        if ($cid && $cname) {
                                            $links[] = Kealoa_Formatter::format_constructor_link($cid, $cname);
                                        }
                                    }
                                    echo Kealoa_Formatter::format_list_with_and($links);
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if (!empty($puzzle->editor_id)) {
                                    echo Kealoa_Formatter::format_editor_link((int) $puzzle->editor_id, $puzzle->editor_name);
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if (!empty($round_ids)) {
                                    $round_links = [];
                                    for ($i = 0; $i < count($round_ids); $i++) {
                                        $rid = (int) $round_ids[$i];
                                        $rdate = $round_dates[$i] ?? '';
                                        $gn = (int) ($round_game_numbers[$i] ?? $rid);
                                        if ($rid && $rdate) {
                                            $round_links[] = Kealoa_Formatter::format_round_date_link($gn, $rdate);
                                        }
                                    }
                                    echo implode('<br>', $round_links);
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td class="kealoa-solutions-cell">
                                <?php
                                if (!empty($round_ids)) {
                                    $solution_links = [];
                                    for ($i = 0; $i < count($round_ids); $i++) {
                                        $rid = (int) $round_ids[$i];
                                        if ($rid) {
                                            $solutions = $bulk_puzzle_solutions_map[$rid] ?? [];
                                            $gn = (int) ($round_game_numbers[$i] ?? $rid);
                                            $solution_links[] = Kealoa_Formatter::format_solution_words_link($gn, $solutions);
                                        }
                                    }
                                    echo implode('<br>', $solution_links);
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
        <?php endif; ?>
            </div><!-- end kealoa-puzzles-tab-all -->
            <div class="kealoa-tab-panel" id="kealoa-puzzles-tab-curiosities" data-tab="kealoa-puzzles-tab-curiosities" role="tabpanel">
                <?php $this->render_curiosities_panel($multi_round_puzzles, $same_constructor_editor_puzzles); ?>
            </div><!-- end kealoa-puzzles-tab-curiosities -->
        </div><!-- end kealoa-tabs -->
        </div><!-- end kealoa-puzzles-page -->
        <?php
        return ob_get_clean();

        }); // end get_cached_or_render
    }

    /**
     * Render the Curiosities tab panel — stacked sections.
     *
     * @param array $multi_round_puzzles              Puzzles used in more than one round.
     * @param array $same_constructor_editor_puzzles   Puzzles where constructor = editor.
     */
    private function render_curiosities_panel(array $multi_round_puzzles, array $same_constructor_editor_puzzles): void {
        if (empty($multi_round_puzzles) && empty($same_constructor_editor_puzzles)): ?>
            <p class="kealoa-no-data"><?php esc_html_e('No curiosities found yet.', 'kealoa-reference'); ?></p>
        <?php return; endif;

        if (!empty($multi_round_puzzles)):

        // Pre-fetch solutions and per-date round counts for all rounds across all puzzles
        $cur_all_rids = [];
        foreach ($multi_round_puzzles as $puzzle) {
            if (!empty($puzzle->round_ids)) {
                foreach (explode(',', $puzzle->round_ids) as $rid) {
                    $rid = (int) $rid;
                    if ($rid) {
                        $cur_all_rids[] = $rid;
                    }
                }
            }
        }
        $cur_all_rids = array_unique($cur_all_rids);
        $cur_solutions_cache = !empty($cur_all_rids) ? $this->db->get_round_solutions_bulk($cur_all_rids) : [];
        $cur_rounds_per_date = $this->db->get_rounds_per_date_counts();
        ?>
        <div class="kealoa-curiosities-section">
        <h3><?php esc_html_e('Puzzles Used in Multiple Rounds', 'kealoa-reference'); ?></h3>
        <p class="kealoa-section-description"><?php esc_html_e('These puzzles were featured as clues in more than one round.', 'kealoa-reference'); ?></p>
        <div class="kealoa-puzzles-table-wrapper">
            <div class="kealoa-table-scroll">
                <table class="kealoa-table kealoa-puzzles-table" id="kealoa-puzzles-curiosities-table">
                    <thead>
                        <tr>
                            <th data-sort="weekday"><?php esc_html_e('Day', 'kealoa-reference'); ?></th>
                            <th data-sort="date"><?php esc_html_e('Puzzle Date', 'kealoa-reference'); ?></th>
                            <th data-sort="text"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></th>
                            <th data-sort="text"><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
                            <th data-sort="date"><?php esc_html_e('Round Date', 'kealoa-reference'); ?></th>
                            <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($multi_round_puzzles as $puzzle): ?>
                            <?php
                            $constructor_ids   = !empty($puzzle->constructor_ids)   ? explode(',', $puzzle->constructor_ids)   : [];
                            $constructor_names = !empty($puzzle->constructor_names) ? explode(', ', $puzzle->constructor_names) : [];
                            $round_ids         = !empty($puzzle->round_ids)         ? explode(',', $puzzle->round_ids)         : [];
                            $round_dates       = !empty($puzzle->round_dates)       ? explode(',', $puzzle->round_dates)       : [];
                            $round_numbers     = !empty($puzzle->round_numbers)     ? explode(',', $puzzle->round_numbers)     : [];
                            $round_game_numbers = !empty($puzzle->round_game_numbers) ? explode(',', $puzzle->round_game_numbers) : [];

                            $round_words_parts = [];
                            $round_date_parts  = [];
                            foreach ($round_ids as $idx => $rid) {
                                $rid    = (int) $rid;
                                $rdate  = $round_dates[$idx]  ?? '';
                                $rnum   = $round_numbers[$idx] ?? '';
                                $gn     = (int) ($round_game_numbers[$idx] ?? $rid);
                                $round_words_parts[] = isset($cur_solutions_cache[$rid])
                                    ? Kealoa_Formatter::format_solution_words_link($gn, $cur_solutions_cache[$rid])
                                    : '';
                                $date_cell = Kealoa_Formatter::format_round_date_link($gn, $rdate);
                                if (!empty($rnum) && ($cur_rounds_per_date[$rdate] ?? 1) > 1) {
                                    $date_cell .= ' <span class="kealoa-round-number">(#' . esc_html($rnum) . ')</span>';
                                }
                                $round_date_parts[] = $date_cell;
                            }
                            ?>
                            <tr>
                                <td class="kealoa-day-cell"><?php echo esc_html(Kealoa_Formatter::format_day_abbrev($puzzle->publication_date)); ?></td>
                                <td><?php echo Kealoa_Formatter::format_puzzle_date_link($puzzle->publication_date); ?></td>
                                <td>
                                    <?php
                                    if (!empty($constructor_ids)) {
                                        $links = [];
                                        for ($i = 0; $i < count($constructor_ids); $i++) {
                                            $cid   = (int) $constructor_ids[$i];
                                            $cname = $constructor_names[$i] ?? '';
                                            if ($cid && $cname) {
                                                $links[] = Kealoa_Formatter::format_constructor_link($cid, $cname);
                                            }
                                        }
                                        echo Kealoa_Formatter::format_list_with_and($links);
                                    } else {
                                        echo '&mdash;';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if (!empty($puzzle->editor_id)) {
                                        echo Kealoa_Formatter::format_editor_link((int) $puzzle->editor_id, $puzzle->editor_name);
                                    } else {
                                        echo '&mdash;';
                                    }
                                    ?>
                                </td>
                                <td class="kealoa-round-date"><?php echo implode('<br>', $round_date_parts); ?></td>
                                <td class="kealoa-round-words"><?php echo implode('<br>', $round_words_parts); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>

        <?php endif; // end if (!empty($multi_round_puzzles)) ?>

        <?php if (!empty($same_constructor_editor_puzzles)): ?>
        <?php
        // Pre-fetch solutions for all rounds across same-constructor-editor puzzles
        $sce_all_rids = [];
        foreach ($same_constructor_editor_puzzles as $sce_puzzle) {
            if (!empty($sce_puzzle->round_ids)) {
                foreach (explode(',', $sce_puzzle->round_ids) as $rid) {
                    $rid = (int) $rid;
                    if ($rid) {
                        $sce_all_rids[] = $rid;
                    }
                }
            }
        }
        $sce_all_rids = array_unique($sce_all_rids);
        $sce_solutions_cache = !empty($sce_all_rids) ? $this->db->get_round_solutions_bulk($sce_all_rids) : [];
        $sce_rounds_per_date = $this->db->get_rounds_per_date_counts();
        ?>
        <div class="kealoa-curiosities-section">
        <h3><?php esc_html_e('Same Constructor & Editor', 'kealoa-reference'); ?></h3>
        <p class="kealoa-section-description"><?php esc_html_e('These puzzles have the same person listed as both constructor and editor.', 'kealoa-reference'); ?></p>
        <div class="kealoa-puzzles-table-wrapper">
            <div class="kealoa-table-scroll">
                <table class="kealoa-table kealoa-puzzles-table" id="kealoa-puzzles-curiosities-same-ce-table">
                    <thead>
                        <tr>
                            <th data-sort="weekday"><?php esc_html_e('Day', 'kealoa-reference'); ?></th>
                            <th data-sort="date"><?php esc_html_e('Puzzle Date', 'kealoa-reference'); ?></th>
                            <th data-sort="text"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></th>
                            <th data-sort="text"><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
                            <th data-sort="date"><?php esc_html_e('Round Date', 'kealoa-reference'); ?></th>
                            <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($same_constructor_editor_puzzles as $sce_puzzle): ?>
                            <?php
                            $constructor_ids   = !empty($sce_puzzle->constructor_ids)   ? explode(',', $sce_puzzle->constructor_ids)   : [];
                            $constructor_names = !empty($sce_puzzle->constructor_names) ? explode(', ', $sce_puzzle->constructor_names) : [];
                            $round_ids         = !empty($sce_puzzle->round_ids)         ? explode(',', $sce_puzzle->round_ids)         : [];
                            $round_dates       = !empty($sce_puzzle->round_dates)       ? explode(',', $sce_puzzle->round_dates)       : [];
                            $round_numbers     = !empty($sce_puzzle->round_numbers)     ? explode(',', $sce_puzzle->round_numbers)     : [];
                            $round_game_numbers = !empty($sce_puzzle->round_game_numbers) ? explode(',', $sce_puzzle->round_game_numbers) : [];

                            $round_words_parts = [];
                            $round_date_parts  = [];
                            foreach ($round_ids as $idx => $rid) {
                                $rid    = (int) $rid;
                                $rdate  = $round_dates[$idx]  ?? '';
                                $rnum   = $round_numbers[$idx] ?? '';
                                $gn     = (int) ($round_game_numbers[$idx] ?? $rid);
                                $round_words_parts[] = isset($sce_solutions_cache[$rid])
                                    ? Kealoa_Formatter::format_solution_words_link($gn, $sce_solutions_cache[$rid])
                                    : '';
                                $date_cell = Kealoa_Formatter::format_round_date_link($gn, $rdate);
                                if (!empty($rnum) && ($sce_rounds_per_date[$rdate] ?? 1) > 1) {
                                    $date_cell .= ' <span class="kealoa-round-number">(#' . esc_html($rnum) . ')</span>';
                                }
                                $round_date_parts[] = $date_cell;
                            }
                            ?>
                            <tr>
                                <td class="kealoa-day-cell"><?php echo esc_html(Kealoa_Formatter::format_day_abbrev($sce_puzzle->publication_date)); ?></td>
                                <td><?php echo Kealoa_Formatter::format_puzzle_date_link($sce_puzzle->publication_date); ?></td>
                                <td>
                                    <?php
                                    if (!empty($constructor_ids)) {
                                        $links = [];
                                        for ($i = 0; $i < count($constructor_ids); $i++) {
                                            $cid   = (int) $constructor_ids[$i];
                                            $cname = $constructor_names[$i] ?? '';
                                            if ($cid && $cname) {
                                                $links[] = Kealoa_Formatter::format_constructor_link($cid, $cname);
                                            }
                                        }
                                        echo Kealoa_Formatter::format_list_with_and($links);
                                    } else {
                                        echo '&mdash;';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if (!empty($sce_puzzle->editor_id)) {
                                        echo Kealoa_Formatter::format_editor_link((int) $sce_puzzle->editor_id, $sce_puzzle->editor_name);
                                    } else {
                                        echo '&mdash;';
                                    }
                                    ?>
                                </td>
                                <td class="kealoa-round-date"><?php echo !empty($round_date_parts) ? implode('<br>', $round_date_parts) : '&mdash;'; ?></td>
                                <td class="kealoa-round-words"><?php echo !empty($round_words_parts) ? implode('<br>', $round_words_parts) : '&mdash;'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
        <?php endif; // end if (!empty($same_constructor_editor_puzzles)) ?>
        <?php
    }

    /**
     * Render single puzzle view shortcode
     *
     * [kealoa_puzzle date="2024-01-15"]
     */
    public function render_puzzle(array $atts = []): string {
        $atts = shortcode_atts([
            'date' => '',
        ], $atts, 'kealoa_puzzle');

        $puzzle_date = sanitize_text_field($atts['date']);
        if (empty($puzzle_date)) {
            return '<p class="kealoa-error">' . esc_html__('Please specify a puzzle date.', 'kealoa-reference') . '</p>';
        }

        $puzzle = $this->db->get_puzzle_by_date($puzzle_date);
        if (!$puzzle) {
            return '<p class="kealoa-error">' . esc_html__('Puzzle not found.', 'kealoa-reference') . '</p>';
        }

        $puzzle_id = (int) $puzzle->id;

        return $this->get_cached_or_render('puzzle_' . $puzzle_id, function () use ($puzzle_id, $puzzle) {

        $constructors = $this->db->get_puzzle_constructors($puzzle_id);
        $clues = $this->db->get_puzzle_clues($puzzle_id);
        $player_results = $this->db->get_puzzle_player_results($puzzle_id);

        // Look up editor person record
        $editor_person = null;
        $editor_name = '';
        if (!empty($puzzle->editor_id)) {
            $editor_person = $this->db->get_person((int) $puzzle->editor_id);
            $editor_name = $editor_person ? $editor_person->full_name : '';
        }

        // Determine editor image: media library > XWordInfo
        $editor_image_url = '';
        $editor_image_source = '';
        if ($editor_person) {
            $editor_media_id = (int) ($editor_person->media_id ?? 0);
            if ($editor_media_id > 0) {
                $media_src = wp_get_attachment_image_src($editor_media_id, 'medium');
                if ($media_src) {
                    $editor_image_url = $media_src[0];
                    $editor_image_source = 'media';
                }
            }
            if (empty($editor_image_source)) {
                $editor_image_url = $editor_person->xwordinfo_image_url ?? '';
                $editor_image_source = 'xwordinfo';
            }
        }

        // Group clues by round
        $rounds_clues = [];
        foreach ($clues as $clue) {
            $rid = (int) $clue->round_id;
            if (!isset($rounds_clues[$rid])) {
                $rounds_clues[$rid] = [
                    'round_id' => $rid,
                    'round_date' => $clue->round_date,
                    'round_number' => $clue->round_number,
                    'episode_number' => $clue->episode_number,
                    'clues' => [],
                ];
            }
            $rounds_clues[$rid]['clues'][] = $clue;
        }

        // Pre-fetch solutions for each round and count of rounds per date
        $round_rid_list = array_keys($rounds_clues);
        $round_solutions_cache = !empty($round_rid_list) ? $this->db->get_round_solutions_bulk($round_rid_list) : [];
        $rounds_per_date_cache = $this->db->get_rounds_per_date_counts();

        // Pre-fetch guess stats per clue position
        $clue_stats_cache = $this->db->get_puzzle_clue_stats($puzzle_id);

        // Group clues by crossword position (puzzle_clue_number + direction)
        $clues_by_position = [];
        foreach ($clues as $clue) {
            $pos_key = $clue->puzzle_clue_number . '_' . $clue->puzzle_clue_direction;
            if (!isset($clues_by_position[$pos_key])) {
                $clues_by_position[$pos_key] = [
                    'puzzle_clue_number'    => (int) $clue->puzzle_clue_number,
                    'puzzle_clue_direction' => $clue->puzzle_clue_direction,
                    'clue_text'             => $clue->clue_text,
                    'correct_answer'        => $clue->correct_answer,
                    'rounds'                => [],
                ];
            }
            $clues_by_position[$pos_key]['rounds'][] = [
                'round_id'          => (int) $clue->round_id,
                'round_date'        => $clue->round_date,
                'round_number'      => $clue->round_number,
                'round_game_number' => (int) $clue->round_game_number,
            ];
        }
        // Sort by crossword number ASC, then direction ASC (A before D)
        usort($clues_by_position, function ($a, $b) {
            if ($a['puzzle_clue_number'] !== $b['puzzle_clue_number']) {
                return $a['puzzle_clue_number'] - $b['puzzle_clue_number'];
            }
            return strcmp($a['puzzle_clue_direction'], $b['puzzle_clue_direction']);
        });

        ob_start();
        ?>
        <div class="kealoa-puzzle-view">
            <div class="kealoa-puzzle-header">
                <div class="kealoa-puzzle-info">
                    <div class="kealoa-puzzle-details">

                        <p>
                            <strong class="kealoa-meta-label"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></strong>
                            <span>
                                <?php
                                if (!empty($constructors)) {
                                    $links = array_map(function($c) {
                                        return Kealoa_Formatter::format_constructor_link((int) $c->id, $c->full_name);
                                    }, $constructors);
                                    echo Kealoa_Formatter::format_list_with_and($links);
                                } else {
                                    echo '—';
                                }
                                ?>
                            </span>
                        </p>

                        <?php if (!empty($editor_name)): ?>
                        <p>
                            <strong class="kealoa-meta-label"><?php esc_html_e('Editor', 'kealoa-reference'); ?></strong>
                            <span><?php echo Kealoa_Formatter::format_editor_link((int) $puzzle->editor_id, $editor_name); ?></span>
                        </p>
                        <?php endif; ?>

                        <p>
                            <strong class="kealoa-meta-label"><?php esc_html_e('XWordInfo', 'kealoa-reference'); ?></strong>
                            <span><?php echo Kealoa_Formatter::format_puzzle_xwordinfo_link($puzzle->publication_date); ?></span>
                        </p>
                    </div>
                </div>

                <?php if (!empty($constructors)): ?>
                <div class="kealoa-puzzle-constructors-images">
                    <?php foreach ($constructors as $con): ?>
                        <?php
                        $con_media_id = (int) ($con->media_id ?? 0);
                        $con_image_url = '';
                        $con_image_source = '';

                        if ($con_media_id > 0) {
                            $media_src = wp_get_attachment_image_src($con_media_id, 'medium');
                            if ($media_src) {
                                $con_image_url = $media_src[0];
                                $con_image_source = 'media';
                            }
                        }

                        if (empty($con_image_source)) {
                            $con_image_url = $con->xwordinfo_image_url ?? '';
                            $con_image_source = 'xwordinfo';
                        }

                        $con_slug = str_replace(' ', '_', $con->full_name);
                        $con_url = home_url('/kealoa/person/' . urlencode($con_slug) . '/') . '#kealoa-tab=constructor';
                        ?>
                        <div class="kealoa-puzzle-constructor">
                            <a href="<?php echo esc_url($con_url); ?>">
                                <?php if ($con_image_source === 'media'): ?>
                                    <img src="<?php echo esc_url($con_image_url); ?>"
                                         alt="<?php echo esc_attr($con->full_name); ?>"
                                         class="kealoa-entity-image" />
                                <?php else: ?>
                                    <?php echo Kealoa_Formatter::format_xwordinfo_image($con_image_url, $con->full_name); ?>
                                <?php endif; ?>
                            </a>
                            <span class="kealoa-puzzle-constructor-name"><?php echo esc_html($con->full_name); ?></span>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!empty($editor_name) && $editor_image_source !== ''): ?>
                        <?php
                        $editor_slug = str_replace(' ', '_', $editor_name);
                        $editor_url = home_url('/kealoa/person/' . urlencode($editor_slug) . '/') . '#kealoa-tab=editor';
                        ?>
                        <div class="kealoa-puzzle-constructor">
                            <a href="<?php echo esc_url($editor_url); ?>">
                                <?php if ($editor_image_source === 'media'): ?>
                                    <img src="<?php echo esc_url($editor_image_url); ?>"
                                         alt="<?php echo esc_attr($editor_name); ?>"
                                         class="kealoa-entity-image" />
                                <?php else: ?>
                                    <?php echo Kealoa_Formatter::format_xwordinfo_image($editor_image_url, $editor_name); ?>
                                <?php endif; ?>
                            </a>
                            <span class="kealoa-puzzle-constructor-name"><?php echo esc_html($editor_name); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($clues)): ?>
            <div class="kealoa-tabs">
                <div class="kealoa-tab-nav">
                    <button class="kealoa-tab-button active" data-tab="clues"><?php esc_html_e('Clues', 'kealoa-reference'); ?></button>
                    <button class="kealoa-tab-button" data-tab="player"><?php esc_html_e('Players', 'kealoa-reference'); ?></button>
                </div>

                <div class="kealoa-tab-panel active" data-tab="clues">

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-puzzle-clues-table">
                        <thead>
                            <tr>
                                <th data-sort="text"><?php esc_html_e('Clue #', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Clue Text', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Answer', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Round Date', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clues_by_position as $entry): ?>
                                <?php
                                $round_words_parts = [];
                                $round_date_parts  = [];
                                foreach ($entry['rounds'] as $er) {
                                    $rid = $er['round_id'];
                                    $gn = $er['round_game_number'] ?? $rid;
                                    $round_words_parts[] = isset($round_solutions_cache[$rid])
                                        ? Kealoa_Formatter::format_solution_words_link($gn, $round_solutions_cache[$rid])
                                        : '';
                                    $date_cell = Kealoa_Formatter::format_round_date_link($gn, $er['round_date']);
                                    if (!empty($er['round_number']) && ($rounds_per_date_cache[$er['round_date']] ?? 1) > 1) {
                                        $date_cell .= ' <span class="kealoa-round-number">(#' . esc_html($er['round_number']) . ')</span>';
                                    }
                                    $round_date_parts[] = $date_cell;
                                }
                                $pos_key = $entry['puzzle_clue_number'] . '_' . $entry['puzzle_clue_direction'];
                                $clue_total   = (int) ($clue_stats_cache[$pos_key]->total_guesses ?? 0);
                                $clue_correct = (int) ($clue_stats_cache[$pos_key]->correct_guesses ?? 0);
                                $clue_pct     = $clue_total > 0
                                    ? ($clue_correct / $clue_total) * 100
                                    : 0;
                                ?>
                                <tr>
                                    <td class="kealoa-clue-ref">
                                        <?php echo esc_html(Kealoa_Formatter::format_clue_direction($entry['puzzle_clue_number'], $entry['puzzle_clue_direction'])); ?>
                                    </td>
                                    <td class="kealoa-clue-text"><?php echo esc_html($entry['clue_text']); ?></td>
                                    <td class="kealoa-correct-answer">
                                        <strong><?php echo esc_html($entry['correct_answer']); ?></strong>
                                    </td>
                                    <td class="kealoa-round-words"><?php echo implode('<br>', $round_words_parts); ?></td>
                                    <td class="kealoa-round-date"><?php echo implode('<br>', $round_date_parts); ?></td>
                                    <td class="kealoa-clue-guesses"><?php echo esc_html(number_format_i18n($clue_total)); ?></td>
                                    <td class="kealoa-clue-correct"><?php echo esc_html(number_format_i18n($clue_correct)); ?></td>
                                    <td class="kealoa-clue-accuracy" data-value="<?php echo esc_attr(number_format((float) $clue_pct, 2, '.', '')); ?>">
                                        <?php echo Kealoa_Formatter::format_percentage($clue_pct); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>

                </div><!-- end Clues tab -->

                <div class="kealoa-tab-panel" data-tab="player">

            <?php if (!empty($player_results)): ?>
                <div class="kealoa-puzzle-player-stats">
                    <h2><?php esc_html_e('Results by Player', 'kealoa-reference'); ?></h2>
                    <p class="kealoa-section-description"><?php esc_html_e('How each player performed on clues from this puzzle.', 'kealoa-reference'); ?></p>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-puzzle-player-table">
                        <thead>
                            <tr>
                                <th data-sort="text"><?php esc_html_e('Player', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($player_results as $result): ?>
                                <tr>
                                    <td><?php echo Kealoa_Formatter::format_person_link((int) $result->person_id, $result->full_name); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->total_guesses)); ?></td>
                                    <td><?php echo esc_html(number_format_i18n((int) $result->correct_guesses)); ?></td>
                                    <?php
                                    $pct = $result->total_guesses > 0
                                        ? ($result->correct_guesses / $result->total_guesses) * 100
                                        : 0;
                                    ?>
                                    <td data-value="<?php echo esc_attr(number_format((float) $pct, 2, '.', '')); ?>">
                                        <?php echo Kealoa_Formatter::format_percentage($pct); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endif; ?>

                </div><!-- end Player tab -->
            </div><!-- end kealoa-tabs -->
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();

        }); // end get_cached_or_render
    }


    /**
     * Render predictions shortcode
     *
     * [kealoa_predictions]
     */
    public function render_predictions(array $atts = []): string {
        return $this->get_cached_or_render('predictions', function () {

        // Fetch data for balance-conditioned predictions
        $clue_sequences = $this->db->get_round_clue_sequences();

        // Group rounds with 2 answers by round_id
        $rounds = [];
        foreach ($clue_sequences as $row) {
            if ((int) $row->solution_count !== 2) {
                continue;
            }
            $rid = (int) $row->round_id;
            $rounds[$rid][] = (int) $row->word_order;
        }

        $sol_count = 2;

        // Track marginals for clue 1
        $bc_marginal = [];
        $bc_marginal_total = 0;

        // Track balance-conditioned transitions: bal_trans[prev][delta][outcome] = count
        $bal_trans = [];

        foreach ($rounds as $rid => $answers) {
            $counts = [1 => 0, 2 => 0];
            $prev = null;

            foreach ($answers as $answer) {
                if ($prev === null) {
                    $bc_marginal[$answer] = ($bc_marginal[$answer] ?? 0) + 1;
                    $bc_marginal_total++;
                } else {
                    $delta = $counts[1] - $counts[2];

                    $bal_trans[$prev][$delta][$answer] = ($bal_trans[$prev][$delta][$answer] ?? 0) + 1;
                    }

                $counts[$answer]++;
                $prev = $answer;
            }
        }

        // Sort transitions by prev then delta
        ksort($bal_trans);
        foreach ($bal_trans as &$deltas) {
            ksort($deltas);
        }
        unset($deltas);

        // Simulate rounds using the model (deterministic: pick highest probability, tiebreaker Answer 1)
        $sim_total = 0;
        $sim_correct = 0;
        $sim_rounds = count($rounds);
        $bc_smoothed_total_sim = $bc_marginal_total + $sol_count;

        foreach ($rounds as $rid => $answers) {
            $sim_counts = [1 => 0, 2 => 0];
            $sim_prev = null;

            foreach ($answers as $actual) {
                if ($sim_prev === null) {
                    // Clue 1: use marginal probabilities
                    $p1 = (($bc_marginal[1] ?? 0) + 1) / $bc_smoothed_total_sim;
                    $p2 = (($bc_marginal[2] ?? 0) + 1) / $bc_smoothed_total_sim;
                } else {
                    // Subsequent clues: use balance-conditioned transitions
                    $sim_delta = $sim_counts[1] - $sim_counts[2];
                    $sim_outcomes = $bal_trans[$sim_prev][$sim_delta] ?? [];
                    $sim_state_total = array_sum($sim_outcomes) + $sol_count;
                    $p1 = (($sim_outcomes[1] ?? 0) + 1) / $sim_state_total;
                    $p2 = (($sim_outcomes[2] ?? 0) + 1) / $sim_state_total;
                }

                $guess = ($p1 >= $p2) ? 1 : 2;
                $sim_total++;
                if ($guess === $actual) {
                    $sim_correct++;
                }

                $sim_counts[$actual]++;
                $sim_prev = $actual;
            }
        }

        $sim_accuracy = $sim_total > 0 ? ($sim_correct / $sim_total) * 100 : 0;

        ob_start();
        ?>
        <div class="kealoa-predictions-wrapper">

        <p class="kealoa-section-description"><?php esc_html_e('Probability of each answer word given the previous answer and the running balance between the two answer words. Uses Bayesian inference with Laplace smoothing.', 'kealoa-reference'); ?></p>

        <h3><?php esc_html_e('Model Simulation', 'kealoa-reference'); ?></h3>
        <p class="kealoa-section-description"><?php esc_html_e('Results of replaying all rounds using the model to predict each answer. The model always picks the most probable answer.', 'kealoa-reference'); ?></p>
        <div class="kealoa-stats-grid">
            <div class="kealoa-stat-card">
                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n($sim_rounds)); ?></span>
                <span class="kealoa-stat-label"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></span>
            </div>
            <div class="kealoa-stat-card">
                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n($sim_total)); ?></span>
                <span class="kealoa-stat-label"><?php esc_html_e('Clues', 'kealoa-reference'); ?></span>
            </div>
            <div class="kealoa-stat-card">
                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n($sim_correct)); ?></span>
                <span class="kealoa-stat-label"><?php esc_html_e('Correct', 'kealoa-reference'); ?></span>
            </div>
            <div class="kealoa-stat-card">
                <span class="kealoa-stat-value"><?php echo Kealoa_Formatter::format_percentage($sim_accuracy); ?></span>
                <span class="kealoa-stat-label"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></span>
            </div>
        </div>

        <?php
        $bc_smoothed_total = $bc_marginal_total + $sol_count;

        for ($prev_answer = 1; $prev_answer <= 2; $prev_answer++):
        ?>
        <h3><?php echo esc_html(sprintf(
            /* translators: %d = answer number */
            __('Previous Answer: Answer %d', 'kealoa-reference'),
            $prev_answer
        )); ?></h3>
        <div class="kealoa-table-scroll">
        <table class="kealoa-table kealoa-show-empty-cols">
            <thead>
                <tr>
                    <th data-sort="text"><?php esc_html_e('Balance', 'kealoa-reference'); ?></th>
                    <th data-sort="number"><?php esc_html_e('Answer 1', 'kealoa-reference'); ?></th>
                    <th data-sort="number"><?php esc_html_e('Answer 2', 'kealoa-reference'); ?></th>
                    <th data-sort="number"><?php esc_html_e('Count', 'kealoa-reference'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php esc_html_e('Start', 'kealoa-reference'); ?></td>
                    <?php
                        $probs = [];
                        for ($an = 1; $an <= 2; $an++) {
                            $count = ($bc_marginal[$an] ?? 0) + 1;
                            $probs[$an] = $bc_smoothed_total > 0 ? ($count / $bc_smoothed_total) * 100 : 0;
                        }
                        $max_prob = max($probs);
                        for ($an = 1; $an <= 2; $an++):
                            $class = ($probs[$an] >= $max_prob) ? 'kealoa-guess-correct' : 'kealoa-guess-incorrect';
                    ?>
                        <td data-value="<?php echo esc_attr(number_format($probs[$an], 2, '.', '')); ?>" class="<?php echo esc_attr($class); ?>"><?php echo Kealoa_Formatter::format_percentage($probs[$an]); ?></td>
                    <?php endfor; ?>
                    <td><?php echo esc_html(number_format_i18n($bc_marginal_total)); ?></td>
                </tr>

                <?php if (isset($bal_trans[$prev_answer])):
                    foreach ($bal_trans[$prev_answer] as $delta => $outcomes):
                        $state_total = array_sum($outcomes);
                        $smoothed_total = $state_total + $sol_count;

                        if ($delta > 0) {
                            $balance_label = sprintf(
                                /* translators: %d = lead amount */
                                __('Answer 1 leads by %d', 'kealoa-reference'),
                                $delta
                            );
                        } elseif ($delta < 0) {
                            $balance_label = sprintf(
                                /* translators: %d = lead amount */
                                __('Answer 2 leads by %d', 'kealoa-reference'),
                                abs($delta)
                            );
                        } else {
                            $balance_label = __('Even', 'kealoa-reference');
                        }
                ?>
                    <tr>
                        <td><?php echo esc_html($balance_label); ?></td>
                        <?php
                            $probs = [];
                            for ($an = 1; $an <= 2; $an++) {
                                $count = ($outcomes[$an] ?? 0) + 1;
                                $probs[$an] = $smoothed_total > 0 ? ($count / $smoothed_total) * 100 : 0;
                            }
                            $max_prob = max($probs);
                            for ($an = 1; $an <= 2; $an++):
                                $class = ($probs[$an] >= $max_prob) ? 'kealoa-guess-correct' : 'kealoa-guess-incorrect';
                        ?>
                            <td data-value="<?php echo esc_attr(number_format($probs[$an], 2, '.', '')); ?>" class="<?php echo esc_attr($class); ?>"><?php echo Kealoa_Formatter::format_percentage($probs[$an]); ?></td>
                        <?php endfor; ?>
                        <td><?php echo esc_html(number_format_i18n($state_total)); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        </div>
        <?php endfor; ?>

        </div>
        <?php
        return ob_get_clean();

        }); // end get_cached_or_render
    }

    /**
     * Render version info shortcode
     *
     * [kealoa_version]
     */
    public function render_version(array $atts = []): string {
        return sprintf(
            '<p class="kealoa-version-widget">Plugin Version: %s<br />Database Version: %s.</p>',
            esc_html(KEALOA_VERSION),
            esc_html(KEALOA_DB_VERSION)
        );
    }
}
