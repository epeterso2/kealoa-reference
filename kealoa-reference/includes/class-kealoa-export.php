<?php
/**
 * @copyright 2026 Eric Peterson (eric@puzzlehead.org)
 * @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
 *
 * Export Handler
 *
 * Handles CSV export of all KEALOA data types.
 *
 * @package KEALOA_Reference
 */

declare(strict_types=1);

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Kealoa_Export
 *
 * Generates CSV downloads for all data types.
 */
class Kealoa_Export {

    private Kealoa_DB $db;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Kealoa_DB();
        add_action('admin_init', [$this, 'handle_export_request']);
    }

    /**
     * Handle CSV export request (runs before any output)
     */
    public function handle_export_request(): void {
        if (!isset($_GET['kealoa_export']) || !isset($_GET['_wpnonce'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export data.', 'kealoa-reference'));
        }

        if (!wp_verify_nonce($_GET['_wpnonce'], 'kealoa_export_csv')) {
            wp_die(__('Security check failed.', 'kealoa-reference'));
        }

        $type = sanitize_text_field($_GET['kealoa_export']);
        $allowed_types = ['persons', 'puzzles', 'rounds', 'clues', 'guesses', 'all'];

        if (!in_array($type, $allowed_types)) {
            wp_die(__('Invalid export type.', 'kealoa-reference'));
        }

        if ($type === 'all') {
            $this->export_all_as_zip();
        } else {
            $this->export_csv($type);
        }

        exit;
    }

    /**
     * Export a single data type as CSV
     */
    private function export_csv(string $type): void {
        $filename = 'kealoa-' . $type . '-' . gmdate('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        // Add UTF-8 BOM for Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");

        $this->write_csv_data($output, $type);

        fclose($output);
    }

    /**
     * Export all data types as a ZIP archive
     */
    private function export_all_as_zip(): void {
        $types = ['persons', 'puzzles', 'rounds', 'clues', 'guesses'];
        $date = gmdate('Y-m-d');
        $zip_filename = 'kealoa-export-' . $date . '.zip';

        // Create temp directory for CSV files
        $temp_dir = get_temp_dir() . 'kealoa-export-' . uniqid();
        wp_mkdir_p($temp_dir);

        // Generate CSV files
        $files = [];
        foreach ($types as $type) {
            $csv_path = $temp_dir . '/' . $type . '.csv';
            $fp = fopen($csv_path, 'w');
            fwrite($fp, "\xEF\xBB\xBF");
            $this->write_csv_data($fp, $type);
            fclose($fp);
            $files[] = $csv_path;
        }

        // Create ZIP
        $zip_path = $temp_dir . '/' . $zip_filename;
        $zip = new ZipArchive();

        if ($zip->open($zip_path, ZipArchive::CREATE) === true) {
            foreach ($files as $file) {
                $zip->addFile($file, basename($file));
            }
            $zip->close();

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
            header('Content-Length: ' . filesize($zip_path));
            header('Pragma: no-cache');
            header('Expires: 0');

            readfile($zip_path);
        }

        // Cleanup temp files
        foreach ($files as $file) {
            @unlink($file);
        }
        @unlink($zip_path);
        @rmdir($temp_dir);
    }

    /**
     * Write CSV data for a given type to a file handle
     */
    private function write_csv_data($output, string $type): void {
        match ($type) {
            'persons' => $this->write_persons($output),
            'puzzles' => $this->write_puzzles($output),
            'rounds' => $this->write_rounds($output),
            'clues' => $this->write_clues($output),
            'guesses' => $this->write_guesses($output),
            default => null,
        };
    }

    /**
     * Write persons CSV
     */
    private function write_persons($output): void {
        fputcsv($output, ['full_name', 'home_page_url', 'image_url', 'hide_xwordinfo', 'xwordinfo_profile_name', 'xwordinfo_image_url', 'media_id']);

        $persons = $this->db->get_persons(['limit' => 999999, 'orderby' => 'full_name', 'order' => 'ASC']);

        foreach ($persons as $person) {
            fputcsv($output, [
                $person->full_name,
                $person->home_page_url ?? '',
                $person->image_url ?? '',
                $person->hide_xwordinfo ?? 0,
                $person->xwordinfo_profile_name ?? '',
                $person->xwordinfo_image_url ?? '',
                $person->media_id ?? '',
            ]);
        }
    }

    /**
     * Write puzzles CSV (with constructors)
     */
    private function write_puzzles($output): void {
        fputcsv($output, ['publication_date', 'editor_name', 'constructors']);

        $puzzles = $this->db->get_puzzles(['limit' => 999999, 'orderby' => 'publication_date', 'order' => 'ASC']);

        foreach ($puzzles as $puzzle) {
            $constructors = $this->db->get_puzzle_constructors((int) $puzzle->id);
            $constructor_names = array_map(fn($c) => $c->full_name, $constructors);

            // Look up editor name from person
            $editor_name = '';
            if (!empty($puzzle->editor_id)) {
                $editor = $this->db->get_person((int) $puzzle->editor_id);
                if ($editor) {
                    $editor_name = $editor->full_name;
                }
            }

            fputcsv($output, [
                $puzzle->publication_date,
                $editor_name,
                implode(', ', $constructor_names),
            ]);
        }
    }

    /**
     * Write rounds CSV (with solution words and guessers)
     */
    private function write_rounds($output): void {
        fputcsv($output, [
            'round_date', 'round_number', 'episode_number', 'episode_id',
            'episode_url', 'episode_start_seconds', 'clue_giver',
            'guessers', 'solution_words', 'description', 'description2',
        ]);

        $rounds = $this->db->get_rounds(['limit' => 0, 'orderby' => 'round_date', 'order' => 'ASC']);

        foreach ($rounds as $round) {
            $solutions = $this->db->get_round_solutions((int) $round->id);
            $solution_words = array_map(fn($s) => strtoupper($s->word), $solutions);

            $guessers = $this->db->get_round_guessers((int) $round->id);
            $guesser_names = array_map(fn($g) => $g->full_name, $guessers);

            fputcsv($output, [
                $round->round_date,
                $round->round_number,
                $round->episode_number,
                $round->episode_id ?? '',
                $round->episode_url ?? '',
                $round->episode_start_seconds ?? 0,
                $round->clue_giver_name ?? '',
                implode(', ', $guesser_names),
                implode(', ', $solution_words),
                $round->description ?? '',
                $round->description2 ?? '',
            ]);
        }
    }

    /**
     * Write clues CSV (with puzzle date and constructors)
     */
    private function write_clues($output): void {
        fputcsv($output, [
            'round_date', 'round_number', 'clue_number', 'puzzle_date',
            'constructors', 'puzzle_clue_number', 'puzzle_clue_direction',
            'clue_text', 'correct_answer',
        ]);

        $rounds = $this->db->get_rounds(['limit' => 0, 'orderby' => 'round_date', 'order' => 'ASC']);

        foreach ($rounds as $round) {
            $clues = $this->db->get_round_clues((int) $round->id);

            foreach ($clues as $clue) {
                $constructors = !empty($clue->puzzle_id) ? $this->db->get_puzzle_constructors((int) $clue->puzzle_id) : [];
                $constructor_names = array_map(fn($c) => $c->full_name, $constructors);

                fputcsv($output, [
                    $round->round_date,
                    $round->round_number,
                    $clue->clue_number,
                    $clue->puzzle_date ?? '',
                    implode(', ', $constructor_names),
                    $clue->puzzle_clue_number,
                    $clue->puzzle_clue_direction,
                    $clue->clue_text,
                    $clue->correct_answer,
                ]);
            }
        }
    }

    /**
     * Write guesses CSV
     */
    private function write_guesses($output): void {
        fputcsv($output, [
            'round_date', 'round_number', 'clue_number',
            'guesser', 'guessed_word', 'is_correct',
        ]);

        $rounds = $this->db->get_rounds(['limit' => 0, 'orderby' => 'round_date', 'order' => 'ASC']);

        foreach ($rounds as $round) {
            $clues = $this->db->get_round_clues((int) $round->id);

            foreach ($clues as $clue) {
                $guesses = $this->db->get_clue_guesses((int) $clue->id);

                foreach ($guesses as $guess) {
                    fputcsv($output, [
                        $round->round_date,
                        $round->round_number,
                        $clue->clue_number,
                        $guess->guesser_name,
                        $guess->guessed_word,
                        $guess->is_correct,
                    ]);
                }
            }
        }
    }

    /**
     * Get the export URL for a given type
     */
    public static function get_export_url(string $type): string {
        return wp_nonce_url(
            admin_url('admin.php?page=kealoa-export&kealoa_export=' . urlencode($type)),
            'kealoa_export_csv'
        );
    }
}
