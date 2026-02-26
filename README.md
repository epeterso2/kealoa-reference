# KEALOA Reference

A WordPress plugin for managing and displaying KEALOA quiz game data from the [Fill Me In](https://bemoresmarter.libsyn.com) podcast.

**Version:** 2.0.11 &bull; **DB Version:** 2.0.0 &bull; **License:** [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)

---

## Table of Contents

1. [About KEALOA](#about-kealoa)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Admin Interface](#admin-interface)
5. [Shortcodes](#shortcodes)
6. [Gutenberg Blocks](#gutenberg-blocks)
7. [Custom URL Routes](#custom-url-routes)
8. [REST API](#rest-api)
9. [Database Model](#database-model)
10. [Data Import and Export](#data-import-and-export)
11. [Search Integration](#search-integration)
12. [Sitemap Integration](#sitemap-integration)
13. [File Structure](#file-structure)
14. [License](#license)

---

## About KEALOA

KEALOA is a word quiz game played on the Fill Me In podcast. For each round, a clue giver selects a set of words from NYT crossword puzzles that share similar lengths, spellings, and/or definitions. Players guess which word fits the given crossword clue.

This plugin tracks every round played, every clue given, every guess made, and the puzzle metadata behind each clue. It also records each person's role — player, puzzle constructor, or puzzle editor — in a unified data model.

---

## Requirements

| Requirement  | Minimum version |
|---|---|
| WordPress    | 6.9             |
| PHP          | 8.4             |

---

## Installation

1. Upload the `kealoa-reference/` directory to `/wp-content/plugins/`.
2. Activate **KEALOA Reference** from the WordPress Plugins admin page.
3. Open the **KEALOA** menu item in the WordPress admin sidebar.

On first activation the plugin creates all database tables and registers the custom rewrite rules. After activating, go to **Settings → Permalinks** and save to flush rewrite rules so the custom URLs work correctly.

---

## Admin Interface

The plugin adds a **KEALOA** top-level menu to the WordPress admin sidebar with the following pages:

| Page        | Description |
|---|---|
| Dashboard   | Summary statistics and quick links |
| Rounds      | Browse, create, edit, and delete game rounds |
| Persons     | Manage persons (players, constructors, and editors in a unified list) |
| Constructors | Filtered view of persons with the constructor role |
| Puzzles     | Browse crossword puzzles referenced by rounds |
| Import      | Import data from CSV files or a ZIP archive |
| Export      | Export data as CSV files or a ZIP archive |
| Data Check  | Identify missing or inconsistent data |
| Settings    | Plugin configuration options |

---

## Shortcodes

| Shortcode | Attributes | Description |
|---|---|---|
| `[kealoa_rounds_table]` | — | Paginated table of all rounds |
| `[kealoa_round]` | `id` (required) — round ID | Full detail view for a single round |
| `[kealoa_rounds_stats]` | — | Aggregate round statistics |
| `[kealoa_persons_table]` | — | Table of all persons |
| `[kealoa_person]` | `id` (required) — person ID | Full profile for a single person |
| `[kealoa_constructors_table]` | — | Table of persons with the constructor role |
| `[kealoa_constructor]` | `id` (required) — person ID | Constructor profile for a single person |
| `[kealoa_editors_table]` | — | Table of persons with the editor role |
| `[kealoa_editor]` | `name` (required) — full name | Editor profile looked up by name |
| `[kealoa_puzzles_table]` | — | Table of all puzzles |
| `[kealoa_puzzle]` | `date` (required) — `YYYY-MM-DD` | Detail view for a single puzzle |
| `[kealoa_version]` | — | Outputs the current plugin version string |

---

## Gutenberg Blocks

The plugin registers 13 blocks under the `kealoa` namespace. Each block renders its content server-side via a PHP `render.php` file.

| Block slug | Block name | Description |
|---|---|---|
| `rounds-table` | KEALOA Rounds Table | Paginated table of all rounds |
| `round-view` | KEALOA Round View | Full detail view for a single round (uses current URL context) |
| `rounds-stats` | KEALOA Rounds Stats | Aggregate round statistics |
| `persons-table` | KEALOA Persons Table | Table of all persons |
| `person-view` | KEALOA Person View | Full profile for a single person (uses current URL context) |
| `constructors-table` | KEALOA Constructors Table | Table of persons with the constructor role |
| `constructor-view` | KEALOA Constructor View | Constructor profile (uses current URL context) |
| `editors-table` | KEALOA Editors Table | Table of persons with the editor role |
| `editor-view` | KEALOA Editor View | Editor profile (uses current URL context) |
| `puzzles-table` | KEALOA Puzzles Table | Table of all puzzles |
| `puzzle-view` | KEALOA Puzzle View | Detail view for a single puzzle (uses current URL context) |
| `play-game` | KEALOA Play Game | Interactive in-browser playback of a round |
| `version-info` | KEALOA Version Info | Outputs the plugin version |

---

## Custom URL Routes

The plugin registers five custom rewrite rules. Name-based routes use underscores in place of spaces.

| URL pattern | Description |
|---|---|
| `/kealoa/round/{id}/` | Round detail page |
| `/kealoa/person/{name}/` | Person profile page |
| `/kealoa/constructor/{name}/` | **Redirects (301)** to `/kealoa/person/{name}/` |
| `/kealoa/editor/{name}/` | **Redirects (301)** to `/kealoa/person/{name}/` |
| `/kealoa/puzzle/{YYYY-MM-DD}/` | Puzzle detail page |

The constructor and editor URL forms are kept for backward compatibility. All canonical person URLs use the `/kealoa/person/` prefix.

---

## REST API

The plugin exposes a read-only REST API under the namespace `kealoa/v1`.

**Base URL:** `/wp-json/kealoa/v1`

All endpoints are publicly accessible (no authentication required). See [REST-API.md](REST-API.md) for full documentation including all parameters, response schemas, and error codes.

### Endpoint Index

| Method | Path | Description |
|---|---|---|
| GET | `/game-round/{id}` | Round data for the interactive play-game block |
| GET | `/rounds` | Paginated list of rounds |
| GET | `/rounds/stats` | Aggregate round statistics |
| GET | `/rounds/{id}` | Full detail for a single round |
| GET | `/persons` | Paginated list of persons |
| GET | `/persons/{id}` | Full profile for a single person |
| GET | `/persons/{id}/rounds` | All rounds a person participated in |
| GET | `/persons/{id}/puzzles` | All puzzles attributed to a person |
| GET | `/persons/{id}/stats/by-year` | Person results grouped by year |
| GET | `/persons/{id}/stats/by-day` | Person results grouped by day of week |
| GET | `/persons/{id}/stats/by-constructor` | Person results grouped by puzzle constructor |
| GET | `/persons/{id}/stats/by-editor` | Person results grouped by puzzle editor |
| GET | `/persons/{id}/stats/by-direction` | Person results grouped by clue direction (Across/Down) |
| GET | `/persons/{id}/stats/by-length` | Person results grouped by answer length |
| GET | `/persons/{id}/stats/by-decade` | Person results grouped by puzzle decade |
| GET | `/persons/{id}/stats/by-clue-number` | Person results grouped by clue number within a round |
| GET | `/persons/{id}/stats/streaks` | Person best correct-guess streaks by year |
| GET | `/puzzles` | Paginated list of puzzles |
| GET | `/puzzles/{id}` | Full detail for a single puzzle |
| GET | `/clues/{id}` | Full detail for a single clue |
| GET | `/search` | Full-text search across persons, rounds, and puzzles |
| GET | `/leaderboard/scores` | All persons ranked by highest round score |
| GET | `/leaderboard/streaks` | All persons ranked by longest correct-guess streak |

### Pagination

All list endpoints (`/rounds`, `/persons`, `/puzzles`) support pagination via query parameters. The response envelope and headers are the same for all paginated endpoints.

**Pagination query parameters:**

| Parameter | Type | Default | Min | Max | Description |
|---|---|---|---|---|---|
| `page` | integer | `1` | `1` | — | Page number |
| `per_page` | integer | `50` | `1` | `500` | Items per page |

**Paginated response envelope:**

```json
{
  "total": 120,
  "total_pages": 3,
  "page": 1,
  "per_page": 50,
  "items": [ ... ]
}
```

**Response headers:**

| Header | Description |
|---|---|
| `X-WP-Total` | Total number of matching items |
| `X-WP-TotalPages` | Total number of pages |

---

## Database Model

The plugin creates eight custom tables. All table names are prefixed with the WordPress table prefix followed by `kealoa_` (e.g., `wp_kealoa_persons`).

| Table | Description |
|---|---|
| `persons` | Unified table for all persons — players, puzzle constructors, and editors |
| `puzzles` | NYT crossword puzzle metadata, with `editor_id` foreign key to `persons` |
| `puzzle_constructors` | Many-to-many join between `puzzles` and `persons` (constructor role) |
| `rounds` | Individual KEALOA game rounds |
| `round_solutions` | The solution words for a round |
| `round_guessers` | The persons who played as guessers in a round |
| `clues` | Individual clues used within a round, each linked to a puzzle clue |
| `guesses` | All guesses made by persons for each clue |

### Key relationships

- A **round** has many **clues**, many **solution words** (`round_solutions`), and many **guessers** (`round_guessers`).
- A **clue** belongs to both a **round** and a **puzzle** entry, with a reference to the specific crossword clue (number + direction).
- A **clue** has many **guesses**.
- A **puzzle** has one **editor** (via `editor_id` → `persons`) and zero or more **constructors** (via `puzzle_constructors`).
- A **person** may hold any combination of the `player`, `constructor`, and `editor` roles.

### Version history

- **v1** — Separate `constructors` and `editors` tables distinct from the `players` table.
- **v2** — Unified `persons` table; all roles stored as flags on the same record. Legacy `/kealoa/constructor/` and `/kealoa/editor/` URLs now redirect (301) to `/kealoa/person/`.

---

## Data Import and Export

### Export

The **Export** admin page generates individual CSV files for each entity type, or a ZIP archive containing all CSV files at once. Use the ZIP option for full backups.

### Import

The **Import** admin page accepts individual CSV files or a ZIP archive. Supported CSV files:

| File | Entity |
|---|---|
| `persons.csv` | Persons |
| `puzzles.csv` | Puzzles |
| `constructors.csv` | Puzzle constructor assignments |
| `rounds.csv` | Rounds |
| `clues.csv` | Clues |
| `guesses.csv` | Guesses |

**Import options:**

- **Overwrite** — existing records with matching IDs are updated.
- **Skip** — existing records are left unchanged; only new records are inserted.

**Date normalization:** The importer accepts dates in `YYYY-MM-DD`, `M/D/YYYY`, and `MM/DD/YYYY` formats and normalizes them to `YYYY-MM-DD` on ingestion.

---

## Search Integration

The plugin extends WordPress core search to include KEALOA entities. When a visitor performs a search on the site, the results may include matching persons, rounds, and puzzles in addition to standard WordPress posts and pages.

---

## Sitemap Integration

KEALOA URLs are automatically included in site sitemaps.

- **WordPress core sitemaps** — a custom sitemap provider registers `/kealoa/person/`, `/kealoa/round/`, and `/kealoa/puzzle/` URLs.
- **Yoast SEO** — when Yoast is active, KEALOA URLs are injected into the Yoast sitemap index and sitemap content via WordPress action hooks.

---

## File Structure

```
kealoa-reference/
├── kealoa-reference.php          Main plugin file; defines constants, bootstrap, REST game-round route
├── uninstall.php                 Cleans up all plugin data on uninstall
├── admin/
│   └── class-kealoa-admin.php    Admin menus, pages, and AJAX handlers
├── assets/
│   ├── css/
│   │   ├── blocks-editor.css     Styles for block editor view
│   │   ├── kealoa-admin.css      Admin page styles
│   │   ├── kealoa-frontend.css   Frontend page styles
│   │   ├── kealoa-game.css       Play-game interactive styles
│   │   └── kealoa-palette.css    Color palette definitions
│   └── js/
│       ├── blocks-editor.js      Block editor JavaScript
│       ├── kealoa-admin.js       Admin page JavaScript
│       ├── kealoa-frontend.js    Frontend JavaScript
│       └── kealoa-game.js        Play-game interactive JavaScript
├── blocks/
│   ├── constructor-view/         Block definition and server-side render
│   ├── constructors-table/
│   ├── editor-view/
│   ├── editors-table/
│   ├── person-view/
│   ├── persons-table/
│   ├── play-game/
│   ├── puzzle-view/
│   ├── puzzles-table/
│   ├── round-view/
│   ├── rounds-stats/
│   ├── rounds-table/
│   └── version-info/
└── includes/
    ├── class-kealoa-activator.php     Plugin activation (table creation, rewrite rules)
    ├── class-kealoa-blocks.php        Gutenberg block registration
    ├── class-kealoa-db.php            All database query methods
    ├── class-kealoa-deactivator.php   Plugin deactivation (flush rewrite rules)
    ├── class-kealoa-export.php        CSV/ZIP export logic
    ├── class-kealoa-formatter.php     Data formatting helpers
    ├── class-kealoa-import.php        CSV/ZIP import logic
    ├── class-kealoa-rest-api.php      REST API route registrations and callbacks
    ├── class-kealoa-shortcodes.php    Shortcode registrations and render callbacks
    ├── class-kealoa-sitemap-provider.php  WordPress core sitemap provider
    └── class-kealoa-sitemap.php           Yoast sitemap integration
```

---

## License

Copyright &copy; 2026 Eric Peterson &lt;eric@puzzlehead.org&gt;

Licensed under the [Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International License](https://creativecommons.org/licenses/by-nc-sa/4.0/).
