<?php
/**
 * @copyright 2026 Eric Peterson (eric@puzzlehead.org)
 * @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
 *
 * KEALOA Reference REST API
 *
 * Provides comprehensive read-only REST API endpoints for all KEALOA data.
 *
 * Namespace: kealoa/v1
 *
 * Endpoints:
 *   GET /rounds                          - List rounds (paginated)
 *   GET /rounds/{id}                     - Single round with full details
 *   GET /rounds/stats                    - Overview stats and per-year breakdown
 *   GET /persons                         - List persons (paginated, optional role filter)
 *   GET /persons/{id}                    - Single person with stats and roles
 *   GET /persons/{id}/rounds             - Person round history
 *   GET /persons/{id}/puzzles            - Puzzles from rounds the person has played
 *   GET /persons/{id}/stats/by-year      - Person stats by year
 *   GET /persons/{id}/stats/by-day       - Person stats by day of week
 *   GET /persons/{id}/stats/by-constructor - Person stats by constructor
 *   GET /persons/{id}/stats/by-editor    - Person stats by editor
 *   GET /persons/{id}/stats/by-direction - Person stats by clue direction
 *   GET /persons/{id}/stats/by-length    - Person stats by answer length
 *   GET /persons/{id}/stats/by-decade    - Person stats by puzzle decade
 *   GET /persons/{id}/stats/by-clue-number - Person stats by clue number
 *   GET /persons/{id}/stats/streaks      - Person best streaks by year
 *   GET /puzzles                         - List puzzles (paginated, with URLs)
 *   GET /puzzles/{id}                    - Single puzzle with clues, player results & rounds
 *   GET /clues/{id}                      - Single clue with guesses
 *   GET /search                          - Full-text search across all entities
 *   GET /leaderboard/scores              - Highest round scores
 *   GET /leaderboard/streaks             - Longest streaks
 *
 * @package KEALOA_Reference
 */

if (!defined('ABSPATH')) {
    exit;
}

class Kealoa_REST_API {

    private const NAMESPACE = 'kealoa/v1';

    private Kealoa_DB $db;

    public function __construct() {
        $this->db = new Kealoa_DB();
    }

