<?php
/**
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
        add_shortcode('kealoa_constructor', [$this, 'render_constructor']);
        add_shortcode('kealoa_editors_table', [$this, 'render_editors_table']);
        add_shortcode('kealoa_editor', [$this, 'render_editor']);
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
                <span class="kealoa-stat-value"><?php echo esc_html($overview->total_rounds); ?></span>
                <span class="kealoa-stat-label"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></span>
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
            <?php echo $this->render_rounds_stats_html($overview); ?>

            <div class="kealoa-tabs">
                <div class="kealoa-tab-nav">
                    <button class="kealoa-tab-button active" data-tab="rounds-played"><?php esc_html_e('Rounds Played', 'kealoa-reference'); ?></button>
                    <button class="kealoa-tab-button" data-tab="stats"><?php esc_html_e('Stats', 'kealoa-reference'); ?></button>
                </div>

                <div class="kealoa-tab-panel" data-tab="stats">

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
                            <td><?php echo esc_html($ys->total_rounds); ?></td>
                            <td><?php echo esc_html($ys->total_clues); ?></td>
                            <td><?php echo esc_html($ys->total_guesses); ?></td>
                            <td><?php echo esc_html($ys->total_correct); ?></td>
                            <td>
                                <?php
                                $pct = (int) $ys->total_guesses > 0
                                    ? ((int) $ys->total_correct / (int) $ys->total_guesses) * 100
                                    : 0;
                                echo Kealoa_Formatter::format_percentage($pct);
                                ?>
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
            <table class="kealoa-table kealoa-rounds-table">
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
                        <strong class="kealoa-meta-label"><?php esc_html_e('Clue Giver', 'kealoa-reference'); ?></strong>
                        <span><?php
                        if ($clue_giver) {
                            echo Kealoa_Formatter::format_person_link((int) $clue_giver->id, $clue_giver->full_name);
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
                            $player_img_url = Kealoa_Formatter::xwordinfo_image_url_from_name($player->full_name);
                            $player_img_source = 'xwordinfo';
                        }
                        ?>
                        <?php
                        $player_slug = str_replace(' ', '_', $player->full_name);
                        $player_url = home_url('/kealoa/person/' . urlencode($player_slug) . '/');
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
                                        if (!empty($clue->editor_name)) {
                                            echo Kealoa_Formatter::format_editor_link($clue->editor_name);
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

        // Check if this person is also a constructor
        $matching_constructor = $this->db->get_constructor_by_name($person->full_name);

        // Check if this person is also an editor
        $is_editor = $this->db->editor_name_exists($person->full_name);

        $person_puzzles = $this->db->get_person_puzzles($person_id);
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

        if (empty($person_image_source) && empty($person->hide_xwordinfo)) {
            // Derive XWordInfo image URL from person's name
            $person_media_url = Kealoa_Formatter::xwordinfo_image_url_from_name($person->full_name);
            $person_image_source = 'xwordinfo';
        }

        ob_start();
        ?>
        <div class="kealoa-person-view">
            <div class="kealoa-person-header">
                <div class="kealoa-person-info">
                    <?php if (!empty($person_media_url)): ?>
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

                        <?php if ($matching_constructor): ?>
                            <p class="kealoa-cross-link-text">
                                <a href="<?php echo esc_url(home_url('/kealoa/constructor/' . urlencode(str_replace(' ', '_', $matching_constructor->full_name)) . '/')); ?>">
                                    <?php esc_html_e('View as Constructor', 'kealoa-reference'); ?>
                                </a>
                            </p>
                        <?php endif; ?>

                        <?php if ($is_editor): ?>
                            <p class="kealoa-cross-link-text">
                                <a href="<?php echo esc_url(home_url('/kealoa/editor/' . urlencode($person->full_name) . '/')); ?>">
                                    <?php esc_html_e('View as Editor', 'kealoa-reference'); ?>
                                </a>
                            </p>
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
                    <button class="kealoa-tab-button active" data-tab="player"><?php esc_html_e('Overall Stats', 'kealoa-reference'); ?></button>
                    <button class="kealoa-tab-button" data-tab="puzzle"><?php esc_html_e('Puzzle Stats', 'kealoa-reference'); ?></button>
                    <button class="kealoa-tab-button" data-tab="round"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></button>
                    <button class="kealoa-tab-button" data-tab="puzzles"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></button>
                    <button class="kealoa-tab-button" data-tab="constructor"><?php esc_html_e('Constructors', 'kealoa-reference'); ?></button>
                    <button class="kealoa-tab-button" data-tab="editor"><?php esc_html_e('Editors', 'kealoa-reference'); ?></button>
                </div>

                <div class="kealoa-tab-panel active" data-tab="player">

            <div class="kealoa-person-stats">
                <h2><?php esc_html_e('KEALOA Statistics', 'kealoa-reference'); ?></h2>

                <div class="kealoa-stats-grid">
                    <div class="kealoa-stat-card">
                        <span class="kealoa-stat-value"><?php echo esc_html($stats->rounds_played); ?></span>
                        <span class="kealoa-stat-label"><?php esc_html_e('Rounds Played', 'kealoa-reference'); ?></span>
                    </div>
                    <div class="kealoa-stat-card">
                        <span class="kealoa-stat-value"><?php echo esc_html($stats->total_clues_answered); ?></span>
                        <span class="kealoa-stat-label"><?php esc_html_e('Clues Answered', 'kealoa-reference'); ?></span>
                    </div>
                    <div class="kealoa-stat-card">
                        <span class="kealoa-stat-value"><?php echo esc_html($stats->total_correct); ?></span>
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
                                <th data-sort="number"><?php esc_html_e('Answered', 'kealoa-reference'); ?></th>
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
                                    <td>
                                        <?php
                                        $pct = $result->total_answered > 0
                                            ? ($result->correct_count / $result->total_answered) * 100
                                            : 0;
                                        echo Kealoa_Formatter::format_percentage($pct);
                                        ?>
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
                                <th data-sort="number"><?php esc_html_e('Answered', 'kealoa-reference'); ?></th>
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
                                    <td>
                                        <?php
                                        $pct = $result->total_answered > 0
                                            ? ($result->correct_count / $result->total_answered) * 100
                                            : 0;
                                        echo Kealoa_Formatter::format_percentage($pct);
                                        ?>
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
                                <th data-sort="number"><?php esc_html_e('Answered', 'kealoa-reference'); ?></th>
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
                                    <td>
                                        <?php
                                        $pct = $result->total_answered > 0
                                            ? ($result->correct_count / $result->total_answered) * 100
                                            : 0;
                                        echo Kealoa_Formatter::format_percentage($pct);
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            </div>

                </div><!-- end Player tab -->

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
                                        if (!empty($puzzle->editor_name)) {
                                            echo Kealoa_Formatter::format_editor_link($puzzle->editor_name);
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
                                <th data-sort="number"><?php esc_html_e('Answered', 'kealoa-reference'); ?></th>
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
                                    <td>
                                        <?php
                                        $pct = $result->total_answered > 0
                                            ? ($result->correct_count / $result->total_answered) * 100
                                            : 0;
                                        echo Kealoa_Formatter::format_percentage($pct);
                                        ?>
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
                                <th data-sort="number"><?php esc_html_e('Answered', 'kealoa-reference'); ?></th>
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
                                    <td>
                                        <?php
                                        $pct = $result->total_answered > 0
                                            ? ($result->correct_count / $result->total_answered) * 100
                                            : 0;
                                        echo Kealoa_Formatter::format_percentage($pct);
                                        ?>
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
                                <th data-sort="number"><?php esc_html_e('Answered', 'kealoa-reference'); ?></th>
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
                                    <td>
                                        <?php
                                        $pct = $result->total_answered > 0
                                            ? ($result->correct_count / $result->total_answered) * 100
                                            : 0;
                                        echo Kealoa_Formatter::format_percentage($pct);
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

                </div><!-- end Puzzle tab -->

                <div class="kealoa-tab-panel" data-tab="constructor">

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
                                <label for="kealoa-constructor-min-answered"><?php esc_html_e('Min. Answered', 'kealoa-reference'); ?></label>
                                <input type="number" id="kealoa-constructor-min-answered" class="kealoa-filter-input" data-filter="min" data-col="1" min="1" placeholder="<?php esc_attr_e('e.g. 5', 'kealoa-reference'); ?>">
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
                                <th data-sort="number"><?php esc_html_e('Answered', 'kealoa-reference'); ?></th>
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
                                    <td>
                                        <?php
                                        $pct = $result->total_answered > 0
                                            ? ($result->correct_count / $result->total_answered) * 100
                                            : 0;
                                        echo Kealoa_Formatter::format_percentage($pct);
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

                </div><!-- end Constructor tab -->

                <div class="kealoa-tab-panel" data-tab="editor">

            <?php if (!empty($editor_results)): ?>
                <div class="kealoa-editor-stats">
                    <h2><?php esc_html_e('Results by Editor', 'kealoa-reference'); ?></h2>

                    <table class="kealoa-table kealoa-editor-table">
                        <thead>
                            <tr>
                                <th data-sort="text"><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Answered', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($editor_results as $result): ?>
                                <tr>
                                    <td><?php echo Kealoa_Formatter::format_editor_link($result->editor_name); ?></td>
                                    <td><?php echo esc_html($result->total_answered); ?></td>
                                    <td><?php echo esc_html($result->correct_count); ?></td>
                                    <td>
                                        <?php
                                        $pct = $result->total_answered > 0
                                            ? ($result->correct_count / $result->total_answered) * 100
                                            : 0;
                                        echo Kealoa_Formatter::format_percentage($pct);
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

                </div><!-- end Editor tab -->

                <div class="kealoa-tab-panel" data-tab="round">

            <?php if (!empty($round_history)): ?>
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
                                <input type="number" id="kealoa-rh-min-correct" class="kealoa-filter-input" data-filter="min" data-col="2" min="0" placeholder="<?php esc_attr_e('e.g. 5', 'kealoa-reference'); ?>">
                            </div>
                        </div>
                        <div class="kealoa-filter-row">
                            <div class="kealoa-filter-group">
                                <label for="kealoa-rh-min-streak"><?php esc_html_e('Min. Streak', 'kealoa-reference'); ?></label>
                                <input type="number" id="kealoa-rh-min-streak" class="kealoa-filter-input" data-filter="min" data-col="3" min="0" placeholder="<?php esc_attr_e('e.g. 3', 'kealoa-reference'); ?>">
                            </div>
                            <div class="kealoa-filter-group">
                                <label for="kealoa-rh-perfect"><?php esc_html_e('Score', 'kealoa-reference'); ?></label>
                                <select id="kealoa-rh-perfect" class="kealoa-filter-select" data-filter="perfect" data-col="2">
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
                                    <td data-total="<?php echo esc_attr((int) $history->total_clues); ?>"><?php echo esc_html($history->correct_count); ?></td>
                                    <td><?php echo esc_html($this->db->get_person_round_streak((int) $history->round_id, $person_id)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

                </div><!-- end Round tab -->
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
            <table class="kealoa-table kealoa-persons-table">
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
                            <td><?php echo esc_html($person->rounds_played); ?></td>
                            <td><?php echo esc_html($person->clues_guessed); ?></td>
                            <td><?php echo esc_html($person->correct_guesses); ?></td>
                            <td>
                                <?php
                                $accuracy = $person->clues_guessed > 0
                                    ? ($person->correct_guesses / $person->clues_guessed) * 100
                                    : 0;
                                echo Kealoa_Formatter::format_percentage((float) $accuracy);
                                ?>
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

        $constructors = $this->db->get_constructors_with_stats();

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
                    <div class="kealoa-filter-group">
                        <label for="kealoa-con-min-clues"><?php esc_html_e('Min. Clues', 'kealoa-reference'); ?></label>
                        <input type="number" id="kealoa-con-min-clues" class="kealoa-filter-input" data-filter="min" data-col="2" min="1" placeholder="<?php esc_attr_e('e.g. 5', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-con-min-guesses"><?php esc_html_e('Min. Guesses', 'kealoa-reference'); ?></label>
                        <input type="number" id="kealoa-con-min-guesses" class="kealoa-filter-input" data-filter="min" data-col="3" min="1" placeholder="<?php esc_attr_e('e.g. 10', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-con-acc-min"><?php esc_html_e('Accuracy Range', 'kealoa-reference'); ?></label>
                        <div class="kealoa-filter-range">
                            <input type="number" id="kealoa-con-acc-min" class="kealoa-filter-input" data-filter="range-min" data-col="5" min="0" max="100" placeholder="<?php esc_attr_e('0%', 'kealoa-reference'); ?>">
                            <span class="kealoa-filter-range-sep">&ndash;</span>
                            <input type="number" id="kealoa-con-acc-max" class="kealoa-filter-input" data-filter="range-max" data-col="5" min="0" max="100" placeholder="<?php esc_attr_e('100%', 'kealoa-reference'); ?>">
                        </div>
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
                    <?php foreach ($constructors as $constructor): ?>
                        <?php
                        $accuracy = $constructor->total_guesses > 0
                            ? ($constructor->correct_guesses / $constructor->total_guesses) * 100
                            : 0;
                        ?>
                        <tr>
                            <td>
                                <?php echo Kealoa_Formatter::format_constructor_link((int) $constructor->id, $constructor->full_name); ?>
                            </td>
                            <td><?php echo esc_html($constructor->puzzle_count); ?></td>
                            <td><?php echo esc_html($constructor->clue_count); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $constructor->total_guesses)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $constructor->correct_guesses)); ?></td>
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
     * Render single constructor shortcode
     *
     * [kealoa_constructor id="X"]
     */
    public function render_constructor(array $atts = []): string {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts, 'kealoa_constructor');

        $constructor_id = (int) $atts['id'];
        if (!$constructor_id) {
            return '<p class="kealoa-error">' . esc_html__('Please specify a constructor ID.', 'kealoa-reference') . '</p>';
        }

        $constructor = $this->db->get_constructor($constructor_id);
        if (!$constructor) {
            return '<p class="kealoa-error">' . esc_html__('Constructor not found.', 'kealoa-reference') . '</p>';
        }

        return $this->get_cached_or_render('constructor_' . $constructor_id, function () use ($constructor_id, $constructor) {

        $puzzles = $this->db->get_constructor_puzzles($constructor_id);
        $stats = $this->db->get_constructor_stats($constructor_id);
        $player_results = $this->db->get_constructor_player_results($constructor_id);
        $editor_results = $this->db->get_constructor_editor_results($constructor_id);

        // Check if this constructor is also a player (person)
        $matching_person = $this->db->get_person_by_name($constructor->full_name);

        // Check if this constructor is also an editor
        $is_constructor_editor = $this->db->editor_name_exists($constructor->full_name);

        // Determine image: media library > XWordInfo
        $con_media_id = (int) ($constructor->media_id ?? 0);
        $con_image_url = '';
        $con_image_source = ''; // 'media', 'xwordinfo', or ''

        if ($con_media_id > 0) {
            $media_src = wp_get_attachment_image_src($con_media_id, 'medium');
            if ($media_src) {
                $con_image_url = $media_src[0];
                $con_image_source = 'media';
            }
        }

        if (empty($con_image_source)) {
            $con_image_url = Kealoa_Formatter::xwordinfo_image_url_from_name($constructor->full_name);
            $con_image_source = 'xwordinfo';
        }

        ob_start();
        ?>
        <div class="kealoa-constructor-view">
            <div class="kealoa-constructor-header">
                <div class="kealoa-constructor-info">
                    <?php if (!empty($con_image_url)): ?>
                        <div class="kealoa-constructor-image">
                            <?php if ($con_image_source === 'xwordinfo'): ?>
                                <?php echo Kealoa_Formatter::format_xwordinfo_image($con_image_url, $constructor->full_name); ?>
                            <?php else: ?>
                                <img src="<?php echo esc_url($con_image_url); ?>"
                                     alt="<?php echo esc_attr($constructor->full_name); ?>"
                                     class="kealoa-entity-image" />
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="kealoa-constructor-details">
                        <h2 class="kealoa-constructor-name"><?php echo esc_html($constructor->full_name); ?></h2>

                        <?php if ($matching_person): ?>
                            <p class="kealoa-cross-link-text">
                                <a href="<?php echo esc_url(home_url('/kealoa/person/' . urlencode(str_replace(' ', '_', $matching_person->full_name)) . '/')); ?>">
                                    <?php esc_html_e('View as Player', 'kealoa-reference'); ?>
                                </a>
                            </p>
                        <?php endif; ?>

                        <?php if ($is_constructor_editor): ?>
                            <p class="kealoa-cross-link-text">
                                <a href="<?php echo esc_url(home_url('/kealoa/editor/' . urlencode($constructor->full_name) . '/')); ?>">
                                    <?php esc_html_e('View as Editor', 'kealoa-reference'); ?>
                                </a>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($constructor->xwordinfo_profile_name)): ?>
                            <p class="kealoa-constructor-xwordinfo">
                                <?php echo Kealoa_Formatter::format_xwordinfo_link($constructor->xwordinfo_profile_name, 'XWordInfo Profile'); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($matching_person && !empty($matching_person->home_page_url)): ?>
                            <p class="kealoa-constructor-xwordinfo">
                                <?php echo Kealoa_Formatter::format_home_page_link($matching_person->home_page_url); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($stats): ?>
            <div class="kealoa-stats-grid">
                <div class="kealoa-stat-card">
                    <span class="kealoa-stat-value"><?php echo esc_html($stats->puzzle_count); ?></span>
                    <span class="kealoa-stat-label"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></span>
                </div>
                <div class="kealoa-stat-card">
                    <span class="kealoa-stat-value"><?php echo esc_html($stats->clue_count); ?></span>
                    <span class="kealoa-stat-label"><?php esc_html_e('Clues', 'kealoa-reference'); ?></span>
                </div>
                <div class="kealoa-stat-card">
                    <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n($stats->total_guesses)); ?></span>
                    <span class="kealoa-stat-label"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></span>
                </div>
                <div class="kealoa-stat-card">
                    <span class="kealoa-stat-value"><?php echo esc_html($stats->correct_guesses); ?></span>
                    <span class="kealoa-stat-label"><?php esc_html_e('Correct', 'kealoa-reference'); ?></span>
                </div>
                <div class="kealoa-stat-card">
                    <span class="kealoa-stat-value"><?php
                        $accuracy = (int) $stats->total_guesses > 0
                            ? ((int) $stats->correct_guesses / (int) $stats->total_guesses) * 100
                            : 0;
                        echo Kealoa_Formatter::format_percentage($accuracy);
                    ?></span>
                    <span class="kealoa-stat-label"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <div class="kealoa-tabs">
                <div class="kealoa-tab-nav">
                    <button class="kealoa-tab-button active" data-tab="puzzles"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></button>
                    <button class="kealoa-tab-button" data-tab="by-player"><?php esc_html_e('Players', 'kealoa-reference'); ?></button>
                    <button class="kealoa-tab-button" data-tab="by-editor"><?php esc_html_e('Editors', 'kealoa-reference'); ?></button>
                </div>

                <div class="kealoa-tab-panel active" data-tab="puzzles">

            <?php if (!empty($puzzles)): ?>
                <?php
                // Check if any puzzle has co-constructors
                $has_co_constructors = false;
                foreach ($puzzles as $p_check) {
                    $co_check = $this->db->get_puzzle_co_constructors((int) $p_check->puzzle_id, $constructor_id);
                    if (!empty($co_check)) {
                        $has_co_constructors = true;
                        break;
                    }
                }
                ?>
                <div class="kealoa-constructor-puzzles">
                    <h2><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></h2>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-constructor-puzzles-table">
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
                            <?php foreach ($puzzles as $puzzle): ?>
                                <?php
                                $co_constructors = $this->db->get_puzzle_co_constructors((int) $puzzle->puzzle_id, $constructor_id);
                                $round_ids = !empty($puzzle->round_ids) ? explode(',', $puzzle->round_ids) : [];
                                $round_dates = !empty($puzzle->round_dates) ? explode(',', $puzzle->round_dates) : [];
                                $round_numbers = !empty($puzzle->round_numbers) ? explode(',', $puzzle->round_numbers) : [];
                                ?>
                                <tr>
                                    <td class="kealoa-day-cell"><?php echo esc_html(Kealoa_Formatter::format_day_abbrev($puzzle->publication_date)); ?></td>
                                    <td>
                                        <?php echo Kealoa_Formatter::format_puzzle_date_link($puzzle->publication_date); ?>
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
                                        if (!empty($puzzle->editor_name)) {
                                            echo Kealoa_Formatter::format_editor_link($puzzle->editor_name);
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
            <?php else: ?>
                <p class="kealoa-no-data"><?php esc_html_e('No puzzles found for this constructor.', 'kealoa-reference'); ?></p>
            <?php endif; ?>

                </div><!-- end Puzzles tab -->

                <div class="kealoa-tab-panel" data-tab="by-player">

            <?php if (!empty($player_results)): ?>
                <div class="kealoa-constructor-player-stats">
                    <h2><?php esc_html_e('Results by Player', 'kealoa-reference'); ?></h2>

                    <table class="kealoa-table kealoa-constructor-player-table">
                        <thead>
                            <tr>
                                <th data-sort="text"><?php esc_html_e('Player', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Answered', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($player_results as $result): ?>
                                <tr>
                                    <td><?php echo Kealoa_Formatter::format_person_link((int) $result->person_id, $result->full_name); ?></td>
                                    <td><?php echo esc_html($result->total_answered); ?></td>
                                    <td><?php echo esc_html($result->correct_count); ?></td>
                                    <td>
                                        <?php
                                        $pct = $result->total_answered > 0
                                            ? ($result->correct_count / $result->total_answered) * 100
                                            : 0;
                                        echo Kealoa_Formatter::format_percentage($pct);
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

                </div><!-- end By Player tab -->

                <div class="kealoa-tab-panel" data-tab="by-editor">

            <?php if (!empty($editor_results)): ?>
                <div class="kealoa-constructor-editor-stats">
                    <h2><?php esc_html_e('Results by Editor', 'kealoa-reference'); ?></h2>

                    <table class="kealoa-table kealoa-constructor-editor-table">
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
                            <?php foreach ($editor_results as $result): ?>
                                <tr>
                                    <td><?php echo Kealoa_Formatter::format_editor_link($result->editor_name); ?></td>
                                    <td><?php echo esc_html($result->puzzle_count); ?></td>
                                    <td><?php echo esc_html($result->clue_count); ?></td>
                                    <td><?php echo esc_html($result->total_guesses); ?></td>
                                    <td><?php echo esc_html($result->correct_guesses); ?></td>
                                    <td>
                                        <?php
                                        $pct = $result->total_guesses > 0
                                            ? ($result->correct_guesses / $result->total_guesses) * 100
                                            : 0;
                                        echo Kealoa_Formatter::format_percentage($pct);
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

                </div><!-- end By Editor tab -->
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

        $editors = $this->db->get_editors_with_stats();

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
                    <div class="kealoa-filter-group">
                        <label for="kealoa-ed-min-puzzles"><?php esc_html_e('Min. Puzzles', 'kealoa-reference'); ?></label>
                        <input type="number" id="kealoa-ed-min-puzzles" class="kealoa-filter-input" data-filter="min" data-col="1" min="1" placeholder="<?php esc_attr_e('e.g. 3', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-ed-min-clues"><?php esc_html_e('Min. Clues', 'kealoa-reference'); ?></label>
                        <input type="number" id="kealoa-ed-min-clues" class="kealoa-filter-input" data-filter="min" data-col="2" min="1" placeholder="<?php esc_attr_e('e.g. 5', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-ed-min-guesses"><?php esc_html_e('Min. Guesses', 'kealoa-reference'); ?></label>
                        <input type="number" id="kealoa-ed-min-guesses" class="kealoa-filter-input" data-filter="min" data-col="3" min="1" placeholder="<?php esc_attr_e('e.g. 10', 'kealoa-reference'); ?>">
                    </div>
                    <div class="kealoa-filter-group">
                        <label for="kealoa-ed-acc-min"><?php esc_html_e('Accuracy Range', 'kealoa-reference'); ?></label>
                        <div class="kealoa-filter-range">
                            <input type="number" id="kealoa-ed-acc-min" class="kealoa-filter-input" data-filter="range-min" data-col="5" min="0" max="100" placeholder="<?php esc_attr_e('0%', 'kealoa-reference'); ?>">
                            <span class="kealoa-filter-range-sep">&ndash;</span>
                            <input type="number" id="kealoa-ed-acc-max" class="kealoa-filter-input" data-filter="range-max" data-col="5" min="0" max="100" placeholder="<?php esc_attr_e('100%', 'kealoa-reference'); ?>">
                        </div>
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
                    <?php foreach ($editors as $editor): ?>
                        <?php
                        $accuracy = $editor->total_guesses > 0
                            ? ($editor->correct_guesses / $editor->total_guesses) * 100
                            : 0;
                        ?>
                        <tr>
                            <td>
                                <?php echo Kealoa_Formatter::format_editor_link($editor->editor_name); ?>
                            </td>
                            <td><?php echo esc_html($editor->puzzle_count); ?></td>
                            <td><?php echo esc_html($editor->clue_count); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $editor->total_guesses)); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) $editor->correct_guesses)); ?></td>
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
     * Render puzzles table shortcode
     *
     * [kealoa_puzzles_table]
     */
    public function render_puzzles_table(array $atts = []): string {
        return $this->get_cached_or_render('puzzles_table', function () {

        $puzzles = $this->db->get_all_puzzles_with_details();

        if (empty($puzzles)) {
            return '<p class="kealoa-no-data">' . esc_html__('No puzzles found.', 'kealoa-reference') . '</p>';
        }

        ob_start();
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
                                <?php echo Kealoa_Formatter::format_editor_link($puzzle->editor_name); ?>
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
        <?php
        return ob_get_clean();

        }); // end get_cached_or_render
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

        // Determine editor image: media library > XWordInfo
        $editor_image_url = '';
        $editor_image_source = '';
        if (!empty($puzzle->editor_name)) {
            $editor_media_id = $this->db->get_editor_media_id($puzzle->editor_name);
            if ($editor_media_id > 0) {
                $media_src = wp_get_attachment_image_src($editor_media_id, 'medium');
                if ($media_src) {
                    $editor_image_url = $media_src[0];
                    $editor_image_source = 'media';
                }
            }
            if (empty($editor_image_source)) {
                $editor_image_url = Kealoa_Formatter::xwordinfo_image_url_from_name($puzzle->editor_name);
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

                        <?php if (!empty($puzzle->editor_name)): ?>
                        <p>
                            <strong class="kealoa-meta-label"><?php esc_html_e('Editor', 'kealoa-reference'); ?></strong>
                            <span><?php echo Kealoa_Formatter::format_editor_link($puzzle->editor_name); ?></span>
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
                        $con_url = home_url('/kealoa/constructor/' . urlencode($con_slug) . '/');
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

                    <?php if (!empty($editor_image_url)): ?>
                        <?php $editor_url = home_url('/kealoa/editor/' . urlencode($puzzle->editor_name) . '/'); ?>
                        <div class="kealoa-puzzle-constructor">
                            <a href="<?php echo esc_url($editor_url); ?>">
                                <?php if ($editor_image_source === 'media'): ?>
                                    <img src="<?php echo esc_url($editor_image_url); ?>"
                                         alt="<?php echo esc_attr($puzzle->editor_name); ?>"
                                         class="kealoa-entity-image" />
                                <?php else: ?>
                                    <?php echo Kealoa_Formatter::format_xwordinfo_image($editor_image_url, $puzzle->editor_name); ?>
                                <?php endif; ?>
                            </a>
                            <span class="kealoa-puzzle-constructor-name"><?php echo esc_html($puzzle->editor_name); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($clues)): ?>
            <div class="kealoa-tabs">
                <div class="kealoa-tab-nav">
                    <button class="kealoa-tab-button active" data-tab="clues"><?php esc_html_e('Clues', 'kealoa-reference'); ?></button>
                    <button class="kealoa-tab-button" data-tab="by-player"><?php esc_html_e('Players', 'kealoa-reference'); ?></button>
                </div>

                <div class="kealoa-tab-panel active" data-tab="clues">

            <?php foreach ($rounds_clues as $rc): ?>
                <div class="kealoa-puzzle-round-clues">
                    <h3>
                        <?php
                        $round_date_link = Kealoa_Formatter::format_round_date_link((int) $rc['round_id'], $rc['round_date']);
                        $solutions = $this->db->get_round_solutions((int) $rc['round_id']);
                        $solution_words = Kealoa_Formatter::format_solution_words($solutions);

                        // Check if multiple rounds share the same date
                        $same_date_count = 0;
                        foreach ($rounds_clues as $other_rc) {
                            if ($other_rc['round_date'] === $rc['round_date']) {
                                $same_date_count++;
                            }
                        }

                        if ($same_date_count > 1) {
                            echo $round_date_link . ' (#' . esc_html($rc['round_number']) . ') — ' . esc_html($solution_words);
                        } else {
                            echo $round_date_link . ' — ' . esc_html($solution_words);
                        }
                        ?>
                    </h3>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-puzzle-clues-table">
                        <thead>
                            <tr>
                                <th data-sort="number"><?php esc_html_e('#', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Clue Ref', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Clue Text', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Answer', 'kealoa-reference'); ?></th>
                                <?php
                                // Get guessers for this round
                                $round_guessers = $this->db->get_round_guessers((int) $rc['round_id']);
                                foreach ($round_guessers as $guesser):
                                ?>
                                    <th class="kealoa-guesser-col">
                                        <?php echo Kealoa_Formatter::format_person_link((int) $guesser->id, $guesser->full_name); ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rc['clues'] as $clue): ?>
                                <?php $clue_guesses = $this->db->get_clue_guesses((int) $clue->id); ?>
                                <tr>
                                    <td class="kealoa-clue-number"><?php echo esc_html($clue->clue_number); ?></td>
                                    <td class="kealoa-clue-ref">
                                        <?php echo esc_html(Kealoa_Formatter::format_clue_direction((int) $clue->puzzle_clue_number, $clue->puzzle_clue_direction)); ?>
                                    </td>
                                    <td class="kealoa-clue-text"><?php echo esc_html($clue->clue_text); ?></td>
                                    <td class="kealoa-correct-answer">
                                        <strong><?php echo esc_html($clue->correct_answer); ?></strong>
                                    </td>
                                    <?php foreach ($round_guessers as $guesser): ?>
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
                                                $is_correct = (bool) $guess->is_correct;
                                                $css_class = $is_correct ? 'kealoa-guess-correct' : 'kealoa-guess-incorrect';
                                                echo '<span class="' . $css_class . '">' . esc_html($guess->guess_text) . '</span>';
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
            <?php endforeach; ?>

                </div><!-- end Clues tab -->

                <div class="kealoa-tab-panel" data-tab="by-player">

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
                                    <td>
                                        <?php
                                        $pct = $result->total_guesses > 0
                                            ? ($result->correct_guesses / $result->total_guesses) * 100
                                            : 0;
                                        echo Kealoa_Formatter::format_percentage($pct);
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

                </div><!-- end By Player tab -->
            </div><!-- end kealoa-tabs -->
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();

        }); // end get_cached_or_render
    }

    /**
     * Render single editor view shortcode
     *
     * [kealoa_editor name="Editor Name"]
     */
    public function render_editor(array $atts = []): string {
        $atts = shortcode_atts([
            'name' => '',
        ], $atts, 'kealoa_editor');

        $editor_name = sanitize_text_field($atts['name']);
        if (empty($editor_name)) {
            return '<p class="kealoa-error">' . esc_html__('Please specify an editor name.', 'kealoa-reference') . '</p>';
        }

        return $this->get_cached_or_render('editor_' . md5($editor_name), function () use ($editor_name) {

        $puzzles = $this->db->get_editor_puzzles($editor_name);
        $stats = $this->db->get_editor_stats($editor_name);
        $player_results = $this->db->get_editor_player_results($editor_name);
        $constructor_results = $this->db->get_editor_constructor_results($editor_name);

        // Check if this editor is also a player or constructor
        $matching_editor_person = $this->db->get_person_by_name($editor_name);
        $matching_editor_constructor = $this->db->get_constructor_by_name($editor_name);

        // Determine editor image: media library > XWordInfo
        $editor_media_id = $this->db->get_editor_media_id($editor_name);
        $editor_image_url = '';
        $editor_image_source = ''; // 'media', 'xwordinfo', or ''

        if ($editor_media_id > 0) {
            $media_src = wp_get_attachment_image_src($editor_media_id, 'medium');
            if ($media_src) {
                $editor_image_url = $media_src[0];
                $editor_image_source = 'media';
            }
        }

        if (empty($editor_image_source)) {
            $editor_image_url = Kealoa_Formatter::xwordinfo_image_url_from_name($editor_name);
            $editor_image_source = 'xwordinfo';
        }

        ob_start();
        ?>
        <div class="kealoa-editor-view">
            <div class="kealoa-editor-header">
                <div class="kealoa-editor-info">
                    <?php if (!empty($editor_image_url)): ?>
                        <div class="kealoa-editor-image">
                            <?php if ($editor_image_source === 'xwordinfo'): ?>
                                <?php echo Kealoa_Formatter::format_xwordinfo_image($editor_image_url, $editor_name); ?>
                            <?php else: ?>
                                <img src="<?php echo esc_url($editor_image_url); ?>"
                                     alt="<?php echo esc_attr($editor_name); ?>"
                                     class="kealoa-entity-image" />
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="kealoa-editor-details">
                        <h2 class="kealoa-editor-name"><?php echo esc_html($editor_name); ?></h2>

                        <?php if ($matching_editor_person): ?>
                            <p class="kealoa-cross-link-text">
                                <a href="<?php echo esc_url(home_url('/kealoa/person/' . urlencode(str_replace(' ', '_', $matching_editor_person->full_name)) . '/')); ?>">
                                    <?php esc_html_e('View as Player', 'kealoa-reference'); ?>
                                </a>
                            </p>
                        <?php endif; ?>

                        <?php if ($matching_editor_constructor): ?>
                            <p class="kealoa-cross-link-text">
                                <a href="<?php echo esc_url(home_url('/kealoa/constructor/' . urlencode(str_replace(' ', '_', $matching_editor_constructor->full_name)) . '/')); ?>">
                                    <?php esc_html_e('View as Constructor', 'kealoa-reference'); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($stats): ?>
            <div class="kealoa-stats-grid">
                <div class="kealoa-stat-card">
                    <span class="kealoa-stat-value"><?php echo esc_html($stats->puzzle_count); ?></span>
                    <span class="kealoa-stat-label"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></span>
                </div>
                <div class="kealoa-stat-card">
                    <span class="kealoa-stat-value"><?php echo esc_html($stats->clue_count); ?></span>
                    <span class="kealoa-stat-label"><?php esc_html_e('Clues', 'kealoa-reference'); ?></span>
                </div>
                <div class="kealoa-stat-card">
                    <span class="kealoa-stat-value"><?php echo esc_html(number_format_i18n($stats->total_guesses)); ?></span>
                    <span class="kealoa-stat-label"><?php esc_html_e('Guesses', 'kealoa-reference'); ?></span>
                </div>
                <div class="kealoa-stat-card">
                    <span class="kealoa-stat-value"><?php echo esc_html($stats->correct_guesses); ?></span>
                    <span class="kealoa-stat-label"><?php esc_html_e('Correct', 'kealoa-reference'); ?></span>
                </div>
                <div class="kealoa-stat-card">
                    <span class="kealoa-stat-value"><?php
                        $accuracy = (int) $stats->total_guesses > 0
                            ? ((int) $stats->correct_guesses / (int) $stats->total_guesses) * 100
                            : 0;
                        echo Kealoa_Formatter::format_percentage($accuracy);
                    ?></span>
                    <span class="kealoa-stat-label"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <div class="kealoa-tabs">
                <div class="kealoa-tab-nav">
                    <button class="kealoa-tab-button active" data-tab="puzzles"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></button>
                    <button class="kealoa-tab-button" data-tab="by-player"><?php esc_html_e('Players', 'kealoa-reference'); ?></button>
                    <button class="kealoa-tab-button" data-tab="by-constructor"><?php esc_html_e('Constructors', 'kealoa-reference'); ?></button>
                </div>

                <div class="kealoa-tab-panel active" data-tab="puzzles">

            <?php if (!empty($puzzles)): ?>
                <div class="kealoa-editor-puzzles">
                    <h2><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></h2>

                    <div class="kealoa-filter-controls" data-target="kealoa-editor-puzzles-table">
                        <div class="kealoa-filter-row">
                            <div class="kealoa-filter-group">
                                <label for="kealoa-ep-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                                <input type="text" id="kealoa-ep-search" class="kealoa-filter-input" data-filter="search" data-col="2" placeholder="<?php esc_attr_e('Constructor name...', 'kealoa-reference'); ?>">
                            </div>
                            <div class="kealoa-filter-group">
                                <label for="kealoa-ep-day"><?php esc_html_e('Day', 'kealoa-reference'); ?></label>
                                <select id="kealoa-ep-day" class="kealoa-filter-select" data-filter="exact" data-col="0">
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
                                <label for="kealoa-ep-search-words"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></label>
                                <input type="text" id="kealoa-ep-search-words" class="kealoa-filter-input" data-filter="search" data-col="4" placeholder="<?php esc_attr_e('e.g. KEALOA', 'kealoa-reference'); ?>">
                            </div>
                            <div class="kealoa-filter-group">
                                <label for="kealoa-ep-date-from"><?php esc_html_e('Publication Date', 'kealoa-reference'); ?></label>
                                <div class="kealoa-filter-range">
                                    <input type="date" id="kealoa-ep-date-from" class="kealoa-filter-input" data-filter="date-min" data-col="1">
                                    <span class="kealoa-filter-range-sep">&ndash;</span>
                                    <input type="date" id="kealoa-ep-date-to" class="kealoa-filter-input" data-filter="date-max" data-col="1">
                                </div>
                            </div>
                            <div class="kealoa-filter-group kealoa-filter-actions">
                                <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                                <span class="kealoa-filter-count"></span>
                            </div>
                        </div>
                    </div>

                    <div class="kealoa-table-scroll">
                    <table class="kealoa-table kealoa-editor-puzzles-table" id="kealoa-editor-puzzles-table">
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
            <?php else: ?>
                <p class="kealoa-no-data"><?php esc_html_e('No puzzles found for this editor.', 'kealoa-reference'); ?></p>
            <?php endif; ?>

                </div><!-- end Puzzles tab -->

                <div class="kealoa-tab-panel" data-tab="by-player">

            <?php if (!empty($player_results)): ?>
                <div class="kealoa-editor-player-stats">
                    <h2><?php esc_html_e('Results by Player', 'kealoa-reference'); ?></h2>

                    <table class="kealoa-table kealoa-editor-player-table">
                        <thead>
                            <tr>
                                <th data-sort="text"><?php esc_html_e('Player', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Answered', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($player_results as $result): ?>
                                <tr>
                                    <td><?php echo Kealoa_Formatter::format_person_link((int) $result->person_id, $result->full_name); ?></td>
                                    <td><?php echo esc_html($result->total_answered); ?></td>
                                    <td><?php echo esc_html($result->correct_count); ?></td>
                                    <td>
                                        <?php
                                        $pct = $result->total_answered > 0
                                            ? ($result->correct_count / $result->total_answered) * 100
                                            : 0;
                                        echo Kealoa_Formatter::format_percentage($pct);
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

                </div><!-- end By Player tab -->

                <div class="kealoa-tab-panel" data-tab="by-constructor">

            <?php if (!empty($constructor_results)): ?>
                <div class="kealoa-editor-constructor-stats">
                    <h2><?php esc_html_e('Results by Constructor', 'kealoa-reference'); ?></h2>

                    <div class="kealoa-filter-controls" data-target="kealoa-editor-constructor-table">
                        <div class="kealoa-filter-row">
                            <div class="kealoa-filter-group">
                                <label for="kealoa-ec-search"><?php esc_html_e('Search', 'kealoa-reference'); ?></label>
                                <input type="text" id="kealoa-ec-search" class="kealoa-filter-input" data-filter="search" data-col="0" placeholder="<?php esc_attr_e('Constructor name...', 'kealoa-reference'); ?>">
                            </div>
                            <div class="kealoa-filter-group">
                                <label for="kealoa-ec-min-clues"><?php esc_html_e('Min. Clues', 'kealoa-reference'); ?></label>
                                <input type="number" id="kealoa-ec-min-clues" class="kealoa-filter-input" data-filter="min" data-col="2" min="1" placeholder="<?php esc_attr_e('e.g. 5', 'kealoa-reference'); ?>">
                            </div>
                            <div class="kealoa-filter-group">
                                <label for="kealoa-ec-acc-min"><?php esc_html_e('Accuracy Range', 'kealoa-reference'); ?></label>
                                <div class="kealoa-filter-range">
                                    <input type="number" id="kealoa-ec-acc-min" class="kealoa-filter-input" data-filter="range-min" data-col="5" min="0" max="100" placeholder="<?php esc_attr_e('0%', 'kealoa-reference'); ?>">
                                    <span class="kealoa-filter-range-sep">&ndash;</span>
                                    <input type="number" id="kealoa-ec-acc-max" class="kealoa-filter-input" data-filter="range-max" data-col="5" min="0" max="100" placeholder="<?php esc_attr_e('100%', 'kealoa-reference'); ?>">
                                </div>
                            </div>
                            <div class="kealoa-filter-group kealoa-filter-actions">
                                <button type="button" class="kealoa-filter-reset"><?php esc_html_e('Reset Filters', 'kealoa-reference'); ?></button>
                                <span class="kealoa-filter-count"></span>
                            </div>
                        </div>
                    </div>

                    <table class="kealoa-table kealoa-editor-constructor-table" id="kealoa-editor-constructor-table">
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
                            <?php foreach ($constructor_results as $result): ?>
                                <tr>
                                    <td><?php echo Kealoa_Formatter::format_constructor_link((int) $result->constructor_id, $result->full_name); ?></td>
                                    <td><?php echo esc_html($result->puzzle_count); ?></td>
                                    <td><?php echo esc_html($result->clue_count); ?></td>
                                    <td><?php echo esc_html($result->total_guesses); ?></td>
                                    <td><?php echo esc_html($result->correct_guesses); ?></td>
                                    <td>
                                        <?php
                                        $pct = $result->total_guesses > 0
                                            ? ($result->correct_guesses / $result->total_guesses) * 100
                                            : 0;
                                        echo Kealoa_Formatter::format_percentage($pct);
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

                </div><!-- end By Constructor tab -->
            </div><!-- end kealoa-tabs -->
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
