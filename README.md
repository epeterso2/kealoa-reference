# KEALOA Reference

A WordPress plugin for managing and displaying KEALOA quiz game data from the [Fill Me In](https://bemoresmarter.libsyn.com) podcast.

**Version:** 2.0.73 &bull; **DB Version:** 2.1.0 &bull; **License:** [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)

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
13. [Frontend Features](#frontend-features)
14. [CSS Palette](#css-palette)
15. [File Structure](#file-structure)
16. [License](#license)

---

## About KEALOA

KEALOA is a word quiz game played on the Fill Me In podcast. For each round, a clue giver selects a set of words from NYT crossword puzzles that share similar lengths, spellings, and/or definitions. Players guess which word fits the given crossword clue.

This plugin tracks every round played, every clue given, every guess made, and the puzzle metadata behind each clue. It also records each person's role — player, puzzle constructor, puzzle editor, or host — in a unified data model.

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
| Dashboard   | Summary statistics (rounds, persons, puzzles), lists available shortcodes and blocks |
| Rounds      | Browse, create, edit, and delete game rounds with full clue and guess management |
| Persons     | Manage persons — players, constructors, editors, and hosts in a unified list with photo support |
| Puzzles     | Browse, create, edit, and delete crossword puzzles with editor and constructor assignments |
| Import      | Import data from CSV files or a ZIP archive; auto-populate editors from puzzle dates |
| Export      | Export data as individual CSV files or a single ZIP archive |
| Data Check  | 16 data consistency checks with repair tools for orphaned records |
| Settings    | Plugin configuration (debug mode toggle) |

### Admin Bar Integration

On the frontend, a KEALOA dropdown in the WordPress admin toolbar provides quick links to all admin pages. When viewing a KEALOA virtual page (person, round, or puzzle), the default "Edit" link is overridden to point to the appropriate admin edit form.

### Data Check (16 consistency checks)

| Category | Checks |
|---|---|
| Orphaned records | Unreferenced puzzles, persons, puzzle-constructor links, clues, guesses, round-guesser links, round solutions |
| Missing references | Clues pointing to missing rounds or puzzles; guesses pointing to missing clues or persons; rounds with missing clue givers; puzzles with missing editors |
| Incomplete data | Rounds with no clues, no solution words, or no guessers |

Repairable checks offer a "Delete Selected" button. Information-only checks display the data without repair options.

---

## Shortcodes

All shortcode output is cached via WordPress transients (24-hour TTL) with a versioned cache key scheme that is invalidated after any data mutation.

| Shortcode | Attributes | Description |
|---|---|---|
| `[kealoa_rounds_table]` | `limit`, `order` | Tabbed view: rounds table (sortable/filterable) and stats (overview grid, yearly stats, clue-number frequency matrices) |
| `[kealoa_round]` | `id` (required) | Round detail: meta, episode link, solution words, host, players with scores, person photos, clue table with guesses, social sharing bar |
| `[kealoa_rounds_stats]` | — | Statistics grid: total players, rounds, puzzles, constructors, clues, guesses, correct answers, accuracy |
| `[kealoa_person]` | `id` (required) | Person profile with tabbed interface: role-specific statistics (player, constructor, editor, host), round history, performance charts, person images |
| `[kealoa_persons_table]` | — | Table of all players with rounds played, clues guessed, and accuracy |
| `[kealoa_constructors_table]` | — | Table of all constructors with puzzle count and statistics |
| `[kealoa_editors_table]` | — | Table of all editors with puzzle count and statistics |
| `[kealoa_clue_givers_table]` | — | Table of all clue givers with rounds, clues, guessers, and accuracy |
| `[kealoa_hosts_table]` | — | Table of all hosts with rounds, clues, players, and accuracy |
| `[kealoa_puzzles_table]` | — | Table of all puzzles with dates, editors, constructors, and round details |
| `[kealoa_puzzle]` | `date` (required, `YYYY-MM-DD`) | Puzzle detail: person images, clue details, and round information |
| `[kealoa_version]` | — | Plugin and database version numbers |

---

## Gutenberg Blocks

The plugin registers 15 blocks under the `kealoa` namespace. Each block renders its content server-side via a PHP `render.php` file.

| Block slug | Title | Icon | Description |
|---|---|---|---|
| `clue-givers-table` | KEALOA Clue Givers Table | `microphone` | Table of all clue givers with rounds, clues, guessers, and accuracy |
| `constructor-view` | KEALOA Constructor View | `hammer` | **Deprecated** — use Person View instead |
| `constructors-table` | KEALOA Constructors Table | `hammer` | Table of all constructors with statistics |
| `editor-view` | KEALOA Editor View | `edit` | **Deprecated** — use Person View instead |
| `editors-table` | KEALOA Editors Table | `edit` | Table of all editors with statistics |
| `hosts-table` | KEALOA Hosts Table | `microphone` | Table of all hosts with rounds, clues, players, and accuracy |
| `person-view` | KEALOA Person View | `admin-users` | Person profile with statistics, round history, and performance metrics |
| `persons-table` | KEALOA Players Table | `groups` | Table of all players with rounds played, clues guessed, and accuracy |
| `play-game` | KEALOA Play Game | `games` | Interactive in-browser KEALOA game |
| `puzzle-view` | KEALOA Puzzle View | `grid-view` | Single puzzle with person images, clue details, and round information |
| `puzzles-table` | KEALOA Puzzles Table | `grid-view` | Table of all puzzles with puzzle date, constructors, editor, and round details |
| `round-view` | KEALOA Round View | `media-document` | Single round with all clues, guesses, and results |
| `rounds-stats` | KEALOA Rounds Stats | `chart-bar` | Round statistics: total rounds, clues, guesses, correct answers, and accuracy |
| `rounds-table` | KEALOA Rounds Table | `editor-table` | Paginated table of all rounds with dates, episodes, solutions, and results |
| `version-info` | KEALOA Version Info | `info-outline` | Plugin and database version numbers |

### Block Attributes

| Block | Attribute | Type | Default |
|---|---|---|---|
| `constructor-view` | `constructorId` | number | `0` |
| `editor-view` | `editorName` | string | `""` |
| `person-view` | `personId` | number | `0` |
| `puzzle-view` | `puzzleDate` | string | `""` |
| `round-view` | `roundId` | number | `0` |
| `rounds-table` | `limit` | number | `50` |
| `rounds-table` | `order` | string | `"DESC"` |

---

## Custom URL Routes

The plugin registers five custom rewrite rules. Name-based routes use underscores in place of spaces.

| URL pattern | Description |
|---|---|
| `/kealoa/round/{id}/` | Round detail page |
| `/kealoa/person/{name}/` | Person profile page |
| `/kealoa/puzzle/{YYYY-MM-DD}/` | Puzzle detail page |
| `/kealoa/constructor/{name}/` | **Redirects (301)** to `/kealoa/person/{name}/` |
| `/kealoa/editor/{name}/` | **Redirects (301)** to `/kealoa/person/{name}/` |

The constructor and editor URL forms are kept for backward compatibility. All canonical person URLs use the `/kealoa/person/` prefix.

Each virtual page sets up a fake `WP_Post` object so WordPress (and caching plugins like WP Super Cache) treats the response as a normal 200 page. GeneratePress is forced to "no-sidebar" layout on KEALOA pages.

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
| GET | `/persons` | Paginated list of persons (filterable by role) |
| GET | `/persons/{id}` | Full profile for a single person |
| GET | `/persons/{id}/rounds` | All rounds a person participated in |
| GET | `/persons/{id}/puzzles` | All puzzles from rounds the person played |
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
| GET | `/search?q=...` | Full-text search across persons, rounds, and puzzles |
| GET | `/leaderboard/scores` | All persons ranked by highest round score |
| GET | `/leaderboard/streaks` | All persons ranked by longest correct-guess streak |

### Pagination

All list endpoints (`/rounds`, `/persons`, `/puzzles`) support pagination via query parameters.

| Parameter | Type | Default | Min | Max | Description |
|---|---|---|---|---|---|
| `page` | integer | `1` | `1` | — | Page number |
| `per_page` | integer | `50` | `1` | `500` | Items per page |

**Response envelope:**

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
| `persons` | Unified table for all persons — players, constructors, editors, and hosts |
| `puzzles` | NYT crossword puzzle metadata, with `editor_id` foreign key to `persons` |
| `puzzle_constructors` | Many-to-many join between `puzzles` and `persons` (constructor role) |
| `rounds` | Individual KEALOA game rounds with episode metadata |
| `round_solutions` | The solution words for a round (ordered) |
| `round_guessers` | The persons who played as guessers in a round |
| `clues` | Individual clues used within a round, each linked to a specific crossword puzzle clue |
| `guesses` | All guesses made by persons for each clue |

### Key Relationships

- A **round** has many **clues**, many **solution words** (`round_solutions`), many **guessers** (`round_guessers`), and one **clue giver** (`clue_giver_id` → `persons`).
- A **clue** belongs to both a **round** and a **puzzle**, referencing the specific crossword clue number and direction (Across/Down).
- A **clue** has many **guesses**, each by a specific guesser.
- A **puzzle** has one **editor** (via `editor_id` → `persons`) and zero or more **constructors** (via `puzzle_constructors`).
- A **person** may hold any combination of the `player`, `constructor`, `editor`, and `clue_giver` (host) roles.

### Table Schemas

#### `persons`

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint(20) UNSIGNED AUTO_INCREMENT` | PK |
| `full_name` | `varchar(255) NOT NULL` | Indexed |
| `nicknames` | `varchar(500) DEFAULT NULL` | Comma-separated |
| `home_page_url` | `varchar(500) DEFAULT NULL` | |
| `image_url` | `varchar(500) DEFAULT NULL` | |
| `media_id` | `bigint(20) UNSIGNED DEFAULT NULL` | WP Media Library attachment ID |
| `hide_xwordinfo` | `tinyint(1) NOT NULL DEFAULT 0` | |
| `xwordinfo_profile_name` | `varchar(255) DEFAULT NULL` | Indexed |
| `xwordinfo_image_url` | `varchar(500) DEFAULT NULL` | |
| `created_at` | `datetime` | |
| `updated_at` | `datetime` | |

#### `puzzles`

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint(20) UNSIGNED AUTO_INCREMENT` | PK |
| `publication_date` | `date NOT NULL` | Unique index |
| `editor_id` | `bigint(20) UNSIGNED DEFAULT NULL` | FK → `persons`, indexed |
| `created_at` | `datetime` | |
| `updated_at` | `datetime` | |

#### `puzzle_constructors`

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint(20) UNSIGNED AUTO_INCREMENT` | PK |
| `puzzle_id` | `bigint(20) UNSIGNED NOT NULL` | Unique with `person_id` |
| `person_id` | `bigint(20) UNSIGNED NOT NULL` | Indexed |
| `constructor_order` | `tinyint(3) UNSIGNED DEFAULT 1` | |
| `created_at` | `datetime` | |

#### `rounds`

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint(20) UNSIGNED AUTO_INCREMENT` | PK |
| `round_date` | `date NOT NULL` | Unique with `round_number` |
| `round_number` | `tinyint(3) UNSIGNED NOT NULL DEFAULT 1` | For multi-round episodes |
| `episode_number` | `int(10) UNSIGNED NOT NULL` | Indexed |
| `episode_id` | `int(10) UNSIGNED DEFAULT NULL` | Indexed |
| `episode_url` | `varchar(500) DEFAULT NULL` | |
| `episode_start_seconds` | `int(10) UNSIGNED DEFAULT 0` | |
| `clue_giver_id` | `bigint(20) UNSIGNED NOT NULL` | FK → `persons`, indexed |
| `description` | `text DEFAULT NULL` | |
| `description2` | `text DEFAULT NULL` | Spoiler/post-game description |
| `created_at` | `datetime` | |
| `updated_at` | `datetime` | |

#### `round_solutions`

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint(20) UNSIGNED AUTO_INCREMENT` | PK |
| `round_id` | `bigint(20) UNSIGNED NOT NULL` | Indexed |
| `word` | `varchar(100) NOT NULL` | Indexed |
| `word_order` | `tinyint(3) UNSIGNED DEFAULT 1` | |
| `created_at` | `datetime` | |

#### `round_guessers`

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint(20) UNSIGNED AUTO_INCREMENT` | PK |
| `round_id` | `bigint(20) UNSIGNED NOT NULL` | Unique with `person_id` |
| `person_id` | `bigint(20) UNSIGNED NOT NULL` | Indexed |
| `created_at` | `datetime` | |

#### `clues`

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint(20) UNSIGNED AUTO_INCREMENT` | PK |
| `round_id` | `bigint(20) UNSIGNED NOT NULL` | Indexed |
| `clue_number` | `tinyint(3) UNSIGNED NOT NULL` | Indexed |
| `puzzle_id` | `bigint(20) UNSIGNED DEFAULT NULL` | FK → `puzzles`, indexed |
| `puzzle_clue_number` | `smallint(5) UNSIGNED DEFAULT NULL` | |
| `puzzle_clue_direction` | `enum('A','D') DEFAULT NULL` | Across or Down |
| `clue_text` | `text NOT NULL` | |
| `correct_answer` | `varchar(100) NOT NULL` | |
| `created_at` | `datetime` | |
| `updated_at` | `datetime` | |

#### `guesses`

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint(20) UNSIGNED AUTO_INCREMENT` | PK |
| `clue_id` | `bigint(20) UNSIGNED NOT NULL` | Unique with `guesser_person_id` |
| `guesser_person_id` | `bigint(20) UNSIGNED NOT NULL` | Indexed |
| `guessed_word` | `varchar(100) NOT NULL` | |
| `is_correct` | `tinyint(1) NOT NULL DEFAULT 0` | Indexed |
| `created_at` | `datetime` | |

---

## Data Import and Export

### Export

The **Export** admin page generates individual CSV files or a single ZIP archive (`kealoa-export-YYYY-MM-DD.zip`). All CSVs include a UTF-8 BOM for Excel compatibility.

| CSV type | Columns |
|---|---|
| Persons | `full_name`, `nicknames`, `home_page_url`, `image_url`, `hide_xwordinfo`, `xwordinfo_profile_name`, `xwordinfo_image_url`, `media_id` |
| Puzzles | `publication_date`, `editor_name`, `constructors` |
| Rounds | `round_date`, `round_number`, `episode_number`, `episode_id`, `episode_url`, `episode_start_seconds`, `clue_giver`, `guessers`, `solution_words`, `description`, `description2` |
| Clues | `round_date`, `round_number`, `clue_number`, `puzzle_date`, `constructors`, `puzzle_clue_number`, `puzzle_clue_direction`, `clue_text`, `correct_answer` |
| Guesses | `round_date`, `round_number`, `clue_number`, `guesser`, `guessed_word`, `is_correct` |

### Import

The **Import** admin page accepts individual CSV files or a ZIP archive. Seven import types are supported:

| Import type | Required columns | Notes |
|---|---|---|
| Constructors (legacy) | `full_name` | Imports as persons with XWordInfo fields |
| Persons | `full_name` | All person profile fields |
| Puzzles | `publication_date` | Auto-creates constructors; determines editors from historical date ranges |
| Rounds | `round_date`, `episode_number`, `clue_giver` | Auto-creates persons; handles guessers and solution words |
| Clues | `round_date`, `clue_text`, `correct_answer` | Rounds must exist; puzzles created if needed |
| Guesses | `round_date`, `clue_number`, `guesser`, `guessed_word` | `is_correct` is auto-calculated |
| Round Data | `round_date`, `round_number`, `clue_number_in_round`, `correct_answer`, `player_name`, `guessed_answer` | All-in-one format (one row per guess); rounds must exist |

**Import options:**
- **Overwrite** — existing records are updated.
- **Skip** — existing records are left unchanged; only new records are inserted.

**Date normalization:** The importer accepts `YYYY-MM-DD`, `M/D/YYYY`, `MM/DD/YYYY`, and `M-D-YYYY` formats.

**ZIP import** processes CSVs in dependency order: persons → puzzles → rounds → clues → guesses.

### CSV Templates

Downloadable templates are available on the Import admin page:

| Template | Headers |
|---|---|
| `constructors.csv` | `full_name`, `xwordinfo_profile_name`, `xwordinfo_image_url`, `media_id` |
| `persons.csv` | `full_name`, `nicknames`, `home_page_url`, `image_url`, `hide_xwordinfo`, `xwordinfo_profile_name`, `xwordinfo_image_url`, `media_id` |
| `puzzles.csv` | `publication_date`, `editor_name`, `constructors` |
| `rounds.csv` | `round_date`, `round_number`, `episode_number`, `episode_id`, `episode_url`, `episode_start_seconds`, `clue_giver`, `guessers`, `solution_words`, `description`, `description2` |
| `clues.csv` | `round_date`, `round_number`, `clue_number`, `puzzle_date`, `constructors`, `puzzle_clue_number`, `puzzle_clue_direction`, `clue_text`, `correct_answer` |
| `guesses.csv` | `round_date`, `round_number`, `clue_number`, `guesser`, `guessed_word`, `is_correct` |
| `round_data.csv` | `round_date`, `round_number`, `clue_number_in_round`, `puzzle_date`, `constructor_name`, `clue_number`, `clue_direction`, `clue_text`, `correct_answer`, `player_name`, `guessed_answer` |

---

## Search Integration

The plugin extends WordPress core search to include KEALOA entities. When a visitor performs a search:

- Matching persons, rounds, and puzzles appear in a table at the top of the search results.
- If exactly one KEALOA entity matches, the user is automatically redirected to that page.
- A placeholder post is injected when no WordPress posts match but KEALOA results exist, so the loop runs and can display the results.

---

## Sitemap Integration

KEALOA URLs are automatically included in site sitemaps.

### WordPress Core Sitemaps

A custom `WP_Sitemaps_Provider` named `kealoa` registers three object subtypes:

| Subtype | URL pattern |
|---|---|
| `rounds` | `/kealoa/round/{id}/` |
| `persons` | `/kealoa/person/{name}/` |
| `puzzles` | `/kealoa/puzzle/{date}/` |

### Yoast SEO

When Yoast SEO is active, a `<sitemap>` entry for `/kealoa-sitemap.xml` is added to the Yoast sitemap index, with a custom `<urlset>` XML containing all KEALOA URLs.

---

## Frontend Features

### Table Sorting

All `.kealoa-table` columns with a `data-sort` attribute support click-to-sort with ascending/descending toggle. Sort types: `date`, `number`, `text` (locale-aware), `clue` (e.g. "42D"), and `weekday`. Supports `data-default-sort` and `data-sort-value` overrides. Empty columns are automatically hidden.

### Table Filtering

Rich filtering system with: text search (multi-column), exact-match select, minimum threshold, accuracy range (min/max), top/bottom N, above/below average, date range, year filter, and perfect-score filter. Visible count display and reset button.

### Tabbed Navigation

Nested tab UI with primary and secondary tabs. URL hash activation via `#kealoa-tab=primary&kealoa-subtab=secondary` for deep-linkable tab state.

### Interactive Game

The Play Game block provides a fully client-side KEALOA quiz:

- Random round selection (avoids replays within a session) or forced round via `?round=ID`
- Two play modes: "In Show Order" or "In Random Order" (`?order=random`)
- Clue cards showing puzzle date, day of week, constructors, editor, and clue text
- Answer buttons (one per solution word) with immediate correct/incorrect feedback
- Shows how the real podcast players answered each clue
- Final scoreboard ranking the user against actual players
- Clue-by-clue review table
- Share results via `navigator.share()` with emoji grid and clipboard fallback
- Spoiler descriptions revealed only after game completion

### Social Sharing

Round pages include a sharing bar with Facebook, X (Twitter), Email, and Copy Link buttons.

### Round Picker

Clickable round-picker links open a modal listing matching rounds with date, solution words, and score.

### Charts

Person profile pages use Chart.js 4.x for interactive performance visualizations.

---

## CSS Palette

The plugin defines a Hawaiian-themed color palette via CSS custom properties in `kealoa-palette.css`:

| Group | Variable | Value |
|---|---|---|
| **Fire & Volcanic** | `--volcanic-ember` | `#C84B0A` |
| | `--lava-orange` | `#E8720C` |
| | `--torch-flame` | `#F5A623` |
| | `--gold-tiki` | `#D4960A` |
| **Earth & Wood** | `--driftwood-dark` | `#5C3A1E` |
| | `--bamboo-brown` | `#8B5E2E` |
| | `--sandy-shore` | `#C9A66B` |
| | `--pale-sand` | `#EDD9A3` |
| **Ocean & Sky** | `--deep-lagoon` | `#0A6E8A` |
| | `--tropical-teal` | `#1A9BB5` |
| | `--sky-blue` | `#5BAED6` |
| | `--seafoam` | `#A8D8E8` |
| **Jungle Greens** | `--jungle-deep` | `#1B4A1E` |
| | `--palm-green` | `#2E7D32` |
| | `--hibiscus-red` | `#C0392B` |
| **Ash & Neutrals** | `--ash-dark` | `#2C2C2C` |
| | `--pumice` | `#6B6B6B` |
| | `--volcanic-ash` | `#B0AFA8` |
| | `--cloud-white` | `#F7F3EC` |

---

## File Structure

```
kealoa-reference/
├── kealoa-reference.php              Main plugin file; constants, bootstrap, REST game-round route
├── uninstall.php                     Cleans up all plugin data on uninstall
├── LICENSE                           CC BY-NC-SA 4.0 license text
├── admin/
│   └── class-kealoa-admin.php        Admin menus, pages, form handlers, and AJAX handlers
├── assets/
│   ├── css/
│   │   ├── blocks-editor.css         Styles for block editor view
│   │   ├── kealoa-admin.css          Admin page styles
│   │   ├── kealoa-frontend.css       Frontend page styles
│   │   ├── kealoa-game.css           Play-game interactive styles
│   │   └── kealoa-palette.css        Color palette CSS custom properties
│   └── js/
│       ├── blocks-editor.js          Gutenberg block editor registrations
│       ├── kealoa-admin.js           Admin page JavaScript
│       ├── kealoa-frontend.js        Frontend table sorting, filtering, tabs, sharing
│       └── kealoa-game.js            Interactive KEALOA game logic
├── blocks/
│   ├── clue-givers-table/            block.json + render.php
│   ├── constructor-view/             block.json + render.php (deprecated)
│   ├── constructors-table/           block.json + render.php
│   ├── editor-view/                  block.json + render.php (deprecated)
│   ├── editors-table/                block.json + render.php
│   ├── hosts-table/                  block.json + render.php
│   ├── person-view/                  block.json + render.php
│   ├── persons-table/                block.json + render.php
│   ├── play-game/                    block.json + render.php
│   ├── puzzle-view/                  block.json + render.php
│   ├── puzzles-table/                block.json + render.php
│   ├── round-view/                   block.json + render.php
│   ├── rounds-stats/                 block.json + render.php
│   ├── rounds-table/                 block.json + render.php
│   └── version-info/                 block.json + render.php
├── includes/
│   ├── class-kealoa-activator.php    Plugin activation (table creation, migrations, rewrite rules)
│   ├── class-kealoa-blocks.php       Gutenberg block registration
│   ├── class-kealoa-db.php           Database query methods (CRUD, stats, search)
│   ├── class-kealoa-deactivator.php  Plugin deactivation (flush rewrite rules)
│   ├── class-kealoa-export.php       CSV/ZIP export logic
│   ├── class-kealoa-formatter.php    Data formatting helpers (dates, links, images, sharing)
│   ├── class-kealoa-import.php       CSV/ZIP import logic with date normalization
│   ├── class-kealoa-rest-api.php     REST API route registrations and callbacks
│   ├── class-kealoa-shortcodes.php   Shortcode registrations and render callbacks
│   ├── class-kealoa-sitemap-provider.php  WordPress core sitemap provider
│   └── class-kealoa-sitemap.php      Yoast sitemap integration
├── languages/
│   └── README.md                     Internationalization placeholder
└── templates/
    └── csv/                          Downloadable CSV import templates
        ├── clues.csv
        ├── constructors.csv
        ├── guesses.csv
        ├── persons.csv
        ├── puzzles.csv
        ├── round_data.csv
        └── rounds.csv
```

---

## License

Copyright &copy; 2026 Eric Peterson &lt;eric@puzzlehead.org&gt;

Licensed under the [Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International License](https://creativecommons.org/licenses/by-nc-sa/4.0/).
