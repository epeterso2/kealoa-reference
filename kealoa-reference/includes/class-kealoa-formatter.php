<?php
/**
 * @copyright 2026 Eric Peterson (eric@puzzlehead.org)
 * @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
 *
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
     * Convert seconds to HH:MM:SS format
     *
     * @param int $seconds Total seconds
     * @return string Time in HH:MM:SS format
     */
    public static function seconds_to_time(int $seconds): string {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    /**
     * Convert HH:MM:SS (or MM:SS or just seconds) to total seconds
     *
     * @param string $time Time string in HH:MM:SS, MM:SS, or seconds format
     * @return int Total seconds
     */
    public static function time_to_seconds(string $time): int {
        $time = trim($time);
        if (empty($time)) {
            return 0;
        }
        
        // If it's just a number, treat it as seconds
        if (is_numeric($time)) {
            return (int) $time;
        }
        
        // Split by colon
        $parts = explode(':', $time);
        $count = count($parts);
        
        if ($count === 3) {
            // HH:MM:SS
            return ((int) $parts[0] * 3600) + ((int) $parts[1] * 60) + (int) $parts[2];
        } elseif ($count === 2) {
            // MM:SS
            return ((int) $parts[0] * 60) + (int) $parts[1];
        }
        
        return 0;
    }

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
        $slug = str_replace(' ', '_', $full_name);
        $url = home_url('/kealoa/person/' . urlencode($slug) . '/');
        return sprintf(
            '<a href="%s" class="kealoa-person-link">%s</a>',
            esc_url($url),
            esc_html($full_name)
        );
    }

    /**
     * Format a constructor name as a link to the in-app constructor page
     *
     * @param int $constructor_id The constructor's ID
     * @param string $full_name The constructor's full name
     * @return string HTML link to constructor view
     */
    public static function format_constructor_link(int $constructor_id, string $full_name): string {
        $slug = str_replace(' ', '_', $full_name);
        $url = home_url('/kealoa/constructor/' . urlencode($slug) . '/');
        return sprintf(
            '<a href="%s" class="kealoa-constructor-link">%s</a>',
            esc_url($url),
            esc_html($full_name)
        );
    }

    /**
     * Format an editor name as a link to the editor view
     *
     * @param string $editor_name The editor's name
     * @return string HTML link to editor view
     */
    public static function format_editor_link(string $editor_name): string {
        $url = home_url('/kealoa/editor/' . urlencode($editor_name) . '/');
        return sprintf(
            '<a href="%s" class="kealoa-editor-link">%s</a>',
            esc_url($url),
            esc_html($editor_name)
        );
    }

    /**
     * Format a list of constructors as links to their in-app pages
     *
     * @param array $constructors Array of constructor objects with id and full_name properties
     * @return string Formatted list with links
     */
    public static function format_constructor_list(array $constructors): string {
        $links = array_map(function($constructor) {
            return self::format_constructor_link(
                (int) $constructor->id,
                $constructor->full_name
            );
        }, $constructors);
        
        return self::format_list_with_and($links);
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
     * @param string|null $episode_url The raw episode URL
     * @return string HTML link to episode player
     */
    public static function format_episode_link(int $episode_number, ?string $episode_url = null): string {
        if (empty($episode_url)) {
            // No URL provided, just return the episode number without a link
            return sprintf('<span class="kealoa-episode-number">%d</span>', $episode_number);
        }
        
        return sprintf(
            '<a href="%s" class="kealoa-episode-link" target="_blank" rel="noopener noreferrer">%d</a>',
            esc_url($episode_url),
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
     * Format a date's day of week as a 3-letter abbreviation
     *
     * @param ?string $date The date in Y-m-d format
     * @return string 3-letter day abbreviation (Mon, Tue, etc.) or em dash if null
     */
    public static function format_day_abbrev(?string $date): string {
        if (empty($date)) {
            return '—';
        }
        return date('D', strtotime($date));
    }

    /**
     * Format solution words as a link to the round view
     *
     * @param int $round_id The round ID
     * @param array $solutions The solution words
     * @return string HTML link with solution words
     */
    public static function format_solution_words_link(int $round_id, array $solutions): string {
        $url = home_url('/kealoa/round/' . $round_id . '/');
        $words = self::format_solution_words($solutions);
        
        return sprintf(
            '<a href="%s" class="kealoa-round-link">%s</a>',
            esc_url($url),
            esc_html($words)
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
     * Format a puzzle date as a link to the internal puzzle page
     *
     * @param string $date The date in Y-m-d format
     * @return string HTML link to internal puzzle page
     */
    public static function format_puzzle_date_link(?string $date): string {
        if (empty($date)) {
            return '—';
        }
        $formatted_date = date('n/j/Y', strtotime($date));
        $url = home_url('/kealoa/puzzle/' . date('Y-m-d', strtotime($date)) . '/');

        return sprintf(
            '<a href="%s" class="kealoa-puzzle-link">%s</a>',
            esc_url($url),
            esc_html($formatted_date)
        );
    }

    /**
     * Format a puzzle date as a link to XWordInfo
     *
     * @param string $date The date in Y-m-d format
     * @return string HTML link to XWordInfo puzzle page
     */
    public static function format_puzzle_xwordinfo_link(?string $date): string {
        if (empty($date)) {
            return '—';
        }
        $formatted_date = date('n/j/Y', strtotime($date));
        $url = 'https://www.xwordinfo.com/Crossword?date=' . $formatted_date;

        return sprintf(
            '<a href="%s" class="kealoa-xwordinfo-puzzle-link" target="_blank" rel="noopener noreferrer">%s &#x2197;</a>',
            esc_url($url),
            esc_html($formatted_date)
        );
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
     * Derive XWordInfo image URL from a person's name
     *
     * Removes all non-alphanumeric characters from the name to form the image filename.
     * Example: "Joel Fagliano" => "https://www.xwordinfo.com/images/cons/JoelFagliano.jpg"
     *
     * @param string $name The person's full name
     * @return string The derived XWordInfo image URL
     */
    public static function xwordinfo_image_url_from_name(string $name): string {
        $clean = preg_replace('/[^A-Za-z0-9]/', '', $name);
        return 'https://www.xwordinfo.com/images/cons/' . $clean . '.jpg';
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
        
        $nophoto_url = 'https://www.xwordinfo.com/images/cons/nophoto.jpg';
        
        return sprintf(
            '<img src="%s" alt="%s" class="kealoa-xwordinfo-image" loading="lazy" onerror="this.onerror=null;this.src=\'%s\';" />',
            esc_url($image_url),
            esc_attr($alt_text),
            esc_url($nophoto_url)
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
    public static function format_clue_direction(int $number, ?string $direction): string {
        if ($number === 0 || empty($direction)) {
            return '—';
        }
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
        if (count($results) > 1) {
            usort($results, function($a, $b) {
                return (int) $b->correct_guesses - (int) $a->correct_guesses;
            });
        }
        
        $formatted = array_map(function($result) use ($total_clues) {
            $link = self::format_person_link((int) $result->person_id, $result->full_name);
            return sprintf('%s (%d/%d)', $link, (int) $result->correct_guesses, $total_clues);
        }, $results);
        
        return implode('<br>', $formatted);
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
    /**
     * Render a social sharing bar with Facebook, X (Twitter), Email, and Copy Link buttons.
     *
     * @param string $title The page title to share
     * @return string HTML for the sharing bar
     */
    public static function render_share_bar(string $title): string {
        $encoded_title = rawurlencode($title);
        // URLs are generated client-side via JS for the current page URL
        $fb_href = '#';
        $x_href = '#';
        $email_href = '#';

        return '<div class="kealoa-share-bar">'
            . '<span class="kealoa-share-bar__label">' . esc_html__('Share:', 'kealoa-reference') . '</span>'
            . '<a class="kealoa-share-btn kealoa-share-btn--facebook" title="' . esc_attr__('Share on Facebook', 'kealoa-reference') . '" data-share="facebook" data-title="' . esc_attr($title) . '" href="' . $fb_href . '" target="_blank" rel="noopener noreferrer">'
            . '<svg viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>'
            . '</a>'
            . '<a class="kealoa-share-btn kealoa-share-btn--x" title="' . esc_attr__('Share on X', 'kealoa-reference') . '" data-share="x" data-title="' . esc_attr($title) . '" href="' . $x_href . '" target="_blank" rel="noopener noreferrer">'
            . '<svg viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>'
            . '</a>'
            . '<a class="kealoa-share-btn kealoa-share-btn--email" title="' . esc_attr__('Share via Email', 'kealoa-reference') . '" data-share="email" data-title="' . esc_attr($title) . '" href="' . $email_href . '">'
            . '<svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6" fill="none" stroke="currentColor" stroke-width="2"/></svg>'
            . '</a>'
            . '<button type="button" class="kealoa-share-btn kealoa-share-btn--copy" title="' . esc_attr__('Copy link to clipboard', 'kealoa-reference') . '" data-share="copy">'
            . '<svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
            . '</button>'
            . '</div>';
    }}
