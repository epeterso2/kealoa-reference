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
    }

    /**
     * Render rounds table shortcode
     *
     * [kealoa_rounds_table]
     */
    public function render_rounds_table(array $atts = []): string {
        $atts = shortcode_atts([
        'limit' => 0,
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
                                <?php echo esc_html(Kealoa_Formatter::format_solution_words($solutions)); ?>
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
                        <?php echo Kealoa_Formatter::format_person_list($guessers); ?>
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
                                <th data-sort="date"><?php esc_html_e('Puzzle Date', 'kealoa-reference'); ?></th>
                                <th data-sort="text"><?php esc_html_e('Constructors', 'kealoa-reference'); ?></th>
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
                                $constructors = $this->db->get_puzzle_constructors((int) $clue->puzzle_id);
                                $clue_guesses = $this->db->get_clue_guesses((int) $clue->id);
                                ?>
                                <tr>
                                    <td class="kealoa-clue-number"><?php echo esc_html($clue->clue_number); ?></td>
                                    <td class="kealoa-puzzle-date">
                                        <?php echo Kealoa_Formatter::format_puzzle_date_link($clue->puzzle_date); ?>
                                    </td>
                                    <td class="kealoa-constructors">
                                        <?php echo Kealoa_Formatter::format_constructor_list($constructors); ?>
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
        $constructor_results = $this->db->get_person_results_by_constructor($person_id);
        $round_history = $this->db->get_person_round_history($person_id);
        
        ob_start();
        ?>
        <div class="kealoa-person-view">
            <div class="kealoa-person-header">
                <div class="kealoa-person-info">
                    <?php if (!empty($person->xwordinfo_image_url)): ?>
                        <div class="kealoa-person-image">
                            <?php echo Kealoa_Formatter::format_xwordinfo_image($person->xwordinfo_image_url, $person->full_name); ?>
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
                            <td><?php esc_html_e('Percentage Correct', 'kealoa-reference'); ?></td>
                            <td><?php echo Kealoa_Formatter::format_percentage($stats->min_percentage); ?></td>
                            <td><?php echo Kealoa_Formatter::format_percentage($stats->mean_percentage); ?></td>
                            <td><?php echo Kealoa_Formatter::format_percentage($stats->median_percentage); ?></td>
                            <td><?php echo Kealoa_Formatter::format_percentage($stats->max_percentage); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($clue_number_results)): ?>
                <div class="kealoa-clue-number-stats">
                    <h3><?php esc_html_e('Results by Clue Number', 'kealoa-reference'); ?></h3>
                    
                    <table class="kealoa-table kealoa-clue-number-table">
                        <thead>
                            <tr>
                                <th data-sort="number"><?php esc_html_e('Clue #', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Times Answered', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Percentage', 'kealoa-reference'); ?></th>
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
                                <th data-sort="number"><?php esc_html_e('Percentage', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($direction_results as $result): ?>
                                <tr>
                                    <td><?php echo esc_html($result->direction === 'A' ? 'Across' : 'Down'); ?></td>
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
                                <th data-sort="number"><?php esc_html_e('Percentage', 'kealoa-reference'); ?></th>
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
                                <th data-sort="number"><?php esc_html_e('Times Answered', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Correct', 'kealoa-reference'); ?></th>
                                <th data-sort="number"><?php esc_html_e('Percentage', 'kealoa-reference'); ?></th>
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
                                <th data-sort="number"><?php esc_html_e('Percentage', 'kealoa-reference'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($constructor_results as $result): ?>
                                <tr>
                                    <td><?php echo Kealoa_Formatter::format_constructor_link($result->full_name, $result->xwordinfo_profile_name ?? null); ?></td>
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
                                <th data-sort="number"><?php esc_html_e('Percentage', 'kealoa-reference'); ?></th>
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
                                    <td><?php echo esc_html(Kealoa_Formatter::format_solution_words($solutions)); ?></td>
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
}