    /**
     * Register all REST API routes.
     */
    public function register_routes(): void {

        // =====================================================================
        // Rounds
        // =====================================================================

        register_rest_route(self::NAMESPACE, '/rounds', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_rounds'],
            'permission_callback' => '__return_true',
            'args'                => $this->pagination_args([
                'orderby' => [
                    'default'           => 'round_date',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'order' => [
                    'default'           => 'DESC',
                    'sanitize_callback' => function ($v) {
                        return strtoupper($v) === 'ASC' ? 'ASC' : 'DESC';
                    },
                ],
            ]),
        ]);

        register_rest_route(self::NAMESPACE, '/rounds/stats', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_rounds_stats'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/rounds/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_round'],
            'permission_callback' => '__return_true',
            'args'                => $this->id_arg(),
        ]);

        // =====================================================================
        // Persons
        // =====================================================================

        register_rest_route(self::NAMESPACE, '/persons', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_persons'],
            'permission_callback' => '__return_true',
            'args'                => $this->pagination_args([
                'search' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'role' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($v) {
                        return empty($v) || in_array($v, ['player', 'constructor', 'editor'], true);
                    },
                ],
            ]),
        ]);

        register_rest_route(self::NAMESPACE, '/persons/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_person'],
            'permission_callback' => '__return_true',
            'args'                => $this->id_arg(),
        ]);

        register_rest_route(self::NAMESPACE, '/persons/(?P<id>\d+)/rounds', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_person_rounds'],
            'permission_callback' => '__return_true',
            'args'                => $this->id_arg(),
        ]);

        register_rest_route(self::NAMESPACE, '/persons/(?P<id>\d+)/puzzles', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_person_puzzles'],
            'permission_callback' => '__return_true',
            'args'                => $this->id_arg(),
        ]);

        // Person stats breakdowns
        $person_stat_routes = [
            'by-year'         => 'get_person_stats_by_year',
            'by-day'          => 'get_person_stats_by_day',
            'by-constructor'  => 'get_person_stats_by_constructor',
            'by-editor'       => 'get_person_stats_by_editor',
            'by-direction'    => 'get_person_stats_by_direction',
            'by-length'       => 'get_person_stats_by_length',
            'by-decade'       => 'get_person_stats_by_decade',
            'by-clue-number'  => 'get_person_stats_by_clue_number',
            'streaks'         => 'get_person_streaks',
        ];

        foreach ($person_stat_routes as $slug => $callback) {
            register_rest_route(self::NAMESPACE, '/persons/(?P<id>\d+)/stats/' . $slug, [
                'methods'             => 'GET',
                'callback'            => [$this, $callback],
                'permission_callback' => '__return_true',
                'args'                => $this->id_arg(),
            ]);
        }

        // =====================================================================
        // Puzzles
        // =====================================================================

        register_rest_route(self::NAMESPACE, '/puzzles', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_puzzles'],
            'permission_callback' => '__return_true',
            'args'                => $this->pagination_args([
                'orderby' => [
                    'default'           => 'publication_date',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'order' => [
                    'default'           => 'DESC',
                    'sanitize_callback' => function ($v) {
                        return strtoupper($v) === 'ASC' ? 'ASC' : 'DESC';
                    },
                ],
            ]),
        ]);

        register_rest_route(self::NAMESPACE, '/puzzles/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_puzzle'],
            'permission_callback' => '__return_true',
            'args'                => $this->id_arg(),
        ]);

        // =====================================================================
        // Clues
        // =====================================================================

        register_rest_route(self::NAMESPACE, '/clues/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_clue'],
            'permission_callback' => '__return_true',
            'args'                => $this->id_arg(),
        ]);

        // =====================================================================
        // Search
        // =====================================================================

        register_rest_route(self::NAMESPACE, '/search', [
            'methods'             => 'GET',
            'callback'            => [$this, 'search'],
            'permission_callback' => '__return_true',
            'args'                => [
                'q' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($v) {
                        return is_string($v) && strlen(trim($v)) >= 2;
                    },
                ],
            ],
        ]);

        // =====================================================================
        // Leaderboards
        // =====================================================================

        register_rest_route(self::NAMESPACE, '/leaderboard/scores', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_leaderboard_scores'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/leaderboard/streaks', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_leaderboard_streaks'],
            'permission_callback' => '__return_true',
        ]);
    }

    // =========================================================================
    // ROUNDS
    // =========================================================================

    public function get_rounds(WP_REST_Request $request): WP_REST_Response {
        $limit  = (int) $request->get_param('per_page');
        $offset = ((int) $request->get_param('page') - 1) * $limit;
        $rounds = $this->db->get_rounds([
            'limit'   => $limit,
            'offset'  => $offset,
            'orderby' => $request->get_param('orderby'),
            'order'   => $request->get_param('order'),
        ]);
        $total = $this->db->count_rounds();

        $items = array_map(function ($r) {
            $solutions = $this->db->get_round_solutions((int) $r->id);
            return [
                'id'             => (int) $r->id,
                'round_date'     => $r->round_date,
                'round_number'   => (int) ($r->round_number ?? 1),
                'episode_number' => (int) ($r->episode_number ?? 0),
                'clue_giver'     => $r->clue_giver_name ?? '',
                'solution_words' => array_map(fn($s) => $s->word, $solutions),
                'url'            => home_url('/kealoa/round/' . $r->id . '/'),
            ];
        }, $rounds);

        return $this->paginated_response($items, $total, $limit, (int) $request->get_param('page'));
    }

    public function get_rounds_stats(WP_REST_Request $request): WP_REST_Response {
        $overview = $this->db->get_rounds_overview_stats();
        $by_year  = $this->db->get_rounds_stats_by_year();

        return new WP_REST_Response([
            'overview' => $overview,
            'by_year'  => $by_year,
        ], 200);
    }

    public function get_round(WP_REST_Request $request): WP_REST_Response {
        $id    = (int) $request->get_param('id');
        $round = $this->db->get_round($id);

        if (!$round) {
            return new WP_REST_Response(['message' => 'Round not found.'], 404);
        }

        $solutions      = $this->db->get_round_solutions($id);
        $guessers        = $this->db->get_round_guessers($id);
        $clues_raw       = $this->db->get_round_clues($id);
        $guesser_results = $this->db->get_round_guesser_results($id);
        $prev            = $this->db->get_previous_round($id);
        $next            = $this->db->get_next_round($id);

        $clues = array_map(function ($c) {
            $guesses = $this->db->get_clue_guesses((int) $c->id);
            $constructors = '';
            if (!empty($c->puzzle_id)) {
                $pc    = $this->db->get_puzzle_constructors((int) $c->puzzle_id);
                $names = array_map(fn($con) => $con->full_name, $pc);
                $constructors = implode(' & ', $names);
            }
            return [
                'id'                    => (int) $c->id,
                'clue_number'           => (int) $c->clue_number,
                'puzzle_id'             => $c->puzzle_id ? (int) $c->puzzle_id : null,
                'puzzle_date'           => $c->puzzle_date ?? '',
                'constructors'          => $constructors,
                'editor'                => $c->editor_name ?? '',
                'puzzle_clue_number'    => $c->puzzle_clue_number ? (int) $c->puzzle_clue_number : null,
                'puzzle_clue_direction' => $c->puzzle_clue_direction ?? null,
                'clue_text'             => $c->clue_text,
                'correct_answer'        => $c->correct_answer,
                'guesses'               => array_map(fn($g) => [
                    'guesser_name' => $g->guesser_name,
                    'guessed_word' => $g->guessed_word,
                    'is_correct'   => (bool) $g->is_correct,
                ], $guesses),
            ];
        }, $clues_raw);

        return new WP_REST_Response([
            'id'              => (int) $round->id,
            'round_date'      => $round->round_date,
            'round_number'    => (int) ($round->round_number ?? 1),
            'episode_number'  => (int) ($round->episode_number ?? 0),
            'episode_id'      => $round->episode_id ?? '',
            'episode_url'     => $round->episode_url ?? '',
            'episode_start_time' => $round->episode_start_time ?? '',
            'description'     => $round->description ?? '',
            'description2'    => $round->description2 ?? '',
            'clue_giver'      => $round->clue_giver_name ?? '',
            'players'         => array_map(fn($g) => [
                'id'        => (int) $g->id,
                'full_name' => $g->full_name,
            ], $guessers),
            'solution_words'  => array_map(fn($s) => $s->word, $solutions),
            'clues'           => $clues,
            'guesser_results' => array_map(fn($gr) => [
                'full_name'       => $gr->full_name,
                'total_guesses'   => (int) $gr->total_guesses,
                'correct_guesses' => (int) $gr->correct_guesses,
            ], $guesser_results),
            'previous_round_id' => $prev ? (int) $prev->id : null,
            'next_round_id'     => $next ? (int) $next->id : null,
            'url'               => home_url('/kealoa/round/' . $id . '/'),
        ], 200);
    }

    // =========================================================================
    // PERSONS
    // =========================================================================

    public function get_persons(WP_REST_Request $request): WP_REST_Response {
        $limit  = (int) $request->get_param('per_page');
        $offset = ((int) $request->get_param('page') - 1) * $limit;
        $search = $request->get_param('search') ?: '';
        $role   = $request->get_param('role') ?: '';

        // If a role filter is specified, get persons by role
        if ($role === 'constructor') {
            $all = $this->db->get_persons_who_are_constructors();
        } elseif ($role === 'editor') {
            $all = $this->db->get_persons_who_are_editors();
        } else {
            // Default: paginated list
            $persons = $this->db->get_persons([
                'limit'  => $limit,
                'offset' => $offset,
                'search' => $search,
            ]);
            $total = $this->db->count_persons($search);

            $items = array_map(function ($p) {
                return [
                    'id'        => (int) $p->id,
                    'full_name' => $p->full_name,
                    'roles'     => $this->db->get_person_roles((int) $p->id),
                    'url'       => home_url('/kealoa/person/' . urlencode(str_replace(' ', '_', $p->full_name)) . '/'),
                ];
            }, $persons);

            return $this->paginated_response($items, $total, $limit, (int) $request->get_param('page'));
        }

        // Role-filtered results (not paginated, typically small)
        if (!empty($search)) {
            $search_lower = strtolower($search);
            $all = array_filter($all, fn($p) => str_contains(strtolower($p->full_name), $search_lower));
        }

        $total = count($all);
        $sliced = array_slice($all, $offset, $limit);

        $items = array_map(function ($p) {
            return [
                'id'        => (int) $p->id,
                'full_name' => $p->full_name,
                'url'       => home_url('/kealoa/person/' . urlencode(str_replace(' ', '_', $p->full_name)) . '/'),
            ];
        }, $sliced);

        return $this->paginated_response(array_values($items), $total, $limit, (int) $request->get_param('page'));
    }

    public function get_person(WP_REST_Request $request): WP_REST_Response {
        $id     = (int) $request->get_param('id');
        $person = $this->db->get_person($id);

        if (!$person) {
            return new WP_REST_Response(['message' => 'Person not found.'], 404);
        }

        $roles = $this->db->get_person_roles($id);
        $stats = $this->db->get_person_stats($id);

        $data = [
            'id'                     => (int) $person->id,
            'full_name'              => $person->full_name,
            'nicknames'              => $person->nicknames ?? '',
            'home_page_url'          => $person->home_page_url ?? '',
            'xwordinfo_profile_name' => $person->xwordinfo_profile_name ?? '',
            'xwordinfo_image_url'    => $person->xwordinfo_image_url ?? '',
            'roles'                  => $roles,
            'stats'                  => $stats,
            'url'                    => home_url('/kealoa/person/' . urlencode(str_replace(' ', '_', $person->full_name)) . '/'),
        ];

        // Include constructor stats if applicable
        if (in_array('constructor', $roles, true)) {
            $data['constructor_stats'] = $this->db->get_person_constructor_stats($id);
        }

        // Include editor stats if applicable
        if (in_array('editor', $roles, true)) {
            $data['editor_stats'] = $this->db->get_person_editor_stats($id);
        }

        // Include clue giver stats if applicable
        if (in_array('clue_giver', $roles, true)) {
            $data['clue_giver_stats'] = $this->db->get_clue_giver_stats($id);
        }

        return new WP_REST_Response($data, 200);
    }

    public function get_person_rounds(WP_REST_Request $request): WP_REST_Response {
        $id     = (int) $request->get_param('id');
        $person = $this->db->get_person($id);

        if (!$person) {
            return new WP_REST_Response(['message' => 'Person not found.'], 404);
        }

        $rounds = $this->db->get_person_round_history($id);

        return new WP_REST_Response([
            'person_id'  => $id,
            'full_name'  => $person->full_name,
            'rounds'     => $rounds,
        ], 200);
    }

    public function get_person_puzzles(WP_REST_Request $request): WP_REST_Response {
        $id     = (int) $request->get_param('id');
        $person = $this->db->get_person($id);

        if (!$person) {
            return new WP_REST_Response(['message' => 'Person not found.'], 404);
        }

        $puzzles = $this->db->get_person_puzzles($id);

        $items = array_map(function ($p) {
            $constructor_ids   = !empty($p->constructor_ids) ? explode(',', $p->constructor_ids) : [];
            $constructor_names = !empty($p->constructor_names) ? explode(', ', $p->constructor_names) : [];
            $round_ids         = !empty($p->round_ids) ? explode(',', $p->round_ids) : [];
            $round_dates       = !empty($p->round_dates) ? explode(',', $p->round_dates) : [];

            $constructors = [];
            for ($i = 0; $i < count($constructor_ids); $i++) {
                $cid = (int) $constructor_ids[$i];
                $cname = $constructor_names[$i] ?? '';
                $constructors[] = [
                    'id'        => $cid,
                    'full_name' => $cname,
                    'url'       => home_url('/kealoa/person/' . urlencode(str_replace(' ', '_', $cname)) . '/'),
                ];
            }

            $day_name = date('l', strtotime($p->publication_date));

            return [
                'puzzle_id'        => (int) $p->puzzle_id,
                'publication_date' => $p->publication_date,
                'day_of_week'      => $day_name,
                'editor_id'        => $p->editor_id ? (int) $p->editor_id : null,
                'editor_name'      => $p->editor_name ?? '',
                'constructors'     => $constructors,
                'round_ids'        => array_map('intval', $round_ids),
                'round_dates'      => $round_dates,
                'url'              => home_url('/kealoa/puzzle/' . $p->publication_date . '/'),
            ];
        }, $puzzles);

        return new WP_REST_Response([
            'person_id'  => $id,
            'full_name'  => $person->full_name,
            'total'      => count($items),
            'puzzles'    => $items,
        ], 200);
    }

    public function get_person_stats_by_year(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        if (!$this->db->get_person($id)) {
            return new WP_REST_Response(['message' => 'Person not found.'], 404);
        }
        return new WP_REST_Response($this->db->get_person_results_by_year($id), 200);
    }

    public function get_person_stats_by_day(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        if (!$this->db->get_person($id)) {
            return new WP_REST_Response(['message' => 'Person not found.'], 404);
        }
        return new WP_REST_Response($this->db->get_person_results_by_day_of_week($id), 200);
    }

    public function get_person_stats_by_constructor(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        if (!$this->db->get_person($id)) {
            return new WP_REST_Response(['message' => 'Person not found.'], 404);
        }
        return new WP_REST_Response($this->db->get_person_results_by_constructor($id), 200);
    }

    public function get_person_stats_by_editor(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        if (!$this->db->get_person($id)) {
            return new WP_REST_Response(['message' => 'Person not found.'], 404);
        }
        return new WP_REST_Response($this->db->get_person_results_by_editor($id), 200);
    }

    public function get_person_stats_by_direction(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        if (!$this->db->get_person($id)) {
            return new WP_REST_Response(['message' => 'Person not found.'], 404);
        }
        return new WP_REST_Response($this->db->get_person_results_by_direction($id), 200);
    }

    public function get_person_stats_by_length(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        if (!$this->db->get_person($id)) {
            return new WP_REST_Response(['message' => 'Person not found.'], 404);
        }
        return new WP_REST_Response($this->db->get_person_results_by_answer_length($id), 200);
    }

    public function get_person_stats_by_decade(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        if (!$this->db->get_person($id)) {
            return new WP_REST_Response(['message' => 'Person not found.'], 404);
        }
        return new WP_REST_Response($this->db->get_person_results_by_decade($id), 200);
    }

    public function get_person_stats_by_clue_number(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        if (!$this->db->get_person($id)) {
            return new WP_REST_Response(['message' => 'Person not found.'], 404);
        }
        return new WP_REST_Response($this->db->get_person_results_by_clue_number($id), 200);
    }

    public function get_person_streaks(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        if (!$this->db->get_person($id)) {
            return new WP_REST_Response(['message' => 'Person not found.'], 404);
        }
        return new WP_REST_Response($this->db->get_person_best_streaks_by_year($id), 200);
    }

    // =========================================================================
    // PUZZLES
    // =========================================================================

    public function get_puzzles(WP_REST_Request $request): WP_REST_Response {
        $limit  = (int) $request->get_param('per_page');
        $offset = ((int) $request->get_param('page') - 1) * $limit;

        $puzzles = $this->db->get_puzzles([
            'limit'   => $limit,
            'offset'  => $offset,
            'orderby' => $request->get_param('orderby'),
            'order'   => $request->get_param('order'),
        ]);
        $total = $this->db->count_puzzles();

        $items = array_map(function ($p) {
            $constructors = $this->db->get_puzzle_constructors((int) $p->id);
            $day_name = date('l', strtotime($p->publication_date));
            return [
                'id'               => (int) $p->id,
                'publication_date' => $p->publication_date,
                'day_of_week'      => $day_name,
                'editor_id'        => $p->editor_id ? (int) $p->editor_id : null,
                'editor_name'      => $p->editor_name ?? '',
                'constructors'     => array_map(fn($c) => [
                    'id'        => (int) $c->id,
                    'full_name' => $c->full_name,
                    'url'       => home_url('/kealoa/person/' . urlencode(str_replace(' ', '_', $c->full_name)) . '/'),
                ], $constructors),
                'url'              => home_url('/kealoa/puzzle/' . $p->publication_date . '/'),
            ];
        }, $puzzles);

        return $this->paginated_response($items, $total, $limit, (int) $request->get_param('page'));
    }

    public function get_puzzle(WP_REST_Request $request): WP_REST_Response {
        $id     = (int) $request->get_param('id');
        $puzzle = $this->db->get_puzzle($id);

        if (!$puzzle) {
            return new WP_REST_Response(['message' => 'Puzzle not found.'], 404);
        }

        $constructors   = $this->db->get_puzzle_constructors($id);
        $clues          = $this->db->get_puzzle_clues($id);
        $player_results = $this->db->get_puzzle_player_results($id);

        // Group clues by round
        $rounds_clues = [];
        foreach ($clues as $clue) {
            $rid = (int) $clue->round_id;
            if (!isset($rounds_clues[$rid])) {
                $rounds_clues[$rid] = [
                    'round_id'       => $rid,
                    'round_date'     => $clue->round_date,
                    'round_number'   => (int) ($clue->round_number ?? 1),
                    'episode_number' => (int) ($clue->episode_number ?? 0),
                    'round_url'      => home_url('/kealoa/round/' . $rid . '/'),
                    'clues'          => [],
                ];
            }

            $clue_guesses = $this->db->get_clue_guesses((int) $clue->id);
            $rounds_clues[$rid]['clues'][] = [
                'id'                    => (int) $clue->id,
                'clue_number'           => (int) $clue->clue_number,
                'puzzle_clue_number'    => $clue->puzzle_clue_number ? (int) $clue->puzzle_clue_number : null,
                'puzzle_clue_direction' => $clue->puzzle_clue_direction ?? null,
                'clue_text'             => $clue->clue_text,
                'correct_answer'        => $clue->correct_answer,
                'guesses'               => array_map(fn($g) => [
                    'guesser_name' => $g->guesser_name,
                    'guessed_word' => $g->guessed_word,
                    'is_correct'   => (bool) $g->is_correct,
                ], $clue_guesses),
            ];
        }

        // Get solution words for each round
        foreach ($rounds_clues as $rid => &$rc) {
            $solutions = $this->db->get_round_solutions($rid);
            $rc['solution_words'] = array_map(fn($s) => $s->word, $solutions);
        }
        unset($rc);

        // Look up editor person
        $editor_name = '';
        $editor_id = null;
        if (!empty($puzzle->editor_id)) {
            $editor = $this->db->get_person((int) $puzzle->editor_id);
            if ($editor) {
                $editor_name = $editor->full_name;
                $editor_id = (int) $editor->id;
            }
        }

        $day_name = date('l', strtotime($puzzle->publication_date));

        return new WP_REST_Response([
            'id'               => (int) $puzzle->id,
            'publication_date' => $puzzle->publication_date,
            'day_of_week'      => $day_name,
            'editor_id'        => $editor_id,
            'editor_name'      => $editor_name,
            'constructors'     => array_map(fn($c) => [
                'id'        => (int) $c->id,
                'full_name' => $c->full_name,
                'url'       => home_url('/kealoa/person/' . urlencode(str_replace(' ', '_', $c->full_name)) . '/'),
            ], $constructors),
            'rounds'           => array_values($rounds_clues),
            'player_results'   => array_map(fn($r) => [
                'person_id'       => (int) $r->person_id,
                'full_name'       => $r->full_name,
                'total_guesses'   => (int) $r->total_guesses,
                'correct_guesses' => (int) $r->correct_guesses,
                'accuracy'        => $r->total_guesses > 0
                    ? round(($r->correct_guesses / $r->total_guesses) * 100, 1)
                    : 0,
                'url'             => home_url('/kealoa/person/' . urlencode(str_replace(' ', '_', $r->full_name)) . '/'),
            ], $player_results),
            'xwordinfo_url'    => 'https://www.xwordinfo.com/Crossword?date=' . $puzzle->publication_date,
            'url'              => home_url('/kealoa/puzzle/' . $puzzle->publication_date . '/'),
        ], 200);
    }

    // =========================================================================
    // CLUES
    // =========================================================================

    public function get_clue(WP_REST_Request $request): WP_REST_Response {
        $id   = (int) $request->get_param('id');
        $clue = $this->db->get_clue($id);

        if (!$clue) {
            return new WP_REST_Response(['message' => 'Clue not found.'], 404);
        }

        $guesses = $this->db->get_clue_guesses($id);

        $constructors = '';
        if (!empty($clue->puzzle_id)) {
            $pc    = $this->db->get_puzzle_constructors((int) $clue->puzzle_id);
            $names = array_map(fn($c) => $c->full_name, $pc);
            $constructors = implode(' & ', $names);
        }

        return new WP_REST_Response([
            'id'                    => (int) $clue->id,
            'round_id'              => (int) $clue->round_id,
            'clue_number'           => (int) $clue->clue_number,
            'puzzle_id'             => $clue->puzzle_id ? (int) $clue->puzzle_id : null,
            'puzzle_date'           => $clue->puzzle_date ?? '',
            'constructors'          => $constructors,
            'editor'                => $clue->editor_name ?? '',
            'puzzle_clue_number'    => $clue->puzzle_clue_number ? (int) $clue->puzzle_clue_number : null,
            'puzzle_clue_direction' => $clue->puzzle_clue_direction ?? null,
            'clue_text'             => $clue->clue_text,
            'correct_answer'        => $clue->correct_answer,
            'guesses'               => array_map(fn($g) => [
                'guesser_name' => $g->guesser_name,
                'guessed_word' => $g->guessed_word,
                'is_correct'   => (bool) $g->is_correct,
            ], $guesses),
        ], 200);
    }

    // =========================================================================
    // SEARCH
    // =========================================================================

    public function search(WP_REST_Request $request): WP_REST_Response {
        $term    = $request->get_param('q');
        $results = $this->db->search_all($term);

        return new WP_REST_Response([
            'query'   => $term,
            'count'   => count($results),
            'results' => $results,
        ], 200);
    }

    // =========================================================================
    // LEADERBOARDS
    // =========================================================================

    public function get_leaderboard_scores(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response($this->db->get_all_persons_highest_round_scores(), 200);
    }

    public function get_leaderboard_streaks(WP_REST_Request $request): WP_REST_Response {
        return new WP_REST_Response($this->db->get_all_persons_longest_streaks(), 200);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Standard pagination args merged with custom args.
     */
    private function pagination_args(array $extra = []): array {
        return array_merge([
            'page' => [
                'default'           => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => function ($v) {
                    return is_numeric($v) && (int) $v >= 1;
                },
            ],
            'per_page' => [
                'default'           => 50,
                'sanitize_callback' => 'absint',
                'validate_callback' => function ($v) {
                    return is_numeric($v) && (int) $v >= 1 && (int) $v <= 500;
                },
            ],
        ], $extra);
    }

    /**
     * Standard numeric ID arg.
     */
    private function id_arg(): array {
        return [
            'id' => [
                'required'          => true,
                'validate_callback' => function ($v) {
                    return is_numeric($v) && (int) $v > 0;
                },
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /**
     * Build a paginated response with headers.
     */
    private function paginated_response(array $items, int $total, int $per_page, int $page): WP_REST_Response {
        $total_pages = (int) ceil($total / max($per_page, 1));

        $response = new WP_REST_Response([
            'total'       => $total,
            'total_pages' => $total_pages,
            'page'        => $page,
            'per_page'    => $per_page,
            'items'       => $items,
        ], 200);

        $response->header('X-WP-Total', (string) $total);
        $response->header('X-WP-TotalPages', (string) $total_pages);

        return $response;
    }
}
