# KEALOA Reference REST API

Read-only REST API for accessing all KEALOA Reference data.

**Base URL:** `/wp-json/kealoa/v1`

All endpoints are public (no authentication required). All responses are JSON.

---

## Table of Contents

- [Pagination](#pagination)
- [Rounds](#rounds)
  - [List Rounds](#list-rounds)
  - [Get Round](#get-round)
  - [Rounds Stats](#rounds-stats)
- [Persons](#persons)
  - [List Persons](#list-persons)
  - [Get Person](#get-person)
  - [Person Rounds](#person-rounds)
  - [Person Puzzles](#person-puzzles)
  - [Person Stats by Year](#person-stats-by-year)
  - [Person Stats by Day of Week](#person-stats-by-day-of-week)
  - [Person Stats by Constructor](#person-stats-by-constructor)
  - [Person Stats by Editor](#person-stats-by-editor)
  - [Person Stats by Direction](#person-stats-by-direction)
  - [Person Stats by Answer Length](#person-stats-by-answer-length)
  - [Person Stats by Decade](#person-stats-by-decade)
  - [Person Stats by Clue Number](#person-stats-by-clue-number)
  - [Person Streaks](#person-streaks)
- [Puzzles](#puzzles)
  - [List Puzzles](#list-puzzles)
  - [Get Puzzle](#get-puzzle)
- [Clues](#clues)
  - [Get Clue](#get-clue)
- [Search](#search)
- [Leaderboards](#leaderboards)
  - [Leaderboard: Scores](#leaderboard-scores)
  - [Leaderboard: Streaks](#leaderboard-streaks)

---

## Pagination

Paginated endpoints accept these query parameters:

| Parameter  | Type    | Default | Description                        |
|------------|---------|---------|------------------------------------|
| `page`     | integer | `1`     | Page number (1-based).             |
| `per_page` | integer | `50`    | Items per page (1–500).            |

Paginated responses include:

```json
{
  "total": 123,
  "total_pages": 3,
  "page": 1,
  "per_page": 50,
  "items": [ ... ]
}
```

Response headers `X-WP-Total` and `X-WP-TotalPages` are also set.

---

## Rounds

### List Rounds

```
GET /rounds
```

Returns a paginated list of rounds.

**Parameters:**

| Parameter | Type    | Default      | Description                              |
|-----------|---------|--------------|------------------------------------------|
| `page`    | integer | `1`          | Page number.                             |
| `per_page`| integer | `50`         | Items per page (1–500).                  |
| `orderby` | string  | `round_date` | Field to sort by.                        |
| `order`   | string  | `DESC`       | Sort direction: `ASC` or `DESC`.         |

**Response item:**

```json
{
  "id": 1,
  "round_date": "2024-01-15",
  "round_number": 1,
  "episode_number": 100,
  "clue_giver": "John Doe",
  "solution_words": ["WORD1", "WORD2"],
  "url": "https://example.com/kealoa/round/1/"
}
```

---

### Get Round

```
GET /rounds/{id}
```

Returns full details for a single round, including players, clues, guesses, and guesser results.

**Parameters:**

| Parameter | Type    | Required | Description   |
|-----------|---------|----------|---------------|
| `id`      | integer | Yes      | Round ID.     |

**Response:**

```json
{
  "id": 1,
  "round_date": "2024-01-15",
  "round_number": 1,
  "episode_number": 100,
  "episode_id": "abc123",
  "episode_url": "https://...",
  "episode_start_time": "00:15:30",
  "description": "...",
  "description2": "...",
  "clue_giver": "John Doe",
  "players": [
    { "id": 2, "full_name": "Jane Smith" }
  ],
  "solution_words": ["WORD1", "WORD2"],
  "clues": [
    {
      "id": 10,
      "clue_number": 1,
      "puzzle_id": 5,
      "puzzle_date": "2024-01-10",
      "constructors": "Constructor A & Constructor B",
      "editor": "Will Shortz",
      "puzzle_clue_number": 42,
      "puzzle_clue_direction": "Across",
      "clue_text": "A hint for the answer",
      "correct_answer": "ANSWER",
      "guesses": [
        {
          "guesser_name": "Jane Smith",
          "guessed_word": "ANSWER",
          "is_correct": true
        }
      ]
    }
  ],
  "guesser_results": [
    {
      "full_name": "Jane Smith",
      "total_guesses": 5,
      "correct_guesses": 4
    }
  ],
  "previous_round_id": null,
  "next_round_id": 2,
  "url": "https://example.com/kealoa/round/1/"
}
```

**Errors:**

| Status | Description      |
|--------|------------------|
| 404    | Round not found. |

---

### Rounds Stats

```
GET /rounds/stats
```

Returns overview statistics and per-year breakdown for all rounds.

**Response:**

```json
{
  "overview": { ... },
  "by_year": [ ... ]
}
```

---

## Persons

### List Persons

```
GET /persons
```

Returns a paginated list of persons. Can be filtered by role.

**Parameters:**

| Parameter  | Type    | Default | Description                                                       |
|------------|---------|---------|-------------------------------------------------------------------|
| `page`     | integer | `1`     | Page number.                                                      |
| `per_page` | integer | `50`    | Items per page (1–500).                                           |
| `search`   | string  | `""`    | Filter by name (partial match).                                   |
| `role`     | string  | `""`    | Filter by role: `player`, `constructor`, or `editor`. Empty = all.|

**Response item:**

```json
{
  "id": 1,
  "full_name": "Jane Smith",
  "url": "https://example.com/kealoa/person/Jane_Smith/"
}
```

---

### Get Person

```
GET /persons/{id}
```

Returns a single person with their stats.

**Parameters:**

| Parameter | Type    | Required | Description   |
|-----------|---------|----------|---------------|
| `id`      | integer | Yes      | Person ID.    |

**Response:**

```json
{
  "id": 1,
  "full_name": "Jane Smith",
  "home_page_url": "https://...",
  "roles": ["player", "constructor"],
  "xwordinfo_profile_name": "Jane_Smith",
  "xwordinfo_image_url": "https://www.xwordinfo.com/images/cons/JaneSmith.jpg",
  "stats": { ... },
  "constructor_stats": { ... },
  "editor_stats": { ... },
  "url": "https://example.com/kealoa/person/Jane_Smith/"
}
```

> `constructor_stats` and `editor_stats` are included only when the person has those roles.

**Errors:**

| Status | Description       |
|--------|-------------------|
| 404    | Person not found. |

---

### Person Rounds

```
GET /persons/{id}/rounds
```

Returns the complete round history for a person.

**Parameters:**

| Parameter | Type    | Required | Description   |
|-----------|---------|----------|---------------|
| `id`      | integer | Yes      | Person ID.    |

**Response:**

```json
{
  "person_id": 1,
  "full_name": "Jane Smith",
  "rounds": [ ... ]
}
```

**Errors:**

| Status | Description       |
|--------|-------------------|
| 404    | Person not found. |

---

### Person Puzzles

```
GET /persons/{id}/puzzles
```

Returns all puzzles from rounds the person has played.

**Parameters:**

| Parameter | Type    | Required | Description   |
|-----------|---------|----------|---------------|
| `id`      | integer | Yes      | Person ID.    |

**Response:**

```json
{
  "person_id": 1,
  "full_name": "Jane Smith",
  "total": 25,
  "puzzles": [
    {
      "puzzle_id": 10,
      "publication_date": "2024-01-10",
      "day_of_week": "Wednesday",
      "editor_name": "Will Shortz",
      "constructors": [
        { "id": 1, "full_name": "Constructor Name" }
      ],
      "round_ids": [5, 12],
      "round_dates": ["2024-03-15", "2024-06-01"],
      "url": "https://example.com/kealoa/puzzle/2024-01-10/"
    }
  ]
}
```

**Errors:**

| Status | Description       |
|--------|-------------------|
| 404    | Person not found. |

---

### Person Stats by Year

```
GET /persons/{id}/stats/by-year
```

Returns the person's results broken down by year.

**Parameters:**

| Parameter | Type    | Required | Description   |
|-----------|---------|----------|---------------|
| `id`      | integer | Yes      | Person ID.    |

**Errors:**

| Status | Description       |
|--------|-------------------|
| 404    | Person not found. |

---

### Person Stats by Day of Week

```
GET /persons/{id}/stats/by-day
```

Returns the person's results broken down by day of week.

**Parameters:**

| Parameter | Type    | Required | Description   |
|-----------|---------|----------|---------------|
| `id`      | integer | Yes      | Person ID.    |

**Errors:**

| Status | Description       |
|--------|-------------------|
| 404    | Person not found. |

---

### Person Stats by Constructor

```
GET /persons/{id}/stats/by-constructor
```

Returns the person's results broken down by puzzle constructor.

**Parameters:**

| Parameter | Type    | Required | Description   |
|-----------|---------|----------|---------------|
| `id`      | integer | Yes      | Person ID.    |

**Errors:**

| Status | Description       |
|--------|-------------------|
| 404    | Person not found. |

---

### Person Stats by Editor

```
GET /persons/{id}/stats/by-editor
```

Returns the person's results broken down by puzzle editor.

**Parameters:**

| Parameter | Type    | Required | Description   |
|-----------|---------|----------|---------------|
| `id`      | integer | Yes      | Person ID.    |

**Errors:**

| Status | Description       |
|--------|-------------------|
| 404    | Person not found. |

---

### Person Stats by Direction

```
GET /persons/{id}/stats/by-direction
```

Returns the person's results broken down by clue direction (Across/Down).

**Parameters:**

| Parameter | Type    | Required | Description   |
|-----------|---------|----------|---------------|
| `id`      | integer | Yes      | Person ID.    |

**Errors:**

| Status | Description       |
|--------|-------------------|
| 404    | Person not found. |

---

### Person Stats by Answer Length

```
GET /persons/{id}/stats/by-length
```

Returns the person's results broken down by answer word length.

**Parameters:**

| Parameter | Type    | Required | Description   |
|-----------|---------|----------|---------------|
| `id`      | integer | Yes      | Person ID.    |

**Errors:**

| Status | Description       |
|--------|-------------------|
| 404    | Person not found. |

---

### Person Stats by Decade

```
GET /persons/{id}/stats/by-decade
```

Returns the person's results broken down by puzzle publication decade.

**Parameters:**

| Parameter | Type    | Required | Description   |
|-----------|---------|----------|---------------|
| `id`      | integer | Yes      | Person ID.    |

**Errors:**

| Status | Description       |
|--------|-------------------|
| 404    | Person not found. |

---

### Person Stats by Clue Number

```
GET /persons/{id}/stats/by-clue-number
```

Returns the person's results broken down by clue number within the round.

**Parameters:**

| Parameter | Type    | Required | Description   |
|-----------|---------|----------|---------------|
| `id`      | integer | Yes      | Person ID.    |

**Errors:**

| Status | Description       |
|--------|-------------------|
| 404    | Person not found. |

---

### Person Streaks

```
GET /persons/{id}/stats/streaks
```

Returns the person's best correct-answer streaks by year.

**Parameters:**

| Parameter | Type    | Required | Description   |
|-----------|---------|----------|---------------|
| `id`      | integer | Yes      | Person ID.    |

**Errors:**

| Status | Description       |
|--------|-------------------|
| 404    | Person not found. |

---

## Puzzles

### List Puzzles

```
GET /puzzles
```

Returns a paginated list of puzzles with their constructors.

**Parameters:**

| Parameter  | Type    | Default            | Description                      |
|------------|---------|--------------------|----------------------------------|
| `page`     | integer | `1`                | Page number.                     |
| `per_page` | integer | `50`               | Items per page (1–500).          |
| `orderby`  | string  | `publication_date` | Field to sort by.                |
| `order`    | string  | `DESC`             | Sort direction: `ASC` or `DESC`. |

**Response item:**

```json
{
  "id": 10,
  "publication_date": "2024-01-10",
  "day_of_week": "Wednesday",
  "editor_name": "Will Shortz",
  "constructors": [
    { "id": 1, "full_name": "Constructor Name" }
  ],
  "url": "https://example.com/kealoa/puzzle/2024-01-10/"
}
```

---

### Get Puzzle

```
GET /puzzles/{id}
```

Returns a single puzzle with full details including clues grouped by round, player results, and links.

**Parameters:**

| Parameter | Type    | Required | Description   |
|-----------|---------|----------|---------------|
| `id`      | integer | Yes      | Puzzle ID.    |

**Response:**

```json
{
  "id": 10,
  "publication_date": "2024-01-10",
  "day_of_week": "Wednesday",
  "editor_name": "Will Shortz",
  "constructors": [
    {
      "id": 1,
      "full_name": "Constructor Name",
      "url": "https://example.com/kealoa/constructor/Constructor_Name/"
    }
  ],
  "rounds": [
    {
      "round_id": 5,
      "round_date": "2024-03-15",
      "round_number": 1,
      "episode_number": 100,
      "round_url": "https://example.com/kealoa/round/5/",
      "solution_words": ["WORD1", "WORD2"],
      "clues": [
        {
          "id": 42,
          "clue_number": 1,
          "puzzle_clue_number": 15,
          "puzzle_clue_direction": "Across",
          "clue_text": "Clue text here",
          "correct_answer": "ANSWER",
          "guesses": [
            {
              "guesser_name": "Player Name",
              "guessed_word": "ANSWER",
              "is_correct": true
            }
          ]
        }
      ]
    }
  ],
  "player_results": [
    {
      "person_id": 1,
      "full_name": "Player Name",
      "total_guesses": 5,
      "correct_guesses": 4,
      "accuracy": 80.0,
      "url": "https://example.com/kealoa/person/Player_Name/"
    }
  ],
  "xwordinfo_url": "https://www.xwordinfo.com/Crossword?date=2024-01-10",
  "url": "https://example.com/kealoa/puzzle/2024-01-10/"
}
```

**Errors:**

| Status | Description       |
|--------|-------------------|
| 404    | Puzzle not found. |

---

## Clues

### Get Clue

```
GET /clues/{id}
```

Returns a single clue with all guesses.

**Parameters:**

| Parameter | Type    | Required | Description   |
|-----------|---------|----------|---------------|
| `id`      | integer | Yes      | Clue ID.      |

**Response:**

```json
{
  "id": 10,
  "round_id": 1,
  "clue_number": 3,
  "puzzle_id": 5,
  "puzzle_date": "2024-01-10",
  "constructors": "Constructor A & Constructor B",
  "editor": "Will Shortz",
  "puzzle_clue_number": 42,
  "puzzle_clue_direction": "Across",
  "clue_text": "A hint for the answer",
  "correct_answer": "ANSWER",
  "guesses": [
    {
      "guesser_name": "Jane Smith",
      "guessed_word": "ANSWER",
      "is_correct": true
    }
  ]
}
```

**Errors:**

| Status | Description     |
|--------|-----------------|
| 404    | Clue not found. |

---

## Search

```
GET /search
```

Full-text search across all entities (players, constructors, editors, rounds).

**Parameters:**

| Parameter | Type   | Required | Description                                  |
|-----------|--------|----------|----------------------------------------------|
| `q`       | string | Yes      | Search term (minimum 2 characters).          |

**Response:**

```json
{
  "query": "smith",
  "count": 3,
  "results": [ ... ]
}
```

---

## Leaderboards

### Leaderboard: Scores

```
GET /leaderboard/scores
```

Returns highest round scores across all players. Not paginated.

---

### Leaderboard: Streaks

```
GET /leaderboard/streaks
```

Returns longest correct-answer streaks across all players. Not paginated.
