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
                    <button class="kealoa-tab-button" data-tab="stats"><?php esc_html_e('Stats', 'kealoa-reference'); ?></button>
                </div>

                <div class="kealoa-tab-panel" data-tab="stats">
            <?php echo $this->render_rounds_stats_html($overview); ?>

            <?php $yearly_stats = $this->db->get_rounds_stats_by_year(); ?>
            <?php if (!empty($yearly_stats)): ?>
            <h3><?php esc_html_e('Statistics by Year', 'kealoa-reference'); ?></h3>
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
                _n('%d-Answer Rounds', '%d-Answer Rounds', $sol_count, 'kealoa-reference'),
                $sol_count
            )); ?></h3>
            <div class="kealoa-table-scroll">
            <table class="kealoa-table">
                <thead>
                    <tr>
                        <th data-sort="number"><?php esc_html_e('Clue #', 'kealoa-reference'); ?></th>
                        <?php for ($an = 1; $an <= $sol_count; $an++): ?>
                            <th data-sort="number"><?php echo esc_html('Answer #' . $an); ?></th>
                            <th data-sort="number"><?php esc_html_e('Frequency', 'kealoa-reference'); ?></th>
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
                                <td><?php echo $count > 0 ? esc_html($count) : '—'; ?></td>
                                <td><?php echo $count > 0 ? Kealoa_Formatter::format_percentage($freq) : '—'; ?></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endforeach; ?>

                </div><!-- end Stats tab -->

                <div class="kealoa-tab-panel active" data-tab="rounds-played">

            <h3><?php esc_html_e('All Rounds', 'kealoa-reference'); ?></h3>
            <div class="kealoa-filter-controls" data-target="kealoa-rounds-table">
                <div class="kealoa-filter-row">
                    <div class="kealoa-filter-group">
                        <label for="kealoa-rd-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                        <input type="text" id="kealoa-rd-search" class="kealoa-filter-input" data-filter="search" data-col="1" placeholder="<?php esc_attr_e('Solution words...', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-rd-desc"><?php esc_html_e('Description', 'kealoa-reference'); ?></label>
                        <input type="text" id="kealoa-rd-desc" class="kealoa-filter-input" data-filter="search" data-col="3" placeholder="<?php esc_attr_e('Description...', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group kealoa-filter-actions">
                        <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                        <span class="kealoa-filter-count"></span>
                    </div>
                </div>
            </div>
            <table class="kealoa-table kealoa-rounds-table" id="kealoa-rounds-table">
                <thead>
                    <tr>
                        <th data-sort="date" data-default-sort="desc"><?php esc_html_e('Date', 'kealoa-reference'); ?></th>
                        <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                        <th><?php esc_html_e('Results', 'kealoa-reference'); ?></th>
                        <th data-sort="text"><?php esc_html_e('Description', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rounds as $round): ?>
                        <?php
                        $solutions = $this->db->get_round_solutions((int) $round->id);
                        $clue_count = $this->db->get_round_clue_count((int) $round->id);
                        $guesser_results = $this->db->get_round_guesser_results((int) $round->id);
                        $round_num = (int) ($round->round_number ?? 1);
                        $rounds_on_date = $this->db->get_rounds_by_date($round->round_date);
                        ?>
                        <tr>
                            <td class="kealoa-date-cell" data-sort-value="<?php echo esc_attr(date('Ymd', strtotime($round->round_date)) * 100 + $round_num); ?>">
                                <?php
                                echo Kealoa_Formatter::format_round_date_link((int) $round->id, $round->round_date);
                                if (count($rounds_on_date) > 1) {
                                    echo ' <span class="kealoa-round-number">(#' . esc_html($round_num) . ')</span>';
                                }
                                ?>
                            </td>
                            <td class="kealoa-solutions-cell">
                                <?php echo Kealoa_Formatter::format_solution_words_link((int) $round->id, $solutions); ?>
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

                </div><!-- end Rounds Played tab -->
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
                    <p>
                        <strong class="kealoa-meta-label"><?php esc_html_e('Round', 'kealoa-reference'); ?></strong>
                        <span><?php echo esc_html($round_id); ?></span>
                    </p>
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
                    <p>
                        <strong class="kealoa-meta-label"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></strong>
                        <span><?php
                        // Count how many clues have each solution word as the correct answer
                        $answer_counts = [];
                        foreach ($clues as $clue) {
                            $answer = strtoupper($clue->correct_answer);
                            $answer_counts[$answer] = ($answer_counts[$answer] ?? 0) + 1;
                        }
                        $word_parts = [];
                        foreach ($solutions as $s) {
                            $word = strtoupper($s->word);
                            $count = $answer_counts[$word] ?? 0;
                            $word_parts[] = esc_html($word) . ' (' . $count . ')';
                        }
                        echo implode(', ', $word_parts);
                        ?></span>
                    </p>
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
                        $player_url = home_url('/kealoa/person/' . urlencode($player_slug) . '/') . '#kealoa-tab=player';
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
                <div class="kealoa-clues-section">
                    <h2><?php esc_html_e('Clues', 'kealoa-reference'); ?></h2>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-clues-table">
                        <thead>
                            <tr>
                                <th data-sort="number"><?php esc_html_e('#', 'kealoa-reference'); ?></th>
                                <th data-sort="weekday"><?php esc_html_e('Day', 'kealoa-reference'); ?></th>
                                <th data-sort="date"><?php esc_html_e('Puzzle Date', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Constructors', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
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
                                $constructors = !empty($clue->puzzle_id) ? $this->db->get_puzzle_constructors((int) $clue->puzzle_id) : [];
                                $clue_guesses = $this->db->get_clue_guesses((int) $clue->id);
                                ?>
                                <tr>
                                    <td class="kealoa-clue-number"><?php echo esc_html($clue->clue_number); ?></td>
                                    <td class="kealoa-day-cell"><?php echo esc_html(Kealoa_Formatter::format_day_abbrev($clue->puzzle_date)); ?></td>
                                    <td class="kealoa-puzzle-date">
                                        <?php echo Kealoa_Formatter::format_puzzle_date_link($clue->puzzle_date); ?>
                                    </td>
                                    <td class="kealoa-constructors">
                                        <?php echo Kealoa_Formatter::format_constructor_list($constructors); ?>
                                    </td>
                                    <td class="kealoa-editor">
                                        <?php
                                        if (!empty($clue->editor_id)) {
                                            echo Kealoa_Formatter::format_editor_link((int) $clue->editor_id, $clue->editor_name);
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>
                                    <td class="kealoa-clue-ref">
                                        <?php echo esc_html(Kealoa_Formatter::format_clue_direction((int) $clue->puzzle_clue_number, $clue->puzzle_clue_direction)); ?>
                                    </td>
                                    <td class="kealoa-clue-text"><?php echo esc_html($clue->clue_text); ?></td>
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
            $prev_round = $this->db->get_previous_round($round_id);
            $next_round = $this->db->get_next_round($round_id);
            ?>
            <?php if ($prev_round || $next_round): ?>
            <div class="kealoa-round-nav">
                <?php if ($prev_round): ?>
                    <a href="<?php echo esc_url(home_url('/kealoa/round/' . (int) $prev_round->id . '/')); ?>" class="kealoa-round-nav-btn kealoa-round-nav-prev">
                        &larr; <?php esc_html_e('Previous Round', 'kealoa-reference'); ?>
                    </a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <?php if ($next_round): ?>
                    <a href="<?php echo esc_url(home_url('/kealoa/round/' . (int) $next_round->id . '/')); ?>" class="kealoa-round-nav-btn kealoa-round-nav-next">
                        <?php esc_html_e('Next Round', 'kealoa-reference'); ?> &rarr;
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();

        }); // end get_cached_or_render

        // Debug mode: append inline game launcher for this round (outside cache)
        if (kealoa_is_debug()) {
            $html .= '<div class="kealoa-game kealoa-game--debug"'
                . ' data-rest-url="' . esc_url(rest_url('kealoa/v1/game-round')) . '"'
                . ' data-nonce="' . esc_attr(wp_create_nonce('wp_rest')) . '"'
                . ' data-round-ids="' . esc_attr(wp_json_encode([$round_id])) . '"'
                . ' data-force-round="' . esc_attr($round_id) . '">'
                . '<div class="kealoa-game__welcome kealoa-game__welcome--debug">'
                . '<p class="kealoa-game__debug-label">' . esc_html__('Debug: Play This Round', 'kealoa-reference') . '</p>'
                . '<div class="kealoa-game__mode-buttons">'
                . '<button type="button" class="kealoa-game__start-btn" data-mode="show">'
                . esc_html__('In Show Order', 'kealoa-reference')
                . '</button>'
                . '<button type="button" class="kealoa-game__start-btn" data-mode="random">'
                . esc_html__('In Random Order', 'kealoa-reference')
                . '</button>'
                . '</div></div></div>';
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

        return $this->get_cached_or_render('person_' . $person_id, function () use ($person_id, $person) {

        $stats = $this->db->get_person_stats($person_id);

        // Check roles via unified persons schema
        $roles = $this->db->get_person_roles($person_id);
        $is_player = in_array('player', $roles, true);
        $is_constructor = in_array('constructor', $roles, true);
        $is_editor = in_array('editor', $roles, true);

        // Constructor/editor role data (loaded lazily)
        $constructor_stats = $is_constructor ? $this->db->get_person_constructor_stats($person_id) : null;
        $constructor_puzzles = $is_constructor ? $this->db->get_person_constructor_puzzles($person_id) : [];
        $constructor_player_results = $is_constructor ? $this->db->get_person_constructor_player_results($person_id) : [];
        $constructor_editor_results = $is_constructor ? $this->db->get_person_constructor_editor_results($person_id) : [];
        $editor_stats = $is_editor ? $this->db->get_person_editor_stats($person_id) : null;
        $editor_puzzles = $is_editor ? $this->db->get_person_editor_puzzles($person_id) : [];
        $editor_player_results = $is_editor ? $this->db->get_person_editor_player_results($person_id) : [];
        $editor_constructor_results = $is_editor ? $this->db->get_person_editor_constructor_results($person_id) : [];

        $is_clue_giver = in_array('clue_giver', $roles, true);
        $clue_giver_stats = $is_clue_giver ? $this->db->get_clue_giver_stats($person_id) : null;
        $clue_giver_stats_by_year = $is_clue_giver ? $this->db->get_clue_giver_stats_by_year($person_id) : [];
        $clue_giver_stats_by_day = $is_clue_giver ? $this->db->get_clue_giver_stats_by_day($person_id) : [];
        $clue_giver_stats_by_guesser = $is_clue_giver ? $this->db->get_clue_giver_stats_by_guesser($person_id) : [];
        $clue_giver_rounds = $is_clue_giver ? $this->db->get_clue_giver_rounds($person_id) : [];
        $clue_giver_streaks = $is_clue_giver ? $this->db->get_clue_giver_streaks($person_id) : null;

        $person_puzzles = $is_player ? $this->db->get_person_puzzles($person_id) : [];
        $clue_number_results = $this->db->get_person_results_by_clue_number($person_id);
        $answer_length_results = $this->db->get_person_results_by_answer_length($person_id);
        $direction_results = $this->db->get_person_results_by_direction($person_id);
        $day_of_week_results = $this->db->get_person_results_by_day_of_week($person_id);
        $decade_results = $this->db->get_person_results_by_decade($person_id);
        $year_results = $this->db->get_person_results_by_year($person_id);
        $best_streaks_by_year = $this->db->get_person_best_streaks_by_year($person_id);
        $constructor_results = $this->db->get_person_results_by_constructor($person_id);
        $editor_results = $this->db->get_person_results_by_editor($person_id);
        $round_history = $this->db->get_person_round_history($person_id);
        $streak_per_round = $this->db->get_person_streak_per_round($person_id);
        $correct_clue_rounds = $this->db->get_person_correct_clue_rounds($person_id);

        // Build round info lookup: round_id => {url, date, words, score}
        $round_info = [];
        foreach ($round_history as $rh) {
            $rh_solutions = $this->db->get_round_solutions((int) $rh->round_id);
            $round_info[(int) $rh->round_id] = [
                'url' => home_url('/kealoa/round/' . $rh->round_id . '/'),
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

        // Pre-compute: rounds with best streak overall
        $best_streak_round_ids = [];
        foreach ($streak_per_round as $rid => $streak) {
            if ($streak === $stats->best_streak) {
                $best_streak_round_ids[] = $rid;
            }
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
        foreach ($best_streaks_by_year as $year => $best_streak) {
            $best_streak_rounds_by_year[$year] = [];
            foreach ($round_ids_by_year[$year] ?? [] as $rid) {
                if (($streak_per_round[$rid] ?? 0) === $best_streak) {
                    $best_streak_rounds_by_year[$year][] = $rid;
                }
            }
        }

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
                    </div>
                </div>
            </div>

            <div class="kealoa-tabs">
                <div class="kealoa-tab-nav">
                    <?php if ($is_player): ?>
                    <button class="kealoa-tab-button<?php echo $tab_active('player'); ?>" data-tab="player"><?php esc_html_e('Player', 'kealoa-reference'); ?></button>
                    <?php endif; ?>
                    <?php if ($is_clue_giver): ?>
                    <button class="kealoa-tab-button<?php echo $tab_active('host'); ?>" data-tab="host"><?php esc_html_e('Host', 'kealoa-reference'); ?></button>
                    <?php endif; ?>
                    <?php if ($is_constructor): ?>
                    <button class="kealoa-tab-button<?php echo $tab_active('constructor'); ?>" data-tab="constructor"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></button>
                    <?php endif; ?>
                    <?php if ($is_editor): ?>
                    <button class="kealoa-tab-button<?php echo $tab_active('editor'); ?>" data-tab="editor"><?php esc_html_e('Editor', 'kealoa-reference'); ?></button>
                    <?php endif; ?>
                </div>

                <?php if ($is_player): ?>
                <div class="kealoa-tab-panel<?php echo $tab_active('player'); ?>" data-tab="player">

                <div class="kealoa-tabs">
                    <div class="kealoa-tab-nav">
                        <button class="kealoa-tab-button active" data-tab="player-stats"><?php esc_html_e('Overall Stats', 'kealoa-reference'); ?></button>
                        <button class="kealoa-tab-button" data-tab="round"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></button>
                        <button class="kealoa-tab-button" data-tab="puzzle"><?php esc_html_e('Puzzle Stats', 'kealoa-reference'); ?></button>
                        <button class="kealoa-tab-button" data-tab="puzzles"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></button>
                        <button class="kealoa-tab-button" data-tab="by-constructor"><?php esc_html_e('By Constructor', 'kealoa-reference'); ?></button>
                        <button class="kealoa-tab-button" data-tab="by-editor"><?php esc_html_e('By Editor', 'kealoa-reference'); ?></button>
                    </div>

                <div class="kealoa-tab-panel active" data-tab="player-stats">

            <div class="kealoa-person-stats">
                <h2><?php esc_html_e('KEALOA Statistics', 'kealoa-reference'); ?></h2>

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
                        <span class="kealoa-stat-value"><a class="kealoa-round-picker-link" data-rounds="<?php echo $build_picker_json($best_streak_round_ids); ?>"><?php echo esc_html($stats->best_streak); ?></a></span>
                        <span class="kealoa-stat-label"><?php esc_html_e('Best Streak', 'kealoa-reference'); ?></span>
                    </div>
                </div>

            <?php if (!empty($round_history)): ?>
                <div class="kealoa-score-distribution-section">
                    <h2><?php esc_html_e('Score Distribution', 'kealoa-reference'); ?></h2>
                    <div class="kealoa-chart-container">
                        <canvas id="kealoa-score-distribution-chart"></canvas>
                    </div>
                    <?php
                    // Build score distribution: count rounds per score (0-10)
                    $score_counts = array_fill(0, 11, 0);
                    foreach ($round_history as $rh) {
                        $score = min(10, max(0, (int) $rh->correct_count));
                        $score_counts[$score]++;
                    }
                    $score_labels = array_map('strval', range(10, 0, -1));
                    $score_data = array_reverse(array_values($score_counts));

                    // Build round picker data per score (10→0 order matching labels)
                    $score_rounds_json = [];
                    for ($s = 10; $s >= 0; $s--) {
                        $rids = $rounds_by_score[$s] ?? [];
                        $rounds_for_score = [];
                        foreach ($rids as $rid) {
                            if (isset($round_info[$rid])) {
                                $ri = $round_info[$rid];
                                $rounds_for_score[] = [
                                    'url' => $ri['url'],
                                    'date' => $ri['date'],
                                    'words' => $ri['words'],
                                    'score' => $ri['score'],
                                ];
                            }
                        }
                        $score_rounds_json[] = $rounds_for_score;
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
                                                text: <?php echo wp_json_encode(__('Correct Answers', 'kealoa-reference')); ?>
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
                    <h2><?php esc_html_e('Score by Round', 'kealoa-reference'); ?></h2>
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
                        $chart_data[] = (int) $ch->correct_count;
                        $chart_words[] = $ri ? $ri['words'] : '';
                        $chart_urls[] = $ri ? $ri['url'] : home_url('/kealoa/round/' . $rid . '/');
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
                                                text: <?php echo wp_json_encode(__('Correct', 'kealoa-reference')); ?>
                                            },
                                            min: 0,
                                            max: 10,
                                            ticks: {
                                                stepSize: 2,
                                                precision: 0,
                                                autoSkip: false
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
                                                    return item.parsed.y + ' correct';
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
                                $yr_best_streak_val = $best_streaks_by_year[$yr] ?? 0;
                                ?>
                                <tr>
                                    <td><a class="kealoa-year-tab-link" data-year="<?php echo esc_attr($yr); ?>" data-tab-target="round" data-filter-target="kealoa-rh-year"><?php echo esc_html($yr); ?></a></td>
                                    <td><?php echo esc_html($result->rounds_played); ?></td>
                                    <td><?php echo esc_html($result->total_answered); ?></td>
                                    <td><?php echo esc_html($result->correct_count); ?></td>
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
            <?php endif; ?>

            <?php if (!empty($clue_number_results)): ?>
                <div class="kealoa-clue-number-stats">
                    <h2><?php esc_html_e('Results by Clue Number', 'kealoa-reference'); ?></h2>

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
                                    <td><?php echo esc_html($result->total_answered); ?></td>
                                    <td><?php echo esc_html($result->correct_count); ?></td>
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
            <?php endif; ?>

            <?php if (!empty($answer_length_results)): ?>
                <div class="kealoa-answer-length-stats">
                    <h2><?php esc_html_e('Results by Answer Length', 'kealoa-reference'); ?></h2>

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
                                    <td><?php echo esc_html($result->total_answered); ?></td>
                                    <td><?php echo esc_html($result->correct_count); ?></td>
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
            <?php endif; ?>

            </div>

                </div><!-- end Overall Stats sub-tab -->

                <div class="kealoa-tab-panel" data-tab="puzzles">

            <?php if (!empty($person_puzzles)): ?>
                <div class="kealoa-person-puzzles">
                    <h2><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></h2>

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
                                <label for="kealoa-pp-date-from"><?php esc_html_e('Publication Date', 'kealoa-reference'); ?></label>
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
                                <th data-sort="date"><?php esc_html_e('Publication Date', 'kealoa-reference'); ?></th>
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
                                                if ($rid && $rdate) {
                                                    $round_links[] = Kealoa_Formatter::format_round_date_link($rid, $rdate);
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
                                                    $solutions = $this->db->get_round_solutions($rid);
                                                    $solution_links[] = Kealoa_Formatter::format_solution_words_link($rid, $solutions);
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
                                    <td><?php echo esc_html($result->total_answered); ?></td>
                                    <td><?php echo esc_html($result->correct_count); ?></td>
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
            <?php endif; ?>

            <?php if (!empty($decade_results)): ?>
                <div class="kealoa-decade-stats">
                    <h2><?php esc_html_e('Results by Puzzle Decade', 'kealoa-reference'); ?></h2>

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
                                    <td><?php echo esc_html($result->total_answered); ?></td>
                                    <td><?php echo esc_html($result->correct_count); ?></td>
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
            <?php endif; ?>

            <?php if (!empty($direction_results)): ?>
                <div class="kealoa-direction-stats">
                    <h2><?php esc_html_e('Results by Clue Direction', 'kealoa-reference'); ?></h2>

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
                                    <td><?php echo esc_html($result->total_answered); ?></td>
                                    <td><?php echo esc_html($result->correct_count); ?></td>
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
            <?php endif; ?>

                </div><!-- end Puzzle tab -->

                <div class="kealoa-tab-panel" data-tab="by-constructor">

            <?php if (!empty($constructor_results)): ?>
                <div class="kealoa-constructor-stats">
                    <h2><?php esc_html_e('Results by Constructor', 'kealoa-reference'); ?></h2>

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
                                    <td><?php echo esc_html($result->total_answered); ?></td>
                                    <td><?php echo esc_html($result->correct_count); ?></td>
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
            <?php endif; ?>

                </div><!-- end By Constructor tab -->

                <div class="kealoa-tab-panel" data-tab="by-editor">

            <?php if (!empty($editor_results)): ?>
                <div class="kealoa-editor-stats">
                    <h2><?php esc_html_e('Results by Editor', 'kealoa-reference'); ?></h2>

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
                                    <td><?php echo esc_html($result->total_answered); ?></td>
                                    <td><?php echo esc_html($result->correct_count); ?></td>
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
            <?php endif; ?>

                </div><!-- end By Editor tab -->

                <div class="kealoa-tab-panel" data-tab="round">

            <?php if (!empty($round_history)): ?>
                <?php
                // Pre-compute co-players for each round to determine if "Played With" column is needed
                $round_co_players = [];
                $has_co_players = false;
                foreach ($round_history as $rh_check) {
                    $rh_guessers = $this->db->get_round_guessers((int) $rh_check->round_id);
                    $co_players = array_filter($rh_guessers, function($g) use ($person_id) {
                        return (int) $g->id !== $person_id;
                    });
                    $round_co_players[(int) $rh_check->round_id] = $co_players;
                    if (!empty($co_players)) {
                        $has_co_players = true;
                    }
                }
                ?>
                <div class="kealoa-round-history">
                    <h2><?php esc_html_e('Round History', 'kealoa-reference'); ?></h2>

                    <div class="kealoa-filter-controls" data-target="kealoa-person-round-history-table">
                        <div class="kealoa-filter-row">
                            <div class="kealoa-filter-group">
                                <label for="kealoa-rh-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                                <input type="text" id="kealoa-rh-search" class="kealoa-filter-input" data-filter="search" data-col="1" placeholder="<?php esc_attr_e('Solution words...', 'kealoa-reference'); ?>">
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
                                <input type="number" id="kealoa-rh-min-correct" class="kealoa-filter-input" data-filter="min" data-col="<?php echo $has_co_players ? '3' : '2'; ?>" min="0" placeholder="<?php esc_attr_e('e.g. 5', 'kealoa-reference'); ?>">
                            </div>
                        </div>
                        <div class="kealoa-filter-row">
                            <div class="kealoa-filter-group">
                                <label for="kealoa-rh-min-streak"><?php esc_html_e('Min. Streak', 'kealoa-reference'); ?></label>
                                <input type="number" id="kealoa-rh-min-streak" class="kealoa-filter-input" data-filter="min" data-col="<?php echo $has_co_players ? '4' : '3'; ?>" min="0" placeholder="<?php esc_attr_e('e.g. 3', 'kealoa-reference'); ?>">
                            </div>
                            <div class="kealoa-filter-group">
                                <label for="kealoa-rh-perfect"><?php esc_html_e('Score', 'kealoa-reference'); ?></label>
                                <select id="kealoa-rh-perfect" class="kealoa-filter-select" data-filter="perfect" data-col="<?php echo $has_co_players ? '3' : '2'; ?>">
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

                    <table class="kealoa-table kealoa-history-table" id="kealoa-person-round-history-table">
                        <thead>
                            <tr>
                                <th data-sort="date" data-default-sort="desc"><?php esc_html_e('Date', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
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
                                $solutions = $this->db->get_round_solutions((int) $history->round_id);
                                $history_round_num = (int) ($history->round_number ?? 1);
                                $history_rounds_on_date = $this->db->get_rounds_by_date($history->round_date);
                                ?>
                                <tr data-year="<?php echo esc_attr(date('Y', strtotime($history->round_date))); ?>">
                                    <td data-sort-value="<?php echo esc_attr(date('Ymd', strtotime($history->round_date)) * 100 + $history_round_num); ?>">
                                        <?php
                                        echo Kealoa_Formatter::format_round_date_link((int) $history->round_id, $history->round_date);
                                        if (count($history_rounds_on_date) > 1) {
                                            echo ' <span class="kealoa-round-number">(#' . esc_html($history_round_num) . ')</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo Kealoa_Formatter::format_solution_words_link((int) $history->round_id, $solutions); ?></td>
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
                                    <td data-total="<?php echo esc_attr((int) $history->total_clues); ?>"><?php echo esc_html($history->correct_count); ?></td>
                                    <td><?php echo esc_html($this->db->get_person_round_streak((int) $history->round_id, $person_id)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

                </div><!-- end Round sub-tab -->

                </div><!-- end secondary kealoa-tabs -->
                </div><!-- end Player primary tab -->
                <?php endif; ?><!-- end $is_player -->

                <?php if ($is_clue_giver): ?>
                <div class="kealoa-tab-panel<?php echo $tab_active('host'); ?>" data-tab="host">

                <div class="kealoa-tabs">
                    <div class="kealoa-tab-nav">
                        <button class="kealoa-tab-button active" data-tab="host-rounds"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></button>
                        <button class="kealoa-tab-button" data-tab="host-stats"><?php esc_html_e('Stats', 'kealoa-reference'); ?></button>
                    </div>

                <div class="kealoa-tab-panel" data-tab="host-stats">

                    <?php if ($clue_giver_stats): ?>
                    <div class="kealoa-person-clue-giver-overview">
                        <h2><?php esc_html_e('Host Statistics', 'kealoa-reference'); ?></h2>
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

                    <?php if ($clue_giver_streaks): ?>
                    <div class="kealoa-clue-giver-streaks">
                        <h2><?php esc_html_e('Streaks', 'kealoa-reference'); ?></h2>
                        <div class="kealoa-stats-grid">
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $clue_giver_streaks->best_correct_streak)); ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Longest Correct Streak', 'kealoa-reference'); ?></span>
                            </div>
                            <div class="kealoa-stat-card">
                                <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n((int) $clue_giver_streaks->best_incorrect_streak)); ?></span>
                                <span class="kealoa-stat-label"><?php esc_html_e('Longest Incorrect Streak', 'kealoa-reference'); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($clue_giver_stats_by_year)): ?>
                    <div class="kealoa-clue-giver-by-year">
                        <h2><?php esc_html_e('By Year', 'kealoa-reference'); ?></h2>

                        <div class="kealoa-filter-controls" data-target="kealoa-person-cg-year-table">
                            <div class="kealoa-filter-row">
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pcgy-year"><?php esc_html_e('Year', 'kealoa-reference'); ?></label>
                                    <input type="number" id="kealoa-pcgy-year" class="kealoa-filter-input" data-filter="search" data-col="0" placeholder="<?php esc_attr_e('e.g. 2024', 'kealoa-reference'); ?>">
                                </div>
                                <div class="kealoa-filter-group kealoa-filter-actions">
                                    <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                                    <span class="kealoa-filter-count"></span>
                                </div>
                            </div>
                        </div>

                        <table class="kealoa-table" id="kealoa-person-cg-year-table">
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
                                <?php foreach ($clue_giver_stats_by_year as $cgy): ?>
                                    <?php
                                    $cgy_pct = (int) $cgy->total_guesses > 0
                                        ? ((int) $cgy->correct_guesses / (int) $cgy->total_guesses) * 100
                                        : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($cgy->year); ?></td>
                                        <td><?php echo esc_html($cgy->round_count); ?></td>
                                        <td><?php echo esc_html($cgy->clue_count); ?></td>
                                        <td><?php echo esc_html($cgy->total_guesses); ?></td>
                                        <td><?php echo esc_html($cgy->correct_guesses); ?></td>
                                        <td data-value="<?php echo esc_attr(number_format((float) $cgy_pct, 2, '.', '')); ?>">
                                            <?php echo Kealoa_Formatter::format_percentage($cgy_pct); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($clue_giver_stats_by_day)): ?>
                    <div class="kealoa-clue-giver-by-day">
                        <h2><?php esc_html_e('By Day of Week', 'kealoa-reference'); ?></h2>

                        <table class="kealoa-table" id="kealoa-person-cg-day-table">
                            <thead>
                                <tr>
                                    <th data-sort="weekday"><?php esc_html_e('Day', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Clues', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clue_giver_stats_by_day as $cgd): ?>
                                    <?php
                                    $cgd_pct = (int) $cgd->total_guesses > 0
                                        ? ((int) $cgd->correct_guesses / (int) $cgd->total_guesses) * 100
                                        : 0;
                                    ?>
                                    <tr>
                                        <td class="kealoa-day-cell"><?php echo esc_html(Kealoa_Formatter::get_day_name((int) $cgd->day_of_week)); ?></td>
                                        <td><?php echo esc_html($cgd->round_count); ?></td>
                                        <td><?php echo esc_html($cgd->clue_count); ?></td>
                                        <td><?php echo esc_html($cgd->total_guesses); ?></td>
                                        <td><?php echo esc_html($cgd->correct_guesses); ?></td>
                                        <td data-value="<?php echo esc_attr(number_format((float) $cgd_pct, 2, '.', '')); ?>">
                                            <?php echo Kealoa_Formatter::format_percentage($cgd_pct); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($clue_giver_stats_by_guesser)): ?>
                    <div class="kealoa-clue-giver-by-guesser">
                        <h2><?php esc_html_e('By Player', 'kealoa-reference'); ?></h2>

                        <div class="kealoa-filter-controls" data-target="kealoa-person-cg-guesser-table">
                            <div class="kealoa-filter-row">
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pcgg-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                                    <input type="text" id="kealoa-pcgg-search" class="kealoa-filter-input" data-filter="search" data-col="0" placeholder="<?php esc_attr_e('Player name...', 'kealoa-reference'); ?>">
                                </div>
                                <div class="kealoa-filter-group">
                                    <label for="kealoa-pcgg-min-guesses"><?php esc_html_e('Min. Guesses', 'kealoa-reference'); ?></label>
                                    <input type="number" id="kealoa-pcgg-min-guesses" class="kealoa-filter-input" data-filter="min" data-col="1" min="1" placeholder="<?php esc_attr_e('e.g. 5', 'kealoa-reference'); ?>">
                                </div>
                                <div class="kealoa-filter-group kealoa-filter-actions">
                                    <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                                    <span class="kealoa-filter-count"></span>
                                </div>
                            </div>
                        </div>

                        <table class="kealoa-table" id="kealoa-person-cg-guesser-table">
                            <thead>
                                <tr>
                                    <th data-sort="text"><?php esc_html_e('Player', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clue_giver_stats_by_guesser as $cgg): ?>
                                    <?php
                                    $cgg_pct = (int) $cgg->total_guesses > 0
                                        ? ((int) $cgg->correct_guesses / (int) $cgg->total_guesses) * 100
                                        : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo Kealoa_Formatter::format_person_link((int) $cgg->person_id, $cgg->full_name); ?></td>
                                        <td><?php echo esc_html($cgg->total_guesses); ?></td>
                                        <td><?php echo esc_html($cgg->correct_guesses); ?></td>
                                        <td data-value="<?php echo esc_attr(number_format((float) $cgg_pct, 2, '.', '')); ?>">
                                            <?php echo Kealoa_Formatter::format_percentage($cgg_pct); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                </div><!-- end host-stats sub-tab -->

                <div class="kealoa-tab-panel active" data-tab="host-rounds">

                    <?php if (!empty($clue_giver_rounds)): ?>
                    <div class="kealoa-clue-giver-rounds">
                        <h2><?php esc_html_e('Rounds', 'kealoa-reference'); ?></h2>

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
                                    $cgr_solutions = $this->db->get_round_solutions($cgr_id);
                                    $cgr_guesser_ids = !empty($cgr->guesser_ids) ? array_map('intval', explode(',', $cgr->guesser_ids)) : [];
                                    $cgr_guesser_names = !empty($cgr->guesser_names) ? explode(', ', $cgr->guesser_names) : [];
                                    $cgr_rounds_on_date = $this->db->get_rounds_by_date($cgr->round_date);
                                    $cgr_round_num = (int) ($cgr->round_number ?? 1);
                                    ?>
                                    <tr>
                                        <td data-sort-value="<?php echo esc_attr(date('Ymd', strtotime($cgr->round_date)) * 100 + $cgr_round_num); ?>">
                                            <?php
                                            echo Kealoa_Formatter::format_round_date_link($cgr_id, $cgr->round_date);
                                            if (count($cgr_rounds_on_date) > 1) {
                                                echo ' <span class="kealoa-round-number">(#' . esc_html($cgr_round_num) . ')</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="kealoa-solutions-cell"><?php echo Kealoa_Formatter::format_solution_words_link($cgr_id, $cgr_solutions); ?></td>
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
                                        <td><?php echo esc_html($cgr->correct_guesses); ?></td>
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
                    <?php
                    // Check if any puzzle has co-constructors
                    $has_co_constructors = false;
                    foreach ($constructor_puzzles as $p_check) {
                        $co_check = $this->db->get_puzzle_co_constructors((int) $p_check->puzzle_id, $person_id);
                        if (!empty($co_check)) {
                            $has_co_constructors = true;
                            break;
                        }
                    }
                    ?>
                    <div class="kealoa-constructor-puzzles">
                        <h2><?php esc_html_e('Puzzles Constructed', 'kealoa-reference'); ?></h2>

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
                                    <th data-sort="date"><?php esc_html_e('Publication Date', 'kealoa-reference'); ?></th>
                                    <?php if ($has_co_constructors): ?>
                                    <th data-sort="text"><?php esc_html_e('Co-Constructors', 'kealoa-reference'); ?></th>
                                    <?php endif; ?>
                                    <th data-sort="text"><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
                                    <th data-sort="date"><?php esc_html_e('Round Date', 'kealoa-reference'); ?></th>
                                    <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($constructor_puzzles as $cpuzzle): ?>
                                    <?php
                                    $co_constructors = $this->db->get_puzzle_co_constructors((int) $cpuzzle->puzzle_id, $person_id);
                                    $cp_round_ids = !empty($cpuzzle->round_ids) ? explode(',', $cpuzzle->round_ids) : [];
                                    $cp_round_dates = !empty($cpuzzle->round_dates) ? explode(',', $cpuzzle->round_dates) : [];
                                    $cp_round_numbers = !empty($cpuzzle->round_numbers) ? explode(',', $cpuzzle->round_numbers) : [];
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
                                                    if ($rid && $rdate) {
                                                        $cp_round_links[] = Kealoa_Formatter::format_round_date_link($rid, $rdate);
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
                                                        $solutions = $this->db->get_round_solutions($rid);
                                                        $cp_sol_links[] = Kealoa_Formatter::format_solution_words_link($rid, $solutions);
                                                    }
                                                }
                                                echo implode('<br>', $cp_sol_links);
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

                </div><!-- end constructor-puzzles sub-tab -->

                <div class="kealoa-tab-panel" data-tab="constructor-results">

                    <?php if (!empty($constructor_player_results)): ?>
                    <div class="kealoa-constructor-player-stats">
                        <h2><?php esc_html_e('Player Results', 'kealoa-reference'); ?></h2>

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
                                        <td><?php echo esc_html($cpr->total_answered); ?></td>
                                        <td><?php echo esc_html($cpr->correct_count); ?></td>
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
                    <?php endif; ?>

                    <?php if (!empty($constructor_editor_results)): ?>
                    <div class="kealoa-constructor-editor-stats">
                        <h2><?php esc_html_e('Editor Results', 'kealoa-reference'); ?></h2>

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

                        <table class="kealoa-table kealoa-constructor-editor-table" id="kealoa-person-con-editor-table">
                            <thead>
                                <tr>
                                    <th data-sort="text"><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
                                    <th data-sort="number"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></th>
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
                                        <td><?php echo esc_html($cer->puzzle_count); ?></td>
                                        <td><?php echo esc_html($cer->clue_count); ?></td>
                                        <td><?php echo esc_html($cer->total_guesses); ?></td>
                                        <td><?php echo esc_html($cer->correct_guesses); ?></td>
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
                                    <label for="kealoa-ped-date-from"><?php esc_html_e('Publication Date', 'kealoa-reference'); ?></label>
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
                                    <th data-sort="date"><?php esc_html_e('Publication Date', 'kealoa-reference'); ?></th>
                                    <th data-sort="text"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></th>
                                    <th data-sort="date"><?php esc_html_e('Round Date', 'kealoa-reference'); ?></th>
                                    <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
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
                                                    if ($rid && $rdate) {
                                                        $ep_round_links[] = Kealoa_Formatter::format_round_date_link($rid, $rdate);
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
                                                        $solutions = $this->db->get_round_solutions($rid);
                                                        $ep_sol_links[] = Kealoa_Formatter::format_solution_words_link($rid, $solutions);
                                                    }
                                                }
                                                echo implode('<br>', $ep_sol_links);
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

                </div><!-- end editor-puzzles sub-tab -->

                <div class="kealoa-tab-panel" data-tab="editor-results">

                    <?php if (!empty($editor_player_results)): ?>
                    <div class="kealoa-editor-player-stats">
                        <h2><?php esc_html_e('Player Results', 'kealoa-reference'); ?></h2>

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
                                        <td><?php echo esc_html($epr->total_answered); ?></td>
                                        <td><?php echo esc_html($epr->correct_count); ?></td>
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
                    <?php endif; ?>

                    <?php if (!empty($editor_constructor_results)): ?>
                    <div class="kealoa-editor-constructor-stats">
                        <h2><?php esc_html_e('Constructor Results', 'kealoa-reference'); ?></h2>

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
                                        <td><?php echo esc_html($ecr->puzzle_count); ?></td>
                                        <td><?php echo esc_html($ecr->clue_count); ?></td>
                                        <td><?php echo esc_html($ecr->total_guesses); ?></td>
                                        <td><?php echo esc_html($ecr->correct_guesses); ?></td>
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

        $highest_scores = $this->db->get_all_persons_highest_round_scores();
        $longest_streaks = $this->db->get_all_persons_longest_streaks();
        $best_score_round_ids = $this->db->get_all_persons_highest_round_score_round_ids();
        $best_streak_round_ids = $this->db->get_all_persons_longest_streak_round_ids();

        // Collect all unique round IDs needed for picker links
        $all_round_ids = [];
        foreach ($best_score_round_ids as $ids) {
            $all_round_ids = array_merge($all_round_ids, $ids);
        }
        foreach ($best_streak_round_ids as $ids) {
            $all_round_ids = array_merge($all_round_ids, $ids);
        }
        $all_round_ids = array_unique($all_round_ids);

        // Build round info for all needed rounds
        $round_info = [];
        foreach ($all_round_ids as $rid) {
            $round = $this->db->get_round($rid);
            if ($round) {
                $solutions = $this->db->get_round_solutions($rid);
                $round_info[$rid] = [
                    'url' => home_url('/kealoa/round/' . $rid . '/'),
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

            <table class="kealoa-table kealoa-persons-table" id="kealoa-persons-table">
                <thead>
                    <tr>
                        <th data-sort="text"><?php esc_html_e('Player', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></th>
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

        ob_start();
        ?>
        <div class="kealoa-constructors-table-wrapper">
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

        ob_start();
        ?>
        <div class="kealoa-editors-table-wrapper">
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

            <table class="kealoa-table kealoa-editors-table" id="kealoa-editors-table">
                <thead>
                    <tr>
                        <th data-sort="text"><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></th>
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

        ob_start();
        ?>
        <div class="kealoa-tabs">
            <nav class="kealoa-tab-nav" role="tablist">
                <button class="kealoa-tab-button active" role="tab" aria-selected="true" data-tab="kealoa-puzzles-tab-all"><?php esc_html_e('All Puzzles', 'kealoa-reference'); ?></button>
                <button class="kealoa-tab-button" role="tab" aria-selected="false" data-tab="kealoa-puzzles-tab-curiosities"><?php esc_html_e('Curiosities', 'kealoa-reference'); ?></button>
            </nav>
            <div class="kealoa-tab-panel active" id="kealoa-puzzles-tab-all" data-tab="kealoa-puzzles-tab-all" role="tabpanel">
        <?php if (empty($puzzles)): ?>
            <p class="kealoa-no-data"><?php esc_html_e('No puzzles found.', 'kealoa-reference'); ?></p>
        <?php else: ?>
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
                        <label for="kealoa-pz-date-from"><?php esc_html_e('Publication Date', 'kealoa-reference'); ?></label>
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
                        <th data-sort="date"><?php esc_html_e('Publication Date', 'kealoa-reference'); ?></th>
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
                                        if ($rid && $rdate) {
                                            $round_links[] = Kealoa_Formatter::format_round_date_link($rid, $rdate);
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
                                            $solutions = $this->db->get_round_solutions($rid);
                                            $solution_links[] = Kealoa_Formatter::format_solution_words_link($rid, $solutions);
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
                <?php $this->render_curiosities_panel($multi_round_puzzles); ?>
            </div><!-- end kealoa-puzzles-tab-curiosities -->
        </div><!-- end kealoa-tabs -->
        <?php
        return ob_get_clean();

        }); // end get_cached_or_render
    }

    /**
     * Render the Curiosities tab panel — puzzles used in more than one round.
     *
     * @param array $puzzles Array of puzzle objects with round_count.
     */
    private function render_curiosities_panel(array $puzzles): void {
        if (empty($puzzles)): ?>
            <p class="kealoa-no-data"><?php esc_html_e('No puzzles have been used in more than one round yet.', 'kealoa-reference'); ?></p>
        <?php return; endif; ?>
        <h2><?php esc_html_e('Puzzles Used in Multiple Rounds', 'kealoa-reference'); ?></h2>
        <div class="kealoa-puzzles-table-wrapper">
            <div class="kealoa-table-scroll">
                <table class="kealoa-table kealoa-puzzles-table" id="kealoa-puzzles-curiosities-table">
                    <thead>
                        <tr>
                            <th data-sort="weekday"><?php esc_html_e('Day', 'kealoa-reference'); ?></th>
                            <th data-sort="date"><?php esc_html_e('Publication Date', 'kealoa-reference'); ?></th>
                            <th data-sort="text"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></th>
                            <th data-sort="text"><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
                            <th data-sort="number"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($puzzles as $puzzle): ?>
                            <?php
                            $constructor_ids   = !empty($puzzle->constructor_ids)   ? explode(',', $puzzle->constructor_ids)   : [];
                            $constructor_names = !empty($puzzle->constructor_names) ? explode(', ', $puzzle->constructor_names) : [];
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
                                <td class="kealoa-results-cell"><?php echo esc_html((int) $puzzle->round_count); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
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
        $round_solutions_cache = [];
        $rounds_per_date_cache = [];
        foreach ($rounds_clues as $rid => $rc) {
            $round_solutions_cache[$rid] = $this->db->get_round_solutions($rid);
            $date = $rc['round_date'];
            if (!isset($rounds_per_date_cache[$date])) {
                $rounds_per_date_cache[$date] = count($this->db->get_rounds_by_date($date));
            }
        }

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
                'round_id'     => (int) $clue->round_id,
                'round_date'   => $clue->round_date,
                'round_number' => $clue->round_number,
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

                        <?php if (!empty($rounds_clues)): ?>
                        <p>
                            <strong class="kealoa-meta-label"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></strong>
                            <span>
                                <?php
                                $round_links = [];
                                foreach ($rounds_clues as $rc) {
                                    $round_links[] = Kealoa_Formatter::format_round_date_link((int) $rc['round_id'], $rc['round_date']);
                                }
                                echo implode(', ', $round_links);
                                ?>
                            </span>
                        </p>
                        <?php endif; ?>

                        <?php if (!empty($rounds_clues)): ?>
                        <p>
                            <strong class="kealoa-meta-label"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></strong>
                            <span>
                                <?php
                                $sol_parts = [];
                                foreach ($rounds_clues as $rc) {
                                    $solutions = $this->db->get_round_solutions((int) $rc['round_id']);
                                    $sol_parts[] = Kealoa_Formatter::format_solution_words_link((int) $rc['round_id'], $solutions);
                                }
                                echo implode(', ', $sol_parts);
                                ?>
                            </span>
                        </p>
                        <?php endif; ?>
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
                            $con_image_url = Kealoa_Formatter::xwordinfo_image_url_from_name($con->full_name);
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
                                <th data-sort="text"><?php esc_html_e('Round Words', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Round Date', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clues_by_position as $entry): ?>
                                <?php
                                $round_words_parts = [];
                                $round_date_parts  = [];
                                foreach ($entry['rounds'] as $er) {
                                    $rid = $er['round_id'];
                                    $round_words_parts[] = isset($round_solutions_cache[$rid])
                                        ? Kealoa_Formatter::format_solution_words_link($rid, $round_solutions_cache[$rid])
                                        : '';
                                    $date_cell = Kealoa_Formatter::format_round_date_link($rid, $er['round_date']);
                                    if (!empty($er['round_number']) && ($rounds_per_date_cache[$er['round_date']] ?? 1) > 1) {
                                        $date_cell .= ' <span class="kealoa-round-number">(#' . esc_html($er['round_number']) . ')</span>';
                                    }
                                    $round_date_parts[] = $date_cell;
                                }
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
                                    <td><?php echo esc_html($result->total_guesses); ?></td>
                                    <td><?php echo esc_html($result->correct_guesses); ?></td>
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
     * Render version info shortcode
     *
     * [kealoa_version]
     */
    public function render_version(array $atts = []): string {
        return sprintf(
            '<p class="kealoa-version-widget">Plugin Version: %s, Database Version: %s.</p>',
            esc_html(KEALOA_VERSION),
            esc_html(KEALOA_DB_VERSION)
        );
    }
}
