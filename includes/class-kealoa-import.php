<?php
/**
 * KEALOA Reference - CSV Import Handler
 *
 * Handles importing data from CSV files.
 *
 * @package KEALOA_Reference
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kealoa_Import {

    private Kealoa_DB $db;

    public function __construct(Kealoa_DB $db) {
        $this->db = $db;
    }

    /**
     * Normalize a date string to YYYY-MM-DD format
     * Handles various input formats like M/D/YYYY, MM/DD/YYYY, YYYY-MM-DD, etc.
     */
    private function normalize_date(string $date_str): ?string {
        $date_str = trim($date_str);
        if (empty($date_str)) {
            return null;
        }
        
        // If already in YYYY-MM-DD format, validate and return
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_str)) {
            $parts = explode('-', $date_str);
            if (checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
                return $date_str;
            }
        }
        
        // Try M/D/YYYY or MM/DD/YYYY format explicitly (prioritize this for US dates)
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $date_str, $matches)) {
            $month = (int)$matches[1];
            $day = (int)$matches[2];
            $year = (int)$matches[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }
        
        // Try M-D-YYYY or MM-DD-YYYY format
        if (preg_match('#^(\d{1,2})-(\d{1,2})-(\d{4})$#', $date_str, $matches)) {
            $month = (int)$matches[1];
            $day = (int)$matches[2];
            $year = (int)$matches[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }
        
        // Fallback: try to parse with strtotime
        $timestamp = strtotime($date_str);
        if ($timestamp !== false && $timestamp > 0) {
            return date('Y-m-d', $timestamp);
        }
        
        return null;
    }

    /**
     * Parse a CSV file and return rows as associative arrays
     */
    private function parse_csv(string $file_path): array {
        $rows = [];
        
        // Read raw content and ensure UTF-8 encoding
        $content = file_get_contents($file_path);
        if ($content === false) {
            return [];
        }
        
        // Remove UTF-8 BOM if present
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        
        // Detect encoding and convert to UTF-8 if needed
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        
        // Write sanitized content to a temp file for fgetcsv
        $temp_path = tempnam(sys_get_temp_dir(), 'kealoa_csv_');
        file_put_contents($temp_path, $content);
        
        if (($handle = fopen($temp_path, 'r')) !== false) {
            $headers = fgetcsv($handle);
            if ($headers === false) {
                fclose($handle);
                @unlink($temp_path);
                return [];
            }
            
            // Trim headers
            $headers = array_map('trim', $headers);
            
            $header_count = count($headers);
            
            while (($data = fgetcsv($handle)) !== false) {
                // Skip completely empty rows
                if (count($data) === 1 && empty(trim($data[0] ?? ''))) {
                    continue;
                }
                
                // Pad data array if it has fewer columns than headers
                while (count($data) < $header_count) {
                    $data[] = '';
                }
                
                // Truncate if more columns than headers
                if (count($data) > $header_count) {
                    $data = array_slice($data, 0, $header_count);
                }
                
                $row = array_combine($headers, array_map('trim', $data));
                $rows[] = $row;
            }
            
            fclose($handle);
        }
        
        @unlink($temp_path);
        
        return $rows;
    }

    /**
     * Import constructors from CSV
     */
    public function import_constructors(string $file_path, bool $overwrite = true): array {
        $rows = $this->parse_csv($file_path);
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($rows as $index => $row) {
            $line = $index + 2; // Account for header row
            
            if (empty($row['full_name'])) {
                $errors[] = "Line {$line}: Missing full_name";
                $skipped++;
                continue;
            }
            
            // Check if constructor already exists
            $existing = $this->db->get_constructors([
                'search' => $row['full_name'],
                'limit' => 1
            ]);
            
            $found = null;
            foreach ($existing as $c) {
                if (strtolower($c->full_name) === strtolower($row['full_name'])) {
                    $found = $c;
                    break;
                }
            }
            
            if ($found) {
                if (!$overwrite) {
                    $skipped++;
                    continue;
                }
                // Update existing constructor
                $result = $this->db->update_constructor((int) $found->id, [
                    'full_name' => $row['full_name'],
                    'xwordinfo_profile_name' => $row['xwordinfo_profile_name'] ?? null,
                    'xwordinfo_image_url' => $row['xwordinfo_image_url'] ?? null,
                ]);
            } else {
                // Create new constructor
                $result = $this->db->create_constructor([
                    'full_name' => $row['full_name'],
                    'xwordinfo_profile_name' => $row['xwordinfo_profile_name'] ?? null,
                    'xwordinfo_image_url' => $row['xwordinfo_image_url'] ?? null,
                ]);
            }
            
            if ($result) {
                $imported++;
            } else {
                $errors[] = "Line {$line}: Failed to insert constructor";
                $skipped++;
            }
        }
        
        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Import persons from CSV
     */
    public function import_persons(string $file_path, bool $overwrite = true): array {
        $rows = $this->parse_csv($file_path);
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            
            if (empty($row['full_name'])) {
                $errors[] = "Line {$line}: Missing full_name";
                $skipped++;
                continue;
            }
            
            // Check if person already exists
            $existing = $this->db->get_persons([
                'search' => $row['full_name'],
                'limit' => 1
            ]);
            
            $found = null;
            foreach ($existing as $p) {
                if (strtolower($p->full_name) === strtolower($row['full_name'])) {
                    $found = $p;
                    break;
                }
            }
            
            if ($found) {
                if (!$overwrite) {
                    $skipped++;
                    continue;
                }
                // Update existing person
                $result = $this->db->update_person((int) $found->id, [
                    'full_name' => $row['full_name'],
                    'home_page_url' => $row['home_page_url'] ?? null,
                    'image_url' => $row['image_url'] ?? null,
                ]);
            } else {
                // Create new person
                $result = $this->db->create_person([
                    'full_name' => $row['full_name'],
                    'home_page_url' => $row['home_page_url'] ?? null,
                    'image_url' => $row['image_url'] ?? null,
                ]);
            }
            
            if ($result) {
                $imported++;
            } else {
                $errors[] = "Line {$line}: Failed to insert person";
                $skipped++;
            }
        }
        
        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Import puzzles from CSV
     */
    public function import_puzzles(string $file_path, bool $overwrite = true): array {
        $rows = $this->parse_csv($file_path);
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            
            if (empty($row['publication_date'])) {
                $errors[] = "Line {$line}: Missing publication_date";
                $skipped++;
                continue;
            }
            
            // Normalize the date format
            $publication_date = $this->normalize_date($row['publication_date']);
            if (!$publication_date) {
                $errors[] = "Line {$line}: Invalid date format '{$row['publication_date']}' - use YYYY-MM-DD or M/D/YYYY";
                $skipped++;
                continue;
            }
            
            // Check if puzzle already exists for this date
            $existing = $this->db->get_puzzle_by_date($publication_date);
            
            if ($existing) {
                if (!$overwrite) {
                    $skipped++;
                    continue;
                }
                // Update existing puzzle with editor_name if provided
                $update_data = ['publication_date' => $publication_date];
                if (isset($row['editor_name'])) {
                    $update_data['editor_name'] = !empty($row['editor_name']) ? $row['editor_name'] : null;
                }
                $this->db->update_puzzle((int) $existing->id, $update_data);
                // Use existing puzzle ID
                $puzzle_id = (int) $existing->id;
            } else {
                // Create new puzzle
                $create_data = ['publication_date' => $publication_date];
                if (isset($row['editor_name'])) {
                    $create_data['editor_name'] = !empty($row['editor_name']) ? $row['editor_name'] : null;
                }
                $puzzle_id = $this->db->create_puzzle($create_data);
                
                if (!$puzzle_id) {
                    $errors[] = "Line {$line}: Failed to insert puzzle";
                    $skipped++;
                    continue;
                }
            }
            
            // Handle constructors if provided - always update
            if (!empty($row['constructors'])) {
                $constructor_names = array_map('trim', explode(',', $row['constructors']));
                $constructor_ids = [];
                
                foreach ($constructor_names as $name) {
                    $constructor = $this->find_or_create_constructor($name);
                    if ($constructor) {
                        $constructor_ids[] = $constructor->id;
                    }
                }
                
                if (!empty($constructor_ids)) {
                    $this->db->set_puzzle_constructors($puzzle_id, $constructor_ids);
                }
            }
            
            $imported++;
        }
        
        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Import rounds from CSV
     */
    public function import_rounds(string $file_path, bool $overwrite = true): array {
        $rows = $this->parse_csv($file_path);
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            
            if (empty($row['round_date']) || empty($row['episode_number']) || empty($row['clue_giver'])) {
                $errors[] = "Line {$line}: Missing required field (round_date, episode_number, or clue_giver)";
                $skipped++;
                continue;
            }
            
            // Normalize the date format
            $round_date = $this->normalize_date($row['round_date']);
            if (!$round_date) {
                $errors[] = "Line {$line}: Invalid date format '{$row['round_date']}' - use YYYY-MM-DD or M/D/YYYY";
                $skipped++;
                continue;
            }
            
            // Get round number (default to 1 if not specified)
            $round_number = (int) ($row['round_number'] ?? 1);
            if ($round_number < 1) {
                $round_number = 1;
            }
            
            // Find or create clue giver
            $clue_giver = $this->find_or_create_person($row['clue_giver']);
            if (!$clue_giver) {
                $errors[] = "Line {$line}: Could not find or create clue giver";
                $skipped++;
                continue;
            }
            
            // Handle start time - accept both HH:MM:SS format and raw seconds
            $start_time_value = $row['episode_start_time'] ?? $row['episode_start_seconds'] ?? '0';
            $start_seconds = Kealoa_Formatter::time_to_seconds((string) $start_time_value);
            
            $round_data = [
                'round_date' => $round_date,
                'round_number' => $round_number,
                'episode_number' => (int) $row['episode_number'],
                'episode_id' => !empty($row['episode_id']) ? (int) $row['episode_id'] : null,
                'episode_url' => $row['episode_url'] ?? null,
                'episode_start_seconds' => $start_seconds,
                'clue_giver_id' => $clue_giver->id,
                'description' => $row['description'] ?? null,
            ];
            
            // Check if round already exists for this date and round number
            $existing = $this->db->get_round_by_date_and_number($round_date, $round_number);
            
            if ($existing) {
                if (!$overwrite) {
                    $skipped++;
                    continue;
                }
                // Update existing round
                $result = $this->db->update_round((int) $existing->id, $round_data);
                $round_id = (int) $existing->id;
                if (!$result) {
                    $errors[] = "Line {$line}: Failed to update round";
                    $skipped++;
                    continue;
                }
            } else {
                // Create new round
                $round_id = $this->db->create_round($round_data);
                if (!$round_id) {
                    $errors[] = "Line {$line}: Failed to insert round";
                    $skipped++;
                    continue;
                }
            }
            
            // Handle guessers if provided - always update
            if (!empty($row['guessers'])) {
                $guesser_names = array_map('trim', explode(',', $row['guessers']));
                $guesser_ids = [];
                
                foreach ($guesser_names as $name) {
                    $guesser = $this->find_or_create_person($name);
                    if ($guesser) {
                        $guesser_ids[] = $guesser->id;
                    }
                }
                
                $this->db->set_round_guessers($round_id, $guesser_ids);
            }
            
            // Handle solution words if provided - always update
            if (!empty($row['solution_words'])) {
                $words = array_map('trim', explode(',', $row['solution_words']));
                $words = array_map('strtoupper', $words);
                $words = array_filter($words);
                
                $this->db->set_round_solutions($round_id, $words);
            }
            
            $imported++;
        }
        
        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Import clues from CSV
     */
    public function import_clues(string $file_path, bool $overwrite = true): array {
        $rows = $this->parse_csv($file_path);
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            
            $required = ['round_date', 'clue_text', 'correct_answer'];
            $missing = [];
            foreach ($required as $field) {
                if (empty($row[$field])) {
                    $missing[] = $field;
                }
            }
            
            if (!empty($missing)) {
                $errors[] = "Line {$line}: Missing required fields: " . implode(', ', $missing);
                $skipped++;
                continue;
            }
            
            // Normalize dates
            $round_date = $this->normalize_date($row['round_date']);
            if (!$round_date) {
                $errors[] = "Line {$line}: Invalid round_date format '{$row['round_date']}'";
                $skipped++;
                continue;
            }
            
            // Get round number (default to 1 if not specified)
            $round_number = (int) ($row['round_number'] ?? 1);
            if ($round_number < 1) {
                $round_number = 1;
            }
            
            // Find the round
            $round = $this->db->get_round_by_date_and_number($round_date, $round_number);
            if (!$round) {
                $errors[] = "Line {$line}: Round not found for date {$round_date} round #{$round_number}";
                $skipped++;
                continue;
            }
            
            // Handle puzzle fields (optional - may be empty for word-only rounds)
            $puzzle_id = null;
            $puzzle_clue_number = null;
            $puzzle_clue_direction = null;
            
            if (!empty($row['puzzle_date'])) {
                $puzzle_date = $this->normalize_date($row['puzzle_date']);
                if (!$puzzle_date) {
                    $errors[] = "Line {$line}: Invalid puzzle_date format '{$row['puzzle_date']}'";
                    $skipped++;
                    continue;
                }
                
                // Find or create the puzzle
                $puzzle = $this->db->get_puzzle_by_date($puzzle_date);
                if (!$puzzle) {
                    $new_puzzle_id = $this->db->create_puzzle([
                        'publication_date' => $puzzle_date,
                    ]);
                    if (!$new_puzzle_id) {
                        $errors[] = "Line {$line}: Could not create puzzle for date {$puzzle_date}";
                        $skipped++;
                        continue;
                    }
                    $puzzle = $this->db->get_puzzle($new_puzzle_id);
                }
                $puzzle_id = (int) $puzzle->id;
                
                // Handle constructors if provided
                $constructor_value = '';
                if (isset($row['constructors']) && !empty(trim($row['constructors']))) {
                    $constructor_value = trim($row['constructors']);
                } elseif (isset($row['constructor']) && !empty(trim($row['constructor']))) {
                    $constructor_value = trim($row['constructor']);
                }
                
                if (!empty($constructor_value)) {
                    $constructor_names = array_map('trim', explode(',', $constructor_value));
                    $constructor_ids = [];
                    
                    foreach ($constructor_names as $name) {
                        if (empty($name)) {
                            continue;
                        }
                        $constructor = $this->find_or_create_constructor($name);
                        if ($constructor) {
                            $constructor_ids[] = $constructor->id;
                        }
                    }
                    
                    if (!empty($constructor_ids)) {
                        $this->db->set_puzzle_constructors((int) $puzzle->id, $constructor_ids);
                    }
                }
                
                // Handle puzzle clue direction
                if (!empty($row['puzzle_clue_direction'])) {
                    $direction = strtoupper(substr($row['puzzle_clue_direction'], 0, 1));
                    if (!in_array($direction, ['A', 'D'])) {
                        $errors[] = "Line {$line}: Invalid direction '{$row['puzzle_clue_direction']}', must be A or D";
                        $skipped++;
                        continue;
                    }
                    $puzzle_clue_direction = $direction;
                }
                
                // Handle puzzle clue number
                if (!empty($row['puzzle_clue_number'])) {
                    $puzzle_clue_number = (int) $row['puzzle_clue_number'];
                }
            }
            
            $clue_data = [
                'round_id' => $round->id,
                'puzzle_id' => $puzzle_id,
                'clue_number' => !empty($row['clue_number']) ? (int) $row['clue_number'] : ($index + 1),
                'puzzle_clue_number' => $puzzle_clue_number,
                'puzzle_clue_direction' => $puzzle_clue_direction,
                'clue_text' => $row['clue_text'],
                'correct_answer' => strtoupper($row['correct_answer']),
            ];
            
            // Check if clue already exists
            $existing_clues = $this->db->get_round_clues($round->id);
            $existing_clue = null;
            foreach ($existing_clues as $c) {
                if ((int) $c->clue_number === (int) $row['clue_number']) {
                    $existing_clue = $c;
                    break;
                }
            }
            
            if ($existing_clue) {
                if (!$overwrite) {
                    $skipped++;
                    continue;
                }
                // Update existing clue
                $clue_id = $this->db->update_clue((int) $existing_clue->id, $clue_data);
            } else {
                // Create new clue
                $clue_id = $this->db->create_clue($clue_data);
            }
            
            if ($clue_id) {
                $imported++;
                
                // Handle guesser/guess columns if present and both have values
                if (!empty($row['guesser']) && !empty($row['guess'])) {
                    $guesser = $this->find_or_create_person(trim($row['guesser']));
                    if ($guesser) {
                        $guessed_word = strtoupper(trim($row['guess']));
                        $actual_clue_id = $existing_clue ? (int) $existing_clue->id : $clue_id;
                        $this->db->set_guess($actual_clue_id, (int) $guesser->id, $guessed_word);
                    }
                }
            } else {
                $errors[] = "Line {$line}: Failed to insert clue";
                $skipped++;
            }
        }
        
        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Import guesses from CSV
     */
    public function import_guesses(string $file_path, bool $overwrite = true): array {
        $rows = $this->parse_csv($file_path);
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            
            $required = ['round_date', 'clue_number', 'guesser', 'guessed_word'];
            $missing = [];
            foreach ($required as $field) {
                if (empty($row[$field])) {
                    $missing[] = $field;
                }
            }
            
            if (!empty($missing)) {
                $errors[] = "Line {$line}: Missing required fields: " . implode(', ', $missing);
                $skipped++;
                continue;
            }
            
            // Normalize the date
            $round_date = $this->normalize_date($row['round_date']);
            if (!$round_date) {
                $errors[] = "Line {$line}: Invalid round_date format '{$row['round_date']}'";
                $skipped++;
                continue;
            }
            
            // Get round number (default to 1 if not specified)
            $round_number = (int) ($row['round_number'] ?? 1);
            if ($round_number < 1) {
                $round_number = 1;
            }
            
            // Find the round
            $round = $this->db->get_round_by_date_and_number($round_date, $round_number);
            if (!$round) {
                $errors[] = "Line {$line}: Round not found for date {$round_date} round #{$round_number}";
                $skipped++;
                continue;
            }
            
            // Find the clue
            $clues = $this->db->get_round_clues($round->id);
            $clue = null;
            foreach ($clues as $c) {
                if ((int) $c->clue_number === (int) $row['clue_number']) {
                    $clue = $c;
                    break;
                }
            }
            
            if (!$clue) {
                $errors[] = "Line {$line}: Clue #{$row['clue_number']} not found for round {$row['round_date']}";
                $skipped++;
                continue;
            }
            
            // Find the guesser
            $guesser = $this->find_person_by_name($row['guesser']);
            if (!$guesser) {
                $errors[] = "Line {$line}: Player '{$row['guesser']}' not found";
                $skipped++;
                continue;
            }
            
            $guessed_word = strtoupper(trim($row['guessed_word']));
            
            // Check if guess already exists for this clue and guesser
            if (!$overwrite) {
                $existing_guesses = $this->db->get_clue_guesses((int) $clue->id);
                $has_existing = false;
                foreach ($existing_guesses as $g) {
                    if ((int) $g->guesser_person_id === (int) $guesser->id) {
                        $has_existing = true;
                        break;
                    }
                }
                if ($has_existing) {
                    $skipped++;
                    continue;
                }
            }
            
            // set_guess will create or update the guess
            $result = $this->db->set_guess(
                (int) $clue->id,
                (int) $guesser->id,
                $guessed_word
            );
            
            if ($result) {
                $imported++;
            } else {
                $errors[] = "Line {$line}: Failed to insert guess";
                $skipped++;
            }
        }
        
        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Find a constructor by name or create one
     */
    private function find_or_create_constructor(string $name): ?object {
        $constructors = $this->db->get_constructors(['limit' => 1000]);
        
        foreach ($constructors as $c) {
            if (strtolower($c->full_name) === strtolower($name)) {
                return $c;
            }
        }
        
        // Auto-generate XWordInfo fields
        $profile_name = str_replace(' ', '_', $name);
        $image_name = preg_replace('/[^A-Za-z0-9]/', '', $name);
        $image_url = 'https://www.xwordinfo.com/images/cons/' . $image_name . '.jpg';
        
        $id = $this->db->create_constructor([
            'full_name' => $name,
            'xwordinfo_profile_name' => $profile_name,
            'xwordinfo_image_url' => $image_url,
        ]);
        
        return $id ? $this->db->get_constructor($id) : null;
    }

    /**
     * Find a person by name or create one
     */
    private function find_or_create_person(string $name): ?object {
        $person = $this->find_person_by_name($name);
        if ($person) {
            return $person;
        }
        
        $id = $this->db->create_person([
            'full_name' => $name,
        ]);
        
        return $id ? $this->db->get_person($id) : null;
    }

    /**
     * Find a person by name (exact match, case-insensitive)
     */
    private function find_person_by_name(string $name): ?object {
        $persons = $this->db->get_persons(['limit' => 1000]);
        
        foreach ($persons as $p) {
            if (strtolower($p->full_name) === strtolower($name)) {
                return $p;
            }
        }
        
        return null;
    }

    /**
     * Import all data from an exported ZIP file
     *
     * The ZIP file should contain CSV files named: constructors.csv, persons.csv,
     * puzzles.csv, rounds.csv, clues.csv, guesses.csv (as created by Export All).
     * Files are imported in dependency order.
     */
    public function import_zip(string $zip_path, bool $overwrite = true): array {
        $all_results = [
            'success' => true,
            'imported' => 0,
            'skipped' => 0,
            'errors' => [],
            'details' => [],
        ];

        // Verify ZIP extension is available
        if (!class_exists('ZipArchive')) {
            $all_results['success'] = false;
            $all_results['errors'][] = 'PHP ZipArchive extension is not available.';
            return $all_results;
        }

        // Open the ZIP file
        $zip = new \ZipArchive();
        $open_result = $zip->open($zip_path);
        if ($open_result !== true) {
            $all_results['success'] = false;
            $all_results['errors'][] = 'Could not open ZIP file (error code: ' . $open_result . ').';
            return $all_results;
        }

        // Extract to temp directory
        $temp_dir = get_temp_dir() . 'kealoa-import-' . uniqid();
        wp_mkdir_p($temp_dir);

        $zip->extractTo($temp_dir);
        $zip->close();

        // Import in dependency order
        $import_order = [
            'constructors' => 'Constructors',
            'persons' => 'Persons',
            'puzzles' => 'Puzzles',
            'rounds' => 'Rounds',
            'clues' => 'Clues',
            'guesses' => 'Guesses',
        ];

        foreach ($import_order as $type => $label) {
            $csv_path = $temp_dir . '/' . $type . '.csv';

            if (!file_exists($csv_path)) {
                $all_results['details'][$type] = [
                    'label' => $label,
                    'imported' => 0,
                    'skipped' => 0,
                    'status' => 'skipped',
                    'message' => $type . '.csv not found in ZIP file',
                ];
                continue;
            }

            $method = 'import_' . $type;
            $result = $this->$method($csv_path, $overwrite);

            $all_results['imported'] += $result['imported'];
            $all_results['skipped'] += $result['skipped'];

            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    $all_results['errors'][] = $label . ': ' . $error;
                }
            }

            $all_results['details'][$type] = [
                'label' => $label,
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'status' => ($result['imported'] > 0 || empty($result['errors'])) ? 'success' : 'error',
            ];
        }

        // Cleanup temp files
        foreach ($import_order as $type => $label) {
            $csv_path = $temp_dir . '/' . $type . '.csv';
            if (file_exists($csv_path)) {
                @unlink($csv_path);
            }
        }
        // Remove any other files that may have been in the ZIP
        $remaining_files = glob($temp_dir . '/*');
        if ($remaining_files) {
            foreach ($remaining_files as $file) {
                @unlink($file);
            }
        }
        @rmdir($temp_dir);

        $all_results['success'] = $all_results['imported'] > 0 || empty($all_results['errors']);

        return $all_results;
    }

    /**
     * Get available template files
     */
    public static function get_templates(): array {
        $template_dir = KEALOA_PLUGIN_DIR . 'templates/csv/';
        $templates = [];
        
        $files = [
            'constructors' => 'Constructors',
            'persons' => 'Persons',
            'puzzles' => 'Puzzles',
            'rounds' => 'Rounds',
            'clues' => 'Clues',
            'guesses' => 'Guesses',
        ];
        
        foreach ($files as $file => $label) {
            $path = $template_dir . $file . '.csv';
            if (file_exists($path)) {
                $templates[$file] = [
                    'label' => $label,
                    'path' => $path,
                    'url' => KEALOA_PLUGIN_URL . 'templates/csv/' . $file . '.csv',
                ];
            }
        }
        
        return $templates;
    }
}
