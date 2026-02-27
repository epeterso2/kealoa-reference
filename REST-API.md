# KEALOA Reference REST API

Read-only REST API for KEALOA Reference data.

**Base URL:** `/wp-json/kealoa/v1`
**Version:** 2.0.73

All endpoints use the `GET` method and are publicly accessible (`permission_callback: __return_true`). All responses are JSON.

---

## Table of Contents

1. [Common Conventions](#common-conventions)
   - [Pagination](#pagination)
   - [ID Parameters](#id-parameters)
   - [Error Responses](#error-responses)
2. [Game](#game)
   - [GET /game-round/{id}](#get-game-roundid)
3. [Rounds](#rounds)
   - [GET /rounds](#get-rounds)
   - [GET /rounds/stats](#get-roundsstats)
   - [GET /rounds/{id}](#get-roundsid)
4. [Persons](#persons)
   - [GET /persons](#get-persons)
   - [GET /persons/{id}](#get-personsid)
   - [GET /persons/{id}/rounds](#get-personsidrounds)
   - [GET /persons/{id}/puzzles](#get-personsidpuzzles)
   - [GET /persons/{id}/stats/by-year](#get-personsidstatsby-year)
   - [GET /persons/{id}/stats/by-day](#get-personsidstatsby-day)
   - [GET /persons/{id}/stats/by-constructor](#get-personsidstatsby-constructor)
   - [GET /persons/{id}/stats/by-editor](#get-personsidstatsby-editor)
   - [GET /persons/{id}/stats/by-direction](#get-personsidstatsby-direction)
   - [GET /persons/{id}/stats/by-length](#get-personsidstatsby-length)
   - [GET /persons/{id}/stats/by-decade](#get-personsidstatsby-decade)
   - [GET /persons/{id}/stats/by-clue-number](#get-personsidstatsby-clue-number)
   - [GET /persons/{id}/stats/streaks](#get-personsidstatsstreaks)
5. [Puzzles](#puzzles)
   - [GET /puzzles](#get-puzzles)
   - [GET /puzzles/{id}](#get-puzzlesid)
6. [Clues](#clues)
   - [GET /clues/{id}](#get-cluesid)
7. [Search](#search)
   - [GET /search](#get-search)
8. [Leaderboards](#leaderboards)
   - [GET /leaderboard/scores](#get-leaderboardscores)
   - [GET /leaderboard/streaks](#get-leaderboardstreaks)

---

## Common Conventions

### Pagination

The following endpoints return paginated results: `/rounds`, `/persons`, `/puzzles`.

**Query parameters:**

| Parameter  | Type    | Default | Min | Max   | Description        |
|---|---|---:|---:|---:|---|
| `page`     | integer | `1`     | `1` | —     | 1-based page index |
| `per_page` | integer | `50`    | `1` | `500` | Items per page     |

**Response envelope:**

```json
{
  "total": 120,
  "total_pages": 3,
  "page": 1,
  "per_page": 50,
  "items": []
}
```

**Response headers:**

| Header              | Description                     |
|---|---|
| `X-WP-Total`        | Total number of matching items  |
| `X-WP-TotalPages`   | Total number of pages           |

### ID Parameters

Endpoints that include `{id}` in the path require a positive integer (`>= 1`).

### Error Responses

When an entity with the given ID does not exist, the endpoint returns HTTP `404` with:

```json
{ "message": "<Entity> not found." }
```

| Endpoint family  | Message                |
|---|---|
| `/rounds/{id}`   | `"Round not found."`   |
| `/persons/{id}`  | `"Person not found."`  |
| `/puzzles/{id}`  | `"Puzzle not found."`  |
| `/clues/{id}`    | `"Clue not found."`    |

---

## Game

### GET /game-round/{id}

Returns the complete payload needed by the **KEALOA Play Game** Gutenberg block to run an interactive game session for the specified round.

**Path parameters:**

| Parameter | Type    | Description |
|---|---|---|
| `id`      | integer | Round ID    |

**Response: 200 OK**

```json
{
  "round_id": 1,
  "round_url": "https://example.com/kealoa/round/1/",
  "description": "Optional description of the round",
  "description2": "Optional secondary description",
  "clue_giver": "Alex Smith",
  "players": [
    "Jane Doe",
    "Bob Jones"
  ],
  "solution_words": [
    "PANDA",
    "PANEL"
  ],
  "clues": [
    {
      "clue_number": 1,
      "puzzle_date": "2024-01-10",
      "constructors": "Amanda Chung & Karl Ni",
      "editor": "Will Shortz",
      "puzzle_clue_number": 42,
      "puzzle_clue_direction": "A",
      "clue_text": "Bamboo-eating bear",
      "correct_answer": "PANDA",
      "guesses": [
        {
          "guesser_name": "Jane Doe",
          "guessed_word": "PANDA",
          "is_correct": true
        },
        {
          "guesser_name": "Bob Jones",
          "guessed_word": "PANEL",
          "is_correct": false
        }
      ]
    }
  ],
  "guesser_results": [
    {
      "full_name": "Jane Doe",
      "total_guesses": 3,
      "correct_guesses": 3
    },
    {
      "full_name": "Bob Jones",
      "total_guesses": 3,
      "correct_guesses": 2
    }
  ]
}
```

**Response: 404 Not Found**

```json
{ "message": "Round not found." }
```

---

## Rounds

### GET /rounds

Returns a paginated list of rounds.

**Query parameters:**

Supports [pagination parameters](#pagination) plus:

| Parameter | Type   | Default      | Description                        |
|---|---|---|---|
| `orderby` | string | `round_date` | Column to sort by (sanitized text) |
| `order`   | string | `DESC`       | Sort direction: `ASC` or `DESC`    |

**Response: 200 OK**

```json
{
  "total": 80,
  "total_pages": 2,
  "page": 1,
  "per_page": 50,
  "items": [
    {
      "id": 1,
      "round_date": "2024-03-15",
      "round_number": 1,
      "episode_number": 100,
      "clue_giver": "Alex Smith",
      "solution_words": ["PANDA", "PANEL"],
      "url": "https://example.com/kealoa/round/1/"
    }
  ]
}
```

---

### GET /rounds/stats

Returns aggregate statistics across all rounds.

**Query parameters:** None

**Response: 200 OK**

```json
{
  "overview": {},
  "by_year": []
}
```

The `overview` object and `by_year` array contain aggregate fields computed by the database layer. The exact fields depend on the data present.

---

### GET /rounds/{id}

Returns full detail for a single round, including all clues, all guesses, player results, and navigation to adjacent rounds.

**Path parameters:**

| Parameter | Type    | Description |
|---|---|---|
| `id`      | integer | Round ID    |

**Response: 200 OK**

```json
{
  "id": 1,
  "round_date": "2024-03-15",
  "round_number": 1,
  "episode_number": 100,
  "episode_id": "abc123",
  "episode_url": "https://bemoresmarter.libsyn.com/episode/100",
  "episode_start_time": "00:12:34",
  "description": "Optional description",
  "description2": "Optional secondary description",
  "clue_giver": "Alex Smith",
  "players": [
    { "id": 2, "full_name": "Jane Doe" },
    { "id": 3, "full_name": "Bob Jones" }
  ],
  "solution_words": ["PANDA", "PANEL"],
  "clues": [
    {
      "id": 10,
      "clue_number": 1,
      "puzzle_id": 5,
      "puzzle_date": "2024-01-10",
      "constructors": "Amanda Chung & Karl Ni",
      "editor": "Will Shortz",
      "puzzle_clue_number": 42,
      "puzzle_clue_direction": "A",
      "clue_text": "Bamboo-eating bear",
      "correct_answer": "PANDA",
      "guesses": [
        {
          "guesser_name": "Jane Doe",
          "guessed_word": "PANDA",
          "is_correct": true
        }
      ]
    }
  ],
  "guesser_results": [
    {
      "full_name": "Jane Doe",
      "total_guesses": 3,
      "correct_guesses": 3
    }
  ],
  "previous_round_id": null,
  "next_round_id": 2,
  "url": "https://example.com/kealoa/round/1/"
}
```

**Response: 404 Not Found**

```json
{ "message": "Round not found." }
```

---

## Persons

### GET /persons

Returns a paginated list of persons. When `role` is omitted, each item includes the full `roles` array. When `role` is specified, the items omit `roles`.

**Query parameters:**

Supports [pagination parameters](#pagination) plus:

| Parameter | Type   | Default | Description                                               |
|---|---|---|---|
| `search`  | string | `""`    | Filter by name substring (case-insensitive)               |
| `role`    | string | `""`    | Restrict to persons with this role: `player`, `constructor`, or `editor` |

**Response (no role filter): 200 OK**

```json
{
  "total": 25,
  "total_pages": 1,
  "page": 1,
  "per_page": 50,
  "items": [
    {
      "id": 1,
      "full_name": "Jane Doe",
      "roles": ["player", "constructor"],
      "url": "https://example.com/kealoa/person/Jane_Doe/"
    }
  ]
}
```

**Response (with role filter): 200 OK**

```json
{
  "total": 10,
  "total_pages": 1,
  "page": 1,
  "per_page": 50,
  "items": [
    {
      "id": 1,
      "full_name": "Jane Doe",
      "url": "https://example.com/kealoa/person/Jane_Doe/"
    }
  ]
}
```

---

### GET /persons/{id}

Returns the full profile for a single person including role-specific stats.

**Path parameters:**

| Parameter | Type    | Description |
|---|---|---|
| `id`      | integer | Person ID   |

**Response: 200 OK**

```json
{
  "id": 1,
  "full_name": "Jane Doe",
  "nicknames": "",
  "home_page_url": "https://janedoe.example.com",
  "xwordinfo_profile_name": "Jane Doe",
  "xwordinfo_image_url": "https://www.xwordinfo.com/photos/jane_doe.jpg",
  "roles": ["player", "constructor"],
  "stats": {},
  "constructor_stats": {},
  "url": "https://example.com/kealoa/person/Jane_Doe/"
}
```

Notes:
- `stats` contains aggregate player stats; fields depend on data present.
- `constructor_stats` is only included when the person has the `constructor` role.
- `editor_stats` is only included when the person has the `editor` role.
- `clue_giver_stats` is only included when the person has the `clue_giver` role.

**Response: 404 Not Found**

```json
{ "message": "Person not found." }
```

---

### GET /persons/{id}/rounds

Returns all rounds in which the person participated as a guesser.

**Path parameters:**

| Parameter | Type    | Description |
|---|---|---|
| `id`      | integer | Person ID   |

**Response: 200 OK**

```json
{
  "person_id": 1,
  "full_name": "Jane Doe",
  "rounds": []
}
```

The `rounds` array contains round summary objects with fields matching those returned by `GET /rounds` items.

**Response: 404 Not Found**

```json
{ "message": "Person not found." }
```

---

### GET /persons/{id}/puzzles

Returns all puzzles associated with rounds that the person participated in (as a guesser).

**Path parameters:**

| Parameter | Type    | Description |
|---|---|---|
| `id`      | integer | Person ID   |

**Response: 200 OK**

```json
{
  "person_id": 1,
  "full_name": "Jane Doe",
  "total": 25,
  "puzzles": [
    {
      "puzzle_id": 10,
      "publication_date": "2024-01-10",
      "day_of_week": "Wednesday",
      "editor_id": 3,
      "editor_name": "Will Shortz",
      "constructors": [
        {
          "id": 5,
          "full_name": "Amanda Chung",
          "url": "https://example.com/kealoa/person/Amanda_Chung/"
        }
      ],
      "round_ids": [1, 4],
      "round_dates": ["2024-03-15", "2024-06-20"],
      "url": "https://example.com/kealoa/puzzle/2024-01-10/"
    }
  ]
}
```

**Response: 404 Not Found**

```json
{ "message": "Person not found." }
```

---

### GET /persons/{id}/stats/by-year

Returns the person's guess results grouped by calendar year.

**Path parameters:**

| Parameter | Type    | Description |
|---|---|---|
| `id`      | integer | Person ID   |

**Response: 200 OK** — Array of objects with aggregated totals for each year.

**Response: 404 Not Found**

```json
{ "message": "Person not found." }
```

---

### GET /persons/{id}/stats/by-day

Returns the person's guess results grouped by day of week.

**Path parameters:**

| Parameter | Type    | Description |
|---|---|---|
| `id`      | integer | Person ID   |

**Response: 200 OK** — Array of objects, one per day of the week (Monday–Sunday).

**Response: 404 Not Found**

```json
{ "message": "Person not found." }
```

---

### GET /persons/{id}/stats/by-constructor

Returns the person's guess results grouped by puzzle constructor.

**Path parameters:**

| Parameter | Type    | Description |
|---|---|---|
| `id`      | integer | Person ID   |

**Response: 200 OK** — Array of objects, one per constructor.

**Response: 404 Not Found**

```json
{ "message": "Person not found." }
```

---

### GET /persons/{id}/stats/by-editor

Returns the person's guess results grouped by puzzle editor.

**Path parameters:**

| Parameter | Type    | Description |
|---|---|---|
| `id`      | integer | Person ID   |

**Response: 200 OK** — Array of objects, one per editor.

**Response: 404 Not Found**

```json
{ "message": "Person not found." }
```

---

### GET /persons/{id}/stats/by-direction

Returns the person's guess results grouped by crossword clue direction (Across / Down).

**Path parameters:**

| Parameter | Type    | Description |
|---|---|---|
| `id`      | integer | Person ID   |

**Response: 200 OK** — Array of objects, one per direction.

**Response: 404 Not Found**

```json
{ "message": "Person not found." }
```

---

### GET /persons/{id}/stats/by-length

Returns the person's guess results grouped by answer length (number of letters).

**Path parameters:**

| Parameter | Type    | Description |
|---|---|---|
| `id`      | integer | Person ID   |

**Response: 200 OK** — Array of objects, one per answer length.

**Response: 404 Not Found**

```json
{ "message": "Person not found." }
```

---

### GET /persons/{id}/stats/by-decade

Returns the person's guess results grouped by the puzzle's publication decade.

**Path parameters:**

| Parameter | Type    | Description |
|---|---|---|
| `id`      | integer | Person ID   |

**Response: 200 OK** — Array of objects, one per decade.

**Response: 404 Not Found**

```json
{ "message": "Person not found." }
```

---

### GET /persons/{id}/stats/by-clue-number

Returns the person's guess results grouped by clue number within a round (clue 1, clue 2, etc.).

**Path parameters:**

| Parameter | Type    | Description |
|---|---|---|
| `id`      | integer | Person ID   |

**Response: 200 OK** — Array of objects, one per clue position.

**Response: 404 Not Found**

```json
{ "message": "Person not found." }
```

---

### GET /persons/{id}/stats/streaks

Returns the person's best consecutive correct-guess streaks grouped by year.

**Path parameters:**

| Parameter | Type    | Description |
|---|---|---|
| `id`      | integer | Person ID   |

**Response: 200 OK** — Array of objects containing the year and the best streak length for that year.

**Response: 404 Not Found**

```json
{ "message": "Person not found." }
```

---

## Puzzles

### GET /puzzles

Returns a paginated list of puzzles.

**Query parameters:**

Supports [pagination parameters](#pagination) plus:

| Parameter | Type   | Default            | Description                        |
|---|---|---|---|
| `orderby` | string | `publication_date` | Column to sort by (sanitized text) |
| `order`   | string | `DESC`             | Sort direction: `ASC` or `DESC`    |

**Response: 200 OK**

```json
{
  "total": 300,
  "total_pages": 6,
  "page": 1,
  "per_page": 50,
  "items": [
    {
      "id": 10,
      "publication_date": "2024-01-10",
      "day_of_week": "Wednesday",
      "editor_id": 3,
      "editor_name": "Will Shortz",
      "constructors": [
        {
          "id": 5,
          "full_name": "Amanda Chung",
          "url": "https://example.com/kealoa/person/Amanda_Chung/"
        }
      ],
      "url": "https://example.com/kealoa/puzzle/2024-01-10/"
    }
  ]
}
```

---

### GET /puzzles/{id}

Returns full detail for a single puzzle, including all rounds that used clues from it, all clues and guesses per round, and aggregated player results.

**Path parameters:**

| Parameter | Type    | Description |
|---|---|---|
| `id`      | integer | Puzzle ID   |

**Response: 200 OK**

```json
{
  "id": 10,
  "publication_date": "2024-01-10",
  "day_of_week": "Wednesday",
  "editor_id": 3,
  "editor_name": "Will Shortz",
  "constructors": [
    {
      "id": 5,
      "full_name": "Amanda Chung",
      "url": "https://example.com/kealoa/person/Amanda_Chung/"
    }
  ],
  "rounds": [
    {
      "round_id": 1,
      "round_date": "2024-03-15",
      "round_number": 1,
      "episode_number": 100,
      "round_url": "https://example.com/kealoa/round/1/",
      "solution_words": ["PANDA", "PANEL"],
      "clues": [
        {
          "id": 10,
          "clue_number": 1,
          "puzzle_clue_number": 42,
          "puzzle_clue_direction": "A",
          "clue_text": "Bamboo-eating bear",
          "correct_answer": "PANDA",
          "guesses": [
            {
              "guesser_name": "Jane Doe",
              "guessed_word": "PANDA",
              "is_correct": true
            }
          ]
        }
      ]
    }
  ],
  "player_results": [
    {
      "person_id": 2,
      "full_name": "Jane Doe",
      "total_guesses": 3,
      "correct_guesses": 3,
      "accuracy": 100.0,
      "url": "https://example.com/kealoa/person/Jane_Doe/"
    }
  ],
  "xwordinfo_url": "https://www.xwordinfo.com/Crossword?date=2024-01-10",
  "url": "https://example.com/kealoa/puzzle/2024-01-10/"
}
```

**Response: 404 Not Found**

```json
{ "message": "Puzzle not found." }
```

---

## Clues

### GET /clues/{id}

Returns full detail for a single clue, including its puzzle context and all guesses.

**Path parameters:**

| Parameter | Type    | Description |
|---|---|---|
| `id`      | integer | Clue ID     |

**Response: 200 OK**

```json
{
  "id": 10,
  "round_id": 1,
  "clue_number": 3,
  "puzzle_id": 5,
  "puzzle_date": "2024-01-10",
  "constructors": "Amanda Chung & Karl Ni",
  "editor": "Will Shortz",
  "puzzle_clue_number": 42,
  "puzzle_clue_direction": "A",
  "clue_text": "Bamboo-eating bear",
  "correct_answer": "PANDA",
  "guesses": [
    {
      "guesser_name": "Jane Doe",
      "guessed_word": "PANDA",
      "is_correct": true
    },
    {
      "guesser_name": "Bob Jones",
      "guessed_word": "PANEL",
      "is_correct": false
    }
  ]
}
```

Note: `constructors` and `editor` are plain string values (formatted display strings), not object arrays.

**Response: 404 Not Found**

```json
{ "message": "Clue not found." }
```

---

## Search

### GET /search

Performs a full-text search across persons, rounds, and puzzles.

**Query parameters:**

| Parameter | Type   | Required | Validation                             |
|---|---|---|---|
| `q`       | string | Yes      | Minimum trimmed length of 2 characters |

**Response: 200 OK**

```json
{
  "query": "smith",
  "count": 3,
  "results": []
}
```

The `results` array contains mixed entity objects matching the query. The structure of each result object depends on the matched entity type.

**Response: 400 Bad Request** — when `q` is missing or shorter than 2 characters after trimming.

---

## Leaderboards

### GET /leaderboard/scores

Returns all persons ranked by their highest single-round score.

**Query parameters:** None

**Response: 200 OK** — Array of objects with person identity fields and score data.

---

### GET /leaderboard/streaks

Returns all persons ranked by their longest correct-guess streak across all rounds.

**Query parameters:** None

**Response: 200 OK** — Array of objects with person identity fields and streak data.

