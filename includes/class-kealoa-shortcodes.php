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

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Kealoa_DB();
        
        add_shortcode('kealoa_rounds_table', [$this, 'render_rounds_table']);
        add_shortcode('kealoa_round', [$this, 'render_round']);
        add_shortcode('kealoa_person', [$this, 'render_person']);
        add_shortcode('kealoa_persons_table', [$this, 'render_persons_table']);
        add_shortcode('kealoa_constructors_table', [$this, 'render_constructors_table']);
        add_shortcode('kealoa_constructor', [$this, 'render_constructor']);
        add_shortcode('kealoa_editors_table', [$this, 'render_editors_table']);
        add_shortcode('kealoa_editor', [$this, 'render_editor']);
        add_shortcode('kealoa_version', [$this, 'render_version']);
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
        
        $rounds = $this->db->get_rounds([
            'limit' => (int) $atts['limit'],
            'order' => $atts['order'],
        ]);
        
        if (empty($rounds)) {
            return '<p class="kealoa-no-data">' . esc_html__('No KEALOA rounds found.', 'kealoa-reference') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="kealoa-rounds-table-wrapper">
            <table class="kealoa-table kealoa-rounds-table">
                <thead>
                    <tr>
                        <th data-sort="date"><?php esc_html_e('Date', 'kealoa-reference'); ?></th>
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
                            <td class="kealoa-date-cell">
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
        </div>
        <?php
        return ob_get_clean();
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
                <h2 class="kealoa-round-title">
                    <?php 
                    printf(
                        esc_html__('KEALOA #%d: %s', 'kealoa-reference'),
                        $round_id,
                        esc_html(Kealoa_Formatter::format_solution_words($solutions))
                    );
                    ?>
                </h2>
                
                <div class="kealoa-round-meta">
                    <p>
                        <strong><?php esc_html_e('Date:', 'kealoa-reference'); ?></strong>
                        <?php 
                        echo esc_html(Kealoa_Formatter::format_date($round->round_date));
                        if ($show_round_num) {
                            echo ' <span class="kealoa-round-number">(Round #' . esc_html($round_num) . ')</span>';
                        }
                        ?>
                    </p>
                    <p>
                        <strong><?php esc_html_e('Episode:', 'kealoa-reference'); ?></strong>
                        <?php echo Kealoa_Formatter::format_episode_link((int) $round->episode_number, $round->episode_url ?? null); ?>
                    </p>
                    <p>
                        <strong><?php esc_html_e('Solution Words:', 'kealoa-reference'); ?></strong>
                        <?php echo esc_html(Kealoa_Formatter::format_solution_words($solutions)); ?>
                    </p>
                    <p>
                        <strong><?php esc_html_e('Clue Giver:', 'kealoa-reference'); ?></strong>
                        <?php 
                        if ($clue_giver) {
                            echo Kealoa_Formatter::format_person_link((int) $clue_giver->id, $clue_giver->full_name);
                        } else {
                            esc_html_e('Unknown', 'kealoa-reference');
                        }
                        ?>
                    </p>
                    <p>
                        <strong><?php esc_html_e('Guessers:', 'kealoa-reference'); ?></strong>
                        <?php
                        $guesser_parts = [];
                        foreach ($guesser_results as $gr) {
                            $link = Kealoa_Formatter::format_person_link((int) $gr->person_id, $gr->full_name);
                            $correct = (int) $gr->correct_guesses;
                            $total = (int) $gr->total_guesses;
                            $accuracy = $total > 0 ? ($correct / $total) * 100 : 0;
                            $score = sprintf('%d/%d, %s', $correct, $total, Kealoa_Formatter::format_percentage((float) $accuracy));
                            $guesser_parts[] = $link . ' (' . esc_html($score) . ')';
                        }
                        echo implode('; ', $guesser_parts);
                        ?>
                    </p>
                    <?php if (!empty($round->description)): ?>
                        <p>
                            <strong><?php esc_html_e('Description:', 'kealoa-reference'); ?></strong>
                            <?php echo esc_html($round->description); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($round->episode_id)): ?>
                <div class="kealoa-audio-player">
                    <iframe title="Libsyn Player" style="border: none" src="//html5-player.libsyn.com/embed/episode/id/<?php echo esc_attr($round->episode_id); ?>/height/90/theme/custom/thumbnail/yes/direction/forward/render-playlist/no/custom-color/000000/time-start/<?php echo esc_attr(Kealoa_Formatter::seconds_to_time((int) $round->episode_start_seconds)); ?>/" height="90" width="100%" scrolling="no" allowfullscreen webkitallowfullscreen mozallowfullscreen oallowfullscreen msallowfullscreen></iframe>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($clues)): ?>
                <div class="kealoa-clues-section">
                    <h3><?php esc_html_e('Clues', 'kealoa-reference'); ?></h3>
                    
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
            <?php else: ?>
                <p class="kealoa-no-data"><?php esc_html_e('No clues recorded for this round.', 'kealoa-reference'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
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
        
        $stats = $this->db->get_person_stats($person_id);
        $clue_number_results = $this->db->get_person_results_by_clue_number($person_id);
        $direction_results = $this->db->get_person_results_by_direction($person_id);
        $day_of_week_results = $this->db->get_person_results_by_day_of_week($person_id);
        $decade_results = $this->db->get_person_results_by_decade($person_id);
        $year_results = $this->db->get_person_results_by_year($person_id);
        $constructor_results = $this->db->get_person_results_by_constructor($person_id);
        $editor_results = $this->db->get_person_results_by_editor($person_id);
        $round_history = $this->db->get_person_round_history($person_id);
        
        // If person has no image, check for a matching constructor by name
        $person_image_url = $person->xwordinfo_image_url ?? '';
        if (empty($person_image_url)) {
            $matching_constructors = $this->db->get_constructors([
                'search' => $person->full_name,
                'limit' => 1,
            ]);
            foreach ($matching_constructors as $mc) {
                if (strtolower($mc->full_name) === strtolower($person->full_name) && !empty($mc->xwordinfo_image_url)) {
                    $person_image_url = $mc->xwordinfo_image_url;
                    break;
                }
            }
        }
        
        ob_start();
        ?>
        <div class="kealoa-person-view">
            <div class="kealoa-person-header">
                <div class="kealoa-person-info">
                    <?php if (!empty($person_image_url)): ?>
                        <div class="kealoa-person-image">
                            <?php echo Kealoa_Formatter::format_xwordinfo_image($person_image_url, $person->full_name); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="kealoa-person-details">
                        <h2 class="kealoa-person-name"><?php echo esc_html($person->full_name); ?></h2>
                        
                        <?php if (!empty($person->xwordinfo_profile_name)): ?>
                            <p class="kealoa-person-xwordinfo">
                                <strong><?php esc_html_e('XWordInfo:', 'kealoa-reference'); ?></strong>
                                <?php echo Kealoa_Formatter::format_xwordinfo_link($person->xwordinfo_profile_name, 'View Profile'); ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($person->home_page_url)): ?>
                            <p class="kealoa-person-homepage">
                                <strong><?php esc_html_e('Home Page:', 'kealoa-reference'); ?></strong>
                                <?php echo Kealoa_Formatter::format_home_page_link($person->home_page_url); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="kealoa-person-stats">
                <h3><?php esc_html_e('KEALOA Statistics', 'kealoa-reference'); ?></h3>
                
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
                </div>
                
                <h4><?php esc_html_e('Per-Round Statistics', 'kealoa-reference'); ?></h4>
                
                <table class="kealoa-table kealoa-summary-stats-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Metric', 'kealoa-reference'); ?></th>
                            <th><?php esc_html_e('Minimum', 'kealoa-reference'); ?></th>
                            <th><?php esc_html_e('Mean', 'kealoa-reference'); ?></th>
                            <th><?php esc_html_e('Median', 'kealoa-reference'); ?></th>
                            <th><?php esc_html_e('Maximum', 'kealoa-reference'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php esc_html_e('Clues Correct', 'kealoa-reference'); ?></td>
                            <td><?php echo esc_html($stats->min_correct); ?></td>
                            <td><?php echo esc_html($stats->mean_correct); ?></td>
                            <td><?php echo esc_html($stats->median_correct); ?></td>
                            <td><?php echo esc_html($stats->max_correct); ?></td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></td>
                            <td><?php echo Kealoa_Formatter::format_percentage($stats->min_percentage); ?></td>
                            <td><?php echo Kealoa_Formatter::format_percentage($stats->mean_percentage); ?></td>
                            <td><?php echo Kealoa_Formatter::format_percentage($stats->median_percentage); ?></td>
                            <td><?php echo Kealoa_Formatter::format_percentage($stats->max_percentage); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($year_results)): ?>
                <div class="kealoa-year-stats">
                    <h3><?php esc_html_e('Results by Year of Round', 'kealoa-reference'); ?></h3>
                    
                    <table class="kealoa-table kealoa-year-table">
                        <thead>
                            <tr>
                                <th data-sort="number"><?php esc_html_e('Year', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Rounds', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Clues Answered', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($year_results as $result): ?>
                                <tr>
                                    <td><?php echo esc_html($result->year); ?></td>
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
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($clue_number_results)): ?>
                <div class="kealoa-clue-number-stats">
                    <h3><?php esc_html_e('Results by Clue Number', 'kealoa-reference'); ?></h3>
                    
                    <table class="kealoa-table kealoa-clue-number-table">
                        <thead>
                            <tr>
                                <th data-sort="number"><?php esc_html_e('Clue #', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Clues Answered', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clue_number_results as $result): ?>
                                <tr>
                                    <td><?php echo esc_html($result->clue_number); ?></td>
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
                    <h3><?php esc_html_e('Results by Clue Direction', 'kealoa-reference'); ?></h3>
                    
                    <table class="kealoa-table kealoa-direction-table">
                        <thead>
                            <tr>
                                <th data-sort="text"><?php esc_html_e('Direction', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Clues Answered', 'kealoa-reference'); ?></th>
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
            
            <?php if (!empty($day_of_week_results)): ?>
                <div class="kealoa-day-of-week-stats">
                    <h3><?php esc_html_e('Results by Puzzle Day of Week', 'kealoa-reference'); ?></h3>
                    
                    <table class="kealoa-table kealoa-day-of-week-table">
                        <thead>
                            <tr>
                                <th data-sort="weekday"><?php esc_html_e('Day', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Clues Answered', 'kealoa-reference'); ?></th>
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
                    <h3><?php esc_html_e('Results by Puzzle Decade', 'kealoa-reference'); ?></h3>
                    
                    <table class="kealoa-table kealoa-decade-table">
                        <thead>
                            <tr>
                                <th data-sort="number"><?php esc_html_e('Decade', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Clues Answered', 'kealoa-reference'); ?></th>
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
            
            <?php if (!empty($constructor_results)): ?>
                <div class="kealoa-constructor-stats">
                    <h3><?php esc_html_e('Results by Constructor', 'kealoa-reference'); ?></h3>
                    
                    <table class="kealoa-table kealoa-constructor-table">
                        <thead>
                            <tr>
                                <th data-sort="text"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Clues Answered', 'kealoa-reference'); ?></th>
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
            
            <?php if (!empty($editor_results)): ?>
                <div class="kealoa-editor-stats">
                    <h3><?php esc_html_e('Results by Editor', 'kealoa-reference'); ?></h3>
                    
                    <table class="kealoa-table kealoa-editor-table">
                        <thead>
                            <tr>
                                <th data-sort="text"><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Clues Answered', 'kealoa-reference'); ?></th>
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
            
            <?php if (!empty($round_history)): ?>
                <div class="kealoa-accuracy-chart-section">
                    <h3><?php esc_html_e('Accuracy by Round', 'kealoa-reference'); ?></h3>
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
                        $chart_labels[] = Kealoa_Formatter::format_date($ch->round_date);
                        $pct_val = $ch->total_clues > 0
                            ? round(($ch->correct_count / $ch->total_clues) * 100, 1)
                            : 0;
                        $chart_data[] = $pct_val;
                        $ch_solutions = $this->db->get_round_solutions((int) $ch->round_id);
                        $chart_words[] = Kealoa_Formatter::format_solution_words($ch_solutions);
                        $chart_urls[] = home_url('/kealoa/round/' . $ch->round_id . '/');
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
                                        label: <?php echo wp_json_encode(__('Accuracy %', 'kealoa-reference')); ?>,
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
                                                text: <?php echo wp_json_encode(__('Accuracy %', 'kealoa-reference')); ?>
                                            },
                                            min: 0,
                                            max: 100
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
                                                    return item.parsed.y + '%';
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
                
                <div class="kealoa-round-history">
                    <h3><?php esc_html_e('Round History', 'kealoa-reference'); ?></h3>
                    
                    <?php
                    // Check if any round dates have multiple rounds
                    $has_multi_round_dates = false;
                    foreach ($round_history as $h) {
                        $h_rounds = $this->db->get_rounds_by_date($h->round_date);
                        if (count($h_rounds) > 1) {
                            $has_multi_round_dates = true;
                            break;
                        }
                    }
                    ?>
                    <table class="kealoa-table kealoa-history-table">
                        <thead>
                            <tr>
                                <th data-sort="date"><?php esc_html_e('Date', 'kealoa-reference'); ?></th>
                                <?php if ($has_multi_round_dates): ?>
                                    <th data-sort="number"><?php esc_html_e('Round #', 'kealoa-reference'); ?></th>
                                <?php endif; ?>
                                <th data-sort="text"><?php esc_html_e('Solution Words', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Clues', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($round_history as $history): ?>
                                <?php 
                                $solutions = $this->db->get_round_solutions((int) $history->round_id);
                                $history_round_num = (int) ($history->round_number ?? 1);
                                ?>
                                <tr>
                                    <td>
                                        <?php echo Kealoa_Formatter::format_round_date_link((int) $history->round_id, $history->round_date); ?>
                                    </td>
                                    <?php if ($has_multi_round_dates): ?>
                                        <td><?php echo esc_html($history_round_num); ?></td>
                                    <?php endif; ?>
                                    <td><?php echo Kealoa_Formatter::format_solution_words_link((int) $history->round_id, $solutions); ?></td>
                                    <td><?php echo esc_html($history->total_clues); ?></td>
                                    <td><?php echo esc_html($history->correct_count); ?></td>
                                    <td>
                                        <?php 
                                        $pct = $history->total_clues > 0 
                                            ? ($history->correct_count / $history->total_clues) * 100 
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
        <?php
        return ob_get_clean();
    }

    /**
     * Render persons (players) table shortcode
     *
     * [kealoa_persons_table]
     */
    public function render_persons_table(array $atts = []): string {
        $persons = $this->db->get_persons_with_stats();
        
        if (empty($persons)) {
            return '<p class="kealoa-no-data">' . esc_html__('No players found.', 'kealoa-reference') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="kealoa-persons-table-wrapper">
            <table class="kealoa-table kealoa-persons-table">
                <thead>
                    <tr>
                        <th data-sort="text"><?php esc_html_e('Player', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Rounds Played', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Clues Guessed', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($persons as $person): ?>
                        <tr>
                            <td>
                                <?php echo Kealoa_Formatter::format_person_link((int) $person->id, $person->full_name); ?>
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
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render constructors table shortcode
     *
     * [kealoa_constructors_table]
     */
    public function render_constructors_table(array $atts = []): string {
        $constructors = $this->db->get_constructors_with_stats();
        
        if (empty($constructors)) {
            return '<p class="kealoa-no-data">' . esc_html__('No constructors found.', 'kealoa-reference') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="kealoa-constructors-table-wrapper">
            <table class="kealoa-table kealoa-constructors-table">
                <thead>
                    <tr>
                        <th data-sort="text"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Clues', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($constructors as $constructor): ?>
                        <tr>
                            <td>
                                <?php echo Kealoa_Formatter::format_constructor_link((int) $constructor->id, $constructor->full_name); ?>
                            </td>
                            <td><?php echo esc_html($constructor->puzzle_count); ?></td>
                            <td><?php echo esc_html($constructor->clue_count); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
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
        
        $puzzles = $this->db->get_constructor_puzzles($constructor_id);
        
        ob_start();
        ?>
        <div class="kealoa-constructor-view">
            <div class="kealoa-constructor-header">
                <div class="kealoa-constructor-info">
                    <?php if (!empty($constructor->xwordinfo_image_url)): ?>
                        <div class="kealoa-constructor-image">
                            <?php echo Kealoa_Formatter::format_xwordinfo_image($constructor->xwordinfo_image_url, $constructor->full_name); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="kealoa-constructor-details">
                        <h2 class="kealoa-constructor-name"><?php echo esc_html($constructor->full_name); ?></h2>
                        
                        <?php if (!empty($constructor->xwordinfo_profile_name)): ?>
                            <p class="kealoa-constructor-xwordinfo">
                                <strong><?php esc_html_e('XWordInfo:', 'kealoa-reference'); ?></strong>
                                <?php echo Kealoa_Formatter::format_xwordinfo_link($constructor->xwordinfo_profile_name, 'View Profile'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($puzzles)): ?>
                <div class="kealoa-constructor-puzzles">
                    <h3><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></h3>
                    
                    <table class="kealoa-table kealoa-constructor-puzzles-table">
                        <thead>
                            <tr>
                                <th data-sort="weekday"><?php esc_html_e('Day', 'kealoa-reference'); ?></th>
                                <th data-sort="date"><?php esc_html_e('Publication Date', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Co-Constructors', 'kealoa-reference'); ?></th>
                                <th data-sort="date"><?php esc_html_e('Used in Rounds', 'kealoa-reference'); ?></th>
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
                                            echo implode(', ', $round_links);
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
            <?php else: ?>
                <p class="kealoa-no-data"><?php esc_html_e('No puzzles found for this constructor.', 'kealoa-reference'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render editors table shortcode
     *
     * [kealoa_editors_table]
     */
    public function render_editors_table(array $atts = []): string {
        $editors = $this->db->get_editors_with_stats();
        
        if (empty($editors)) {
            return '<p class="kealoa-no-data">' . esc_html__('No editors found.', 'kealoa-reference') . '</p>';
        }
        
        ob_start();
        ?>
        <div class="kealoa-editors-table-wrapper">
            <table class="kealoa-table kealoa-editors-table">
                <thead>
                    <tr>
                        <th data-sort="text"><?php esc_html_e('Editor', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Clues Guessed', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                        <th data-sort="number"><?php esc_html_e('Accuracy', 'kealoa-reference'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($editors as $editor): ?>
                        <tr>
                            <td>
                                <?php echo Kealoa_Formatter::format_editor_link($editor->editor_name); ?>
                            </td>
                            <td><?php echo esc_html($editor->clues_guessed); ?></td>
                            <td><?php echo esc_html($editor->correct_guesses); ?></td>
                            <td>
                                <?php
                                $accuracy = $editor->clues_guessed > 0
                                    ? ($editor->correct_guesses / $editor->clues_guessed) * 100
                                    : 0;
                                echo Kealoa_Formatter::format_percentage((float) $accuracy);
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
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
        
        $puzzles = $this->db->get_editor_puzzles($editor_name);
        
        ob_start();
        ?>
        <div class="kealoa-editor-view">
            <div class="kealoa-editor-header">
                <h2 class="kealoa-editor-name"><?php echo esc_html($editor_name); ?></h2>
            </div>
            
            <?php if (!empty($puzzles)): ?>
                <div class="kealoa-editor-puzzles">
                    <h3><?php esc_html_e('Puzzles', 'kealoa-reference'); ?></h3>
                    
                    <table class="kealoa-table kealoa-editor-puzzles-table">
                        <thead>
                            <tr>
                                <th data-sort="weekday"><?php esc_html_e('Day', 'kealoa-reference'); ?></th>
                                <th data-sort="date"><?php esc_html_e('Publication Date', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Constructor', 'kealoa-reference'); ?></th>
                                <th data-sort="date"><?php esc_html_e('KEALOA Round(s)', 'kealoa-reference'); ?></th>
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
                                            echo implode(', ', $round_links);
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
            <?php else: ?>
                <p class="kealoa-no-data"><?php esc_html_e('No puzzles found for this editor.', 'kealoa-reference'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
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
