# KEALOA Reference

A WordPress plugin for managing and displaying KEALOA quiz game data from the [Fill Me In](https://bemoresmarter.libsyn.com) podcast.

**Version:** 1.1.93 &bull; **DB Version:** 1.4.0 &bull; **License:** [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)

## Description

KEALOA is a quiz game played on the Fill Me In podcast where a clue giver selects words from New York Times crossword puzzles that have similar lengths, spellings, and definitions. Players then try to identify which word matches each clue.

This plugin provides:

- **Complete data management** for rounds, clues, puzzles, persons, constructors, editors, and guesses
- **Admin interface** for entering and managing all KEALOA data with import/export support
- **Frontend display** via shortcodes and 10 Gutenberg blocks
- **Interactive game** letting visitors play rounds directly on the site
- **Statistics tracking** with multiple breakdowns for individual players
- **Custom URL routing** for person, round, constructor, and editor detail pages
- **Read-only REST API** with 27+ endpoints for all KEALOA data
- **Sitemap integration** with WordPress core sitemaps and Yoast SEO
- **WordPress search integration** with results for players, constructors, editors, and rounds

## Requirements

- WordPress 6.9 or higher
- PHP 8.4 or higher

## Installation

1. Upload the `kealoa-reference` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **KEALOA** in the admin menu to start adding data

## Usage

### Admin Interface

The plugin adds a **KEALOA** menu to the WordPress admin with the following sections:

| Page | Description |
|------|-------------|
| **Dashboard** | Overview with entity counts and shortcode/block reference |
| **Rounds** | Create/edit/delete rounds with clues, guesses, solution words, episode links |
| **Persons** | Manage player profiles (name, home page, image) |
| **Constructors** | Manage constructor profiles (name, XWordInfo profile/image) |
| **Puzzles** | Manage puzzles (date, editor, constructors) |
| **Import** | CSV or ZIP data import with overwrite/skip modes |
| **Export** | CSV or ZIP data export (all 6 entity types) |
| **Data Check** | Data consistency checks and repair tools |
| **Settings** | Plugin settings (debug mode) |

### Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[kealoa_rounds_table]` | Summary stats, yearly breakdown, and full rounds table with per-player scores |
| `[kealoa_round id="X"]` | Single round with episode link, audio player, clue giver, player scores, sortable clues table |
| `[kealoa_persons_table]` | All players with rounds played, clues guessed, accuracy, best score, best streak |
| `[kealoa_person id="X"]` | Tabbed player profile: stats/charts, puzzle breakdowns, constructor breakdowns, round history |
| `[kealoa_constructors_table]` | All constructors with puzzle count, clue count, accuracy |
| `[kealoa_constructor id="X"]` | Constructor profile with stats, XWordInfo link, puzzle table, cross-links |
| `[kealoa_editors_table]` | All editors with clues guessed, correct, accuracy |
| `[kealoa_editor name="X"]` | Editor profile with stats, puzzle table, cross-links |
| `[kealoa_version]` | Plugin and database version numbers |

### Gutenberg Blocks

The plugin provides 10 blocks in the block editor:

| Block | Description |
|-------|-------------|
| **KEALOA Rounds Table** | Displays all rounds in a table format |
| **KEALOA Round View** | Displays a single round with all details |
| **KEALOA Players Table** | Displays all players with stats |
| **KEALOA Person View** | Displays a person's profile and statistics |
| **KEALOA Constructors Table** | Displays all constructors with stats |
| **KEALOA Constructor View** | Displays a constructor's profile and puzzle history |
| **KEALOA Editors Table** | Displays all editors with stats |
| **KEALOA Editor View** | Displays an editor's profile and puzzle history |
| **KEALOA Play Game** | Interactive game that lets visitors play a round |
| **KEALOA Version Info** | Displays plugin and database version numbers |

All blocks use server-side rendering via `render.php` templates.

### Custom URLs

The plugin creates custom URL routes:

| URL Pattern | Description |
|-------------|-------------|
| `/kealoa/round/{id}/` | Round detail page |
| `/kealoa/person/{name}/` | Person detail page (spaces replaced by underscores) |
| `/kealoa/constructor/{name}/` | Constructor detail page |
| `/kealoa/editor/{name}/` | Editor detail page |

### WordPress Search Integration

KEALOA data is injected into WordPress search results. Matching players, constructors, editors, and rounds appear in a table at the top of search results. If a search matches exactly one KEALOA result, the user is redirected directly to that page.

## Features

### Interactive Game

The Play Game block provides an interactive KEALOA game where visitors can:

- Play any round with clues revealed one at a time
- Choose sequential or random clue order
- Share game links that preserve the round and play mode

### Person View

The person view displays a tabbed interface with four sections:

1. **Overall Stats** — Stat cards (rounds, clues, correct, accuracy, best score, best streak), score distribution bar chart, accuracy-by-round line chart, by-year table, by-clue-number table, by-answer-length table, per-round summary stats (min/mean/median/max)
2. **By Puzzle** — Results by clue direction, day of week, puzzle decade, and editor
3. **By Constructor** — Results per constructor
4. **Rounds Played** — Full round history with streak per round

Cross-links to constructor and editor views when the person has matching entries.

### Constructor View

Constructor profile with XWordInfo link, stats, puzzle table with co-constructors (conditionally hidden when no co-constructors exist), player home page link when the constructor is also a player, and cross-links to player/editor views.

### Editor View

Editor profile with stats, puzzle table, and cross-links to player/constructor views.

### Formatting Conventions

- **Lists**: Oxford-comma separated with "and" before the last item
- **Episode links**: Link to Libsyn player with start time
- **Person/constructor/editor names**: Link to their respective detail pages
- **Round dates**: Display as M/D/YYYY format, link to round detail page
- **Puzzle dates**: Link to xwordinfo.com puzzle page
- **XWordInfo profiles**: Link to xwordinfo.com author page

### Caching

All shortcode output is cached using WordPress transients with a 24-hour TTL. Cache keys are versioned and automatically flushed when data changes. WP Super Cache and wp object cache are also purged when applicable.

## REST API

A comprehensive read-only REST API is available under the `kealoa/v1` namespace. All endpoints are public and return JSON.

See [REST-API.md](REST-API.md) for full documentation.

**Endpoint summary:**

| Endpoint | Description |
|----------|-------------|
| `GET /rounds` | List rounds (paginated) |
| `GET /rounds/{id}` | Single round with full details |
| `GET /rounds/stats` | Overview stats and per-year breakdown |
| `GET /persons` | List players (paginated, searchable) |
| `GET /persons/{id}` | Single player with stats |
| `GET /persons/{id}/rounds` | Player round history |
| `GET /persons/{id}/stats/{breakdown}` | Player stats by year, day, constructor, editor, direction, length, decade, clue-number |
| `GET /persons/{id}/stats/streaks` | Player best streaks by year |
| `GET /constructors` | List constructors (paginated, searchable) |
| `GET /constructors/{id}` | Single constructor with stats and puzzles |
| `GET /editors` | List editors with stats |
| `GET /editors/{name}` | Single editor with stats and puzzles |
| `GET /puzzles` | List puzzles (paginated) |
| `GET /puzzles/{id}` | Single puzzle with constructors |
| `GET /clues/{id}` | Single clue with guesses |
| `GET /search?q=` | Full-text search across all entities |
| `GET /leaderboard/scores` | Highest round scores |
| `GET /leaderboard/streaks` | Longest streaks |
| `GET /game-round/{id}` | Game data for a single round |

## Data Import / Export

### Export

- Individual CSV export for constructors, persons, puzzles, rounds, clues, and guesses
- **Export All** as a ZIP archive containing all 6 CSVs
- UTF-8 BOM for Excel compatibility

### Import

- CSV import for each data type individually
- **ZIP import** processes all 6 CSVs in dependency order
- Supports overwrite (update existing) or skip-existing mode
- Auto-normalizes date formats (M/D/YYYY, MM/DD/YYYY, YYYY-MM-DD)
- Auto-creates constructors/persons during import if not found
- CSV template files available for download

## Sitemaps

The plugin integrates with both WordPress core sitemaps (WP 5.5+) and Yoast SEO to expose all KEALOA pages:

- `/kealoa/round/{id}/` for all rounds
- `/kealoa/person/{name}/` for all players
- `/kealoa/constructor/{name}/` for all constructors
- `/kealoa/editor/{name}/` for all editors

## Data Model

### Database Tables

The plugin creates 9 custom database tables (all prefixed with `{wp_prefix}kealoa_`):

| Table | Description |
|-------|-------------|
| `constructors` | Crossword puzzle constructors (name, XWordInfo profile, image) |
| `persons` | People / players (name, home page URL, image) |
| `puzzles` | Crossword puzzles (publication date, editor name) |
| `puzzle_constructors` | Junction: puzzles ↔ constructors (with ordering) |
| `rounds` | Game rounds (date, episode info, clue giver, descriptions) |
| `round_solutions` | Solution words per round (with ordering) |
| `round_guessers` | Junction: rounds ↔ players |
| `clues` | Clues within rounds (puzzle reference, clue text, correct answer) |
| `guesses` | Player guesses per clue (guessed word, correct/incorrect) |

### Entity Relationships

```
Persons ──┬── Rounds (as clue giver)
           ├── Round Guessers (as player)
           └── Guesses (as guesser)

Constructors ── Puzzle Constructors ── Puzzles ── Clues ── Guesses

Rounds ──┬── Round Solutions
          ├── Round Guessers
          └── Clues ── Guesses

Puzzles ── Clues (editor_name on puzzle)
```

## Development

### File Structure

```
kealoa-reference/
├── kealoa-reference.php          # Main plugin file, routing, search, REST
├── uninstall.php                 # Cleanup on plugin deletion
├── LICENSE
├── includes/
│   ├── class-kealoa-activator.php      # Activation / table creation
│   ├── class-kealoa-deactivator.php    # Deactivation cleanup
│   ├── class-kealoa-db.php             # Database operations (83 public methods)
│   ├── class-kealoa-formatter.php      # Display formatting utilities
│   ├── class-kealoa-shortcodes.php     # Shortcode handlers (~2000 lines)
│   ├── class-kealoa-blocks.php         # Gutenberg block registration
│   ├── class-kealoa-rest-api.php       # REST API endpoints
│   ├── class-kealoa-export.php         # CSV/ZIP data export
│   ├── class-kealoa-import.php         # CSV/ZIP data import
│   ├── class-kealoa-sitemap.php        # Sitemap registration
│   └── class-kealoa-sitemap-provider.php # WP core sitemap provider
├── admin/
│   └── class-kealoa-admin.php          # Admin interface
├── blocks/
│   ├── rounds-table/                   # block.json + render.php
│   ├── round-view/
│   ├── persons-table/
│   ├── person-view/
│   ├── constructors-table/
│   ├── constructor-view/
│   ├── editors-table/
│   ├── editor-view/
│   ├── play-game/
│   └── version-info/
├── assets/
│   ├── css/
│   │   ├── kealoa-palette.css          # Color palette / design tokens
│   │   ├── kealoa-frontend.css         # Frontend display styles
│   │   ├── kealoa-admin.css            # Admin panel styles
│   │   ├── kealoa-game.css             # Interactive game styles
│   │   └── blocks-editor.css           # Gutenberg editor styles
│   └── js/
│       ├── kealoa-frontend.js          # Table sorting, tabs, Chart.js charts
│       ├── kealoa-admin.js             # Admin AJAX and form enhancements
│       ├── kealoa-game.js              # Interactive game logic
│       └── blocks-editor.js            # Gutenberg block editor registration
├── templates/
│   └── csv/                            # Import template files
│       ├── constructors.csv
│       ├── persons.csv
│       ├── puzzles.csv
│       ├── rounds.csv
│       ├── clues.csv
│       └── guesses.csv
└── languages/
```

### Dependencies

- **Chart.js 4.x** — Score distribution and accuracy charts on person view (loaded from CDN)
- **jQuery** — Admin panel interactivity (bundled with WordPress)

## License

Copyright &copy; 2026 Eric Peterson

This project is licensed under the [Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International License](https://creativecommons.org/licenses/by-nc-sa/4.0/).

[![CC BY-NC-SA 4.0](https://licensebuttons.net/l/by-nc-sa/4.0/88x31.png)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

You are free to share and adapt this work for non-commercial purposes, as long as you give appropriate credit and distribute any derivative works under the same license.

## Credits

- **Author**: [Eric Peterson](https://epeterso2.com) (eric@puzzlehead.org)
- **Game concept**: Brian Cimmet
- **Fill Me In podcast**: [bemoresmarter.libsyn.com](https://bemoresmarter.libsyn.com)
- **XWordInfo**: [xwordinfo.com](https://www.xwordinfo.com)
