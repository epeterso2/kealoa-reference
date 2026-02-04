<?php
/**
 * Formatter Helper Class
 *
 * Provides consistent formatting methods for displaying KEALOA data.
 *
 * @package KEALOA_Reference
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Kealoa_Formatter
 *
 * Handles formatting of data for consistent display throughout the plugin.
 */
class Kealoa_Formatter {

    /**
     * Format a list with "and" before the last item
     *
     * @param array $items Array of items to format
     * @return string Comma-separated list with "and" before last item
     */
    public static function format_list_with_and(array $items): string {
        $items = array_filter($items);
        $count = count($items);
        
        if ($count === 0) {
            return '';
        }
        
        if ($count === 1) {
            return (string) reset($items);
        }
        
        if ($count === 2) {
            return implode(' and ', $items);
        }
        
        $last = array_pop($items);
        return implode(', ', $items) . ', and ' . $last;
    }

    /**
     * Format a person's name as a link to their player view
     *
     * @param int $person_id The person's ID
     * @param string $full_name The person's full name
     * @return string HTML link to person view
     */
    public static function format_person_link(int $person_id, string $full_name): string {
        $url = home_url('/kealoa/person/' . $person_id . '/');
        return sprintf(
            '<a href="%s" class="kealoa-person-link">%s</a>',
            esc_url($url),
            esc_html($full_name)
        );
    }

    /**
     * Format a list of persons as links
     *
     * @param array $persons Array of person objects with id and full_name properties
     * @return string Formatted list with links
     */
    public static function format_person_list(array $persons): string {
        $links = array_map(function($person) {
            return self::format_person_link((int) $person->id, $person->full_name);
        }, $persons);
        
        return self::format_list_with_and($links);
    }

    /**
     * Format Fill Me In episode number as a link
     *
     * @param int $episode_number The episode number
     * @param int $start_seconds Seconds after episode start where KEALOA begins
     * @return string HTML link to episode player
     */
    public static function format_episode_link(int $episode_number, int $start_seconds = 0): string {
        $url = sprintf(
            'https://bemoresmarter.libsyn.com/player?episode=%d&startTime=%d',
            $episode_number,
            $start_seconds
        );
        
        return sprintf(
            '<a href="%s" class="kealoa-episode-link" target="_blank" rel="noopener noreferrer">%d</a>',
            esc_url($url),
            $episode_number
        );
    }

    /**
     * Format a round date as a link to the round view
     *
     * @param int $round_id The round ID
     * @param string $date The date in Y-m-d format
     * @return string HTML link with M/D/YYYY formatted date
     */
    public static function format_round_date_link(int $round_id, string $date): string {
        $url = home_url('/kealoa/round/' . $round_id . '/');
        $formatted_date = date('n/j/Y', strtotime($date));
        
        return sprintf(
            '<a href="%s" class="kealoa-round-link">%s</a>',
            esc_url($url),
            esc_html($formatted_date)
        );
    }

    /**
     * Format a date in M/D/YYYY format
     *
     * @param string $date The date in Y-m-d format
     * @return string Formatted date
     */
    public static function format_date(string $date): string {
        return date('n/j/Y', strtotime($date));
    }

    /**
     * Format an XWordInfo profile link
     *
     * @param string $profile_name The XWordInfo profile name (underscores for spaces)
     * @param string $display_name The display text for the link
     * @return string HTML link to XWordInfo profile
     */
    public static function format_xwordinfo_link(string $profile_name, string $display_name = ''): string {
        if (empty($profile_name)) {
            return '';
        }
        
        // Convert spaces to underscores for URL
        $url_name = str_replace(' ', '_', $profile_name);
        $url = 'https://www.xwordinfo.com/Author/' . $url_name;
        $display = $display_name ?: $profile_name;
        
        return sprintf(
            '<a href="%s" class="kealoa-xwordinfo-link" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url($url),
            esc_html($display)
        );
    }

    /**
     * Format an XWordInfo profile image
     *
     * @param string $image_url The URL to the XWordInfo profile image
     * @param string $alt_text Alt text for the image
     * @return string HTML img tag
     */
    public static function format_xwordinfo_image(string $image_url, string $alt_text = ''): string {
        if (empty($image_url)) {
            return '';
        }
        
        return sprintf(
            '<img src="%s" alt="%s" class="kealoa-xwordinfo-image" loading="lazy" />',
            esc_url($image_url),
            esc_attr($alt_text)
        );
    }

    /**
     * Format solution words list
     *
     * @param array $solutions Array of solution objects with word property
     * @return string Formatted list of words (comma-separated with "and")
     */
    public static function format_solution_words(array $solutions): string {
        $words = array_map(fn($s) => strtoupper($s->word), $solutions);
        return self::format_list_with_and($words);
    }

    /**
     * Format clue direction and number
     *
     * @param int $number The clue number
     * @param string $direction The direction (A or D)
     * @return string Formatted like "42D" or "1A"
     */
    public static function format_clue_direction(int $number, string $direction): string {
        return $number . strtoupper($direction);
    }

    /**
     * Format percentage
     *
     * @param float $value The percentage value
     * @param int $decimals Number of decimal places
     * @return string Formatted percentage with % symbol
     */
    public static function format_percentage(float $value, int $decimals = 1): string {
        return number_format($value, $decimals) . '%';
    }

    /**
     * Get day of week name from MySQL DAYOFWEEK value
     *
     * MySQL DAYOFWEEK: 1 = Sunday, 2 = Monday, etc.
     *
     * @param int $day_of_week MySQL DAYOFWEEK value (1-7)
     * @return string Day name
     */
    public static function get_day_name(int $day_of_week): string {
        $days = [
            1 => 'Sunday',
            2 => 'Monday',
            3 => 'Tuesday',
            4 => 'Wednesday',
            5 => 'Thursday',
            6 => 'Friday',
            7 => 'Saturday',
        ];
        
        return $days[$day_of_week] ?? '';
    }

    /**
     * Format guesser results for a round
     *
     * @param array $results Array of result objects with full_name and correct_guesses
     * @param int $total_clues Total number of clues in the round
     * @return string Formatted list showing "Name (X/Y)" with links
     */
    public static function format_guesser_results(array $results, int $total_clues): string {
        $formatted = array_map(function($result) use ($total_clues) {
            $link = self::format_person_link((int) $result->person_id, $result->full_name);
            return sprintf('%s (%d/%d)', $link, (int) $result->correct_guesses, $total_clues);
        }, $results);
        
        return self::format_list_with_and($formatted);
    }

    /**
     * Format home page URL as a link
     *
     * @param string $url The home page URL
     * @param string $display_text Optional display text (defaults to URL)
     * @return string HTML link
     */
    public static function format_home_page_link(string $url, string $display_text = ''): string {
        if (empty($url)) {
            return '';
        }
        
        $display = $display_text ?: parse_url($url, PHP_URL_HOST);
        
        return sprintf(
            '<a href="%s" class="kealoa-homepage-link" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url($url),
            esc_html($display)
        );
    }

    /**
     * Format guess display for clue table
     *
     * @param string $guessed_word The word guessed
     * @param bool $is_correct Whether the guess was correct
     * @return string HTML formatted guess with correct/incorrect styling
     */
    public static function format_guess_display(string $guessed_word, bool $is_correct): string {
        $class = $is_correct ? 'kealoa-guess-correct' : 'kealoa-guess-incorrect';
        $icon = $is_correct ? '✓' : '✗';
        
        return sprintf(
            '<span class="%s">%s %s</span>',
            esc_attr($class),
            esc_html($icon),
            esc_html(strtoupper($guessed_word))
        );
    }
}
