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
     * Parse a CSV file and return rows as associative arrays
     */
    private function parse_csv(string $file_path): array {
        $rows = [];
        
        if (($handle = fopen($file_path, 'r')) !== false) {
            $headers = fgetcsv($handle);
            if ($headers === false) {
                fclose($handle);
                return [];
            }
            
            // Trim headers
            $headers = array_map('trim', $headers);
            
            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) === count($headers)) {
                    $row = array_combine($headers, array_map('trim', $data));
                    $rows[] = $row;
                }
            }
            
            fclose($handle);
        }
        
        return $rows;
    }

    /**
     * Import constructors from CSV
     */
    public function import_constructors(string $file_path): array {
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
            
            $found = false;
            foreach ($existing as $c) {
                if (strtolower($c->full_name) === strtolower($row['full_name'])) {
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                $skipped++;
                continue;
            }
            
            $result = $this->db->create_constructor([
                'full_name' => $row['full_name'],
                'xwordinfo_profile_name' => $row['xwordinfo_profile_name'] ?? null,
                'xwordinfo_image_url' => $row['xwordinfo_image_url'] ?? null,
            ]);
            
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
    public function import_persons(string $file_path): array {
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
            
            $found = false;
            foreach ($existing as $p) {
                if (strtolower($p->full_name) === strtolower($row['full_name'])) {
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                $skipped++;
                continue;
            }
            
            $result = $this->db->create_person([
                'full_name' => $row['full_name'],
                'home_page_url' => $row['home_page_url'] ?? null,
            ]);
            
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
    public function import_puzzles(string $file_path): array {
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
            
            // Check if puzzle already exists for this date
            $existing = $this->db->get_puzzle_by_date($row['publication_date']);
            if ($existing) {
                $skipped++;
                continue;
            }
            
            $puzzle_id = $this->db->create_puzzle([
                'publication_date' => $row['publication_date'],
            ]);
            
            if (!$puzzle_id) {
                $errors[] = "Line {$line}: Failed to insert puzzle";
                $skipped++;
                continue;
            }
            
            // Handle constructors if provided
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
    public function import_rounds(string $file_path): array {
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
            
            // Check if round already exists for this date
            $existing = $this->db->get_round_by_date($row['round_date']);
            if ($existing) {
                $skipped++;
                continue;
            }
            
            // Find or create clue giver
            $clue_giver = $this->find_or_create_person($row['clue_giver']);
            if (!$clue_giver) {
                $errors[] = "Line {$line}: Could not find or create clue giver";
                $skipped++;
                continue;
            }
            
            $round_id = $this->db->create_round([
                'round_date' => $row['round_date'],
                'episode_number' => (int) $row['episode_number'],
                'episode_url' => $row['episode_url'] ?? null,
                'episode_start_seconds' => (int) ($row['episode_start_seconds'] ?? 0),
                'clue_giver_id' => $clue_giver->id,
                'description' => $row['description'] ?? null,
            ]);
            
            if (!$round_id) {
                $errors[] = "Line {$line}: Failed to insert round";
                $skipped++;
                continue;
            }
            
            // Handle guessers if provided
            if (!empty($row['guessers'])) {
                $guesser_names = array_map('trim', explode(',', $row['guessers']));
                $guesser_ids = [];
                
                foreach ($guesser_names as $name) {
                    $guesser = $this->find_or_create_person($name);
                    if ($guesser) {
                        $guesser_ids[] = $guesser->id;
                    }
                }
                
                if (!empty($guesser_ids)) {
                    $this->db->set_round_guessers($round_id, $guesser_ids);
                }
            }
            
            // Handle solution words if provided
            if (!empty($row['solution_words'])) {
                $words = array_map('trim', explode(',', $row['solution_words']));
                $words = array_map('strtoupper', $words);
                $words = array_filter($words);
                
                if (!empty($words)) {
                    $this->db->set_round_solutions($round_id, $words);
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
     * Import clues from CSV
     */
    public function import_clues(string $file_path): array {
        $rows = $this->parse_csv($file_path);
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            
            $required = ['round_date', 'clue_number', 'puzzle_date', 'puzzle_clue_number', 'puzzle_clue_direction', 'clue_text', 'correct_answer'];
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
            
            // Find the round
            $round = $this->db->get_round_by_date($row['round_date']);
            if (!$round) {
                $errors[] = "Line {$line}: Round not found for date {$row['round_date']}";
                $skipped++;
                continue;
            }
            
            // Find or create the puzzle
            $puzzle = $this->db->get_puzzle_by_date($row['puzzle_date']);
            if (!$puzzle) {
                $puzzle_id = $this->db->create_puzzle([
                    'publication_date' => $row['puzzle_date'],
                ]);
                if (!$puzzle_id) {
                    $errors[] = "Line {$line}: Could not create puzzle for date {$row['puzzle_date']}";
                    $skipped++;
                    continue;
                }
                $puzzle = $this->db->get_puzzle($puzzle_id);
            }
            
            // Check if clue already exists
            $existing_clues = $this->db->get_round_clues($round->id);
            $clue_exists = false;
            foreach ($existing_clues as $c) {
                if ((int) $c->clue_number === (int) $row['clue_number']) {
                    $clue_exists = true;
                    break;
                }
            }
            
            if ($clue_exists) {
                $skipped++;
                continue;
            }
            
            $direction = strtoupper(substr($row['puzzle_clue_direction'], 0, 1));
            if (!in_array($direction, ['A', 'D'])) {
                $errors[] = "Line {$line}: Invalid direction '{$row['puzzle_clue_direction']}', must be A or D";
                $skipped++;
                continue;
            }
            
            $clue_id = $this->db->create_clue([
                'round_id' => $round->id,
                'puzzle_id' => $puzzle->id,
                'clue_number' => (int) $row['clue_number'],
                'puzzle_clue_number' => (int) $row['puzzle_clue_number'],
                'puzzle_clue_direction' => $direction,
                'clue_text' => $row['clue_text'],
                'correct_answer' => strtoupper($row['correct_answer']),
            ]);
            
            if ($clue_id) {
                $imported++;
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
    public function import_guesses(string $file_path): array {
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
            
            // Find the round
            $round = $this->db->get_round_by_date($row['round_date']);
            if (!$round) {
                $errors[] = "Line {$line}: Round not found for date {$row['round_date']}";
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
                $errors[] = "Line {$line}: Guesser '{$row['guesser']}' not found";
                $skipped++;
                continue;
            }
            
            // Check if guess already exists
            $existing_guesses = $this->db->get_clue_guesses((int) $clue->id);
            $guess_exists = false;
            foreach ($existing_guesses as $g) {
                if ((int) $g->guesser_person_id === (int) $guesser->id) {
                    $guess_exists = true;
                    break;
                }
            }
            
            if ($guess_exists) {
                $skipped++;
                continue;
            }
            
            $guessed_word = strtoupper(trim($row['guessed_word']));
            $is_correct = ($guessed_word === strtoupper($clue->correct_answer)) ? 1 : 0;
            
            $result = $this->db->create_guess([
                'clue_id' => $clue->id,
                'guesser_person_id' => $guesser->id,
                'guessed_word' => $guessed_word,
                'is_correct' => $is_correct,
            ]);
            
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
        $image_name = str_replace(' ', '', $name);
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
