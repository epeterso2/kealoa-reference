# KEALOA Reference

A WordPress plugin for managing and displaying KEALOA quiz game data from the Fill Me In podcast.

## Description

KEALOA is a quiz game played on the Fill Me In podcast where a clue giver selects words from New York Times crossword puzzles that have similar lengths, spellings, and definitions. Players then try to identify which word matches each clue.

This plugin provides:

- **Complete data management** for KEALOA rounds, clues, puzzles, persons, and guesses
- **Admin interface** for entering and managing all KEALOA data
- **Frontend display** via shortcodes and Gutenberg blocks
- **Statistics tracking** for individual players across all rounds
- **Custom URL routing** for person and round detail pages

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

- **Dashboard** - Overview of data and available shortcodes/blocks
- **Rounds** - Manage KEALOA rounds with clue giver, players, and solution words
- **Persons** - Manage people (clue givers, players, puzzle constructors)
- **Puzzles** - Manage NYT puzzle dates and constructors

### Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[kealoa_rounds_table]` | Displays a table of all KEALOA rounds |
| `[kealoa_round id="X"]` | Displays a single round with all clues and guesses |
| `[kealoa_person id="X"]` | Displays a person's profile and statistics |

### Gutenberg Blocks

The plugin provides three blocks in the block editor:

- **KEALOA Rounds Table** - Displays all rounds in a table format
- **KEALOA Round View** - Displays a single round with all details
- **KEALOA Person View** - Displays a person's profile and statistics

### Custom URLs

The plugin creates custom URL routes:

- `/kealoa/person/{name}/` - Person detail page (spaces replaced by underscores)
- `/kealoa/round/{id}/` - Round detail page

## Data Model

### Persons
- Full name
- XWordInfo profile name (for linking to xwordinfo.com)
- Home page URL
- XWordInfo image URL

### Puzzles
- Publication date
- Constructors (one or more persons)

### Rounds
- Round date
- Fill Me In episode number
- Episode start time (seconds)
- Clue giver
- Players
- Solution words
- Description

### Clues
- Clue number within round
- NYT puzzle reference
- Puzzle clue number and direction (e.g., "42D", "1A")
- Clue text
- Correct answer

### Guesses
- Player
- Guessed word
- Correct/incorrect status

## Features

### Formatting Conventions

- **Lists**: Comma-separated with "and" before the last item
- **Episode links**: Link to `bemoresmarter.libsyn.com/player?episode=N&startTime=T`
- **Person names**: Link to person's detail page on the site
- **Round dates**: Display as M/D/YYYY format, link to round detail page
- **XWordInfo profiles**: Link to `xwordinfo.com/Author/{profile_name}`

### Statistics

The person view includes:

- Rounds played
- Total clues answered
- Total correct answers
- Overall accuracy percentage
- Min/Mean/Median/Max correct per round
- Results grouped by clue number
- Results grouped by puzzle day of week
- Complete round history

## Development

### File Structure

```
kealoa-reference/
├── kealoa-reference.php      # Main plugin file
├── uninstall.php             # Cleanup on plugin deletion
├── includes/
│   ├── class-kealoa-activator.php    # Activation/table creation
│   ├── class-kealoa-deactivator.php  # Deactivation cleanup
│   ├── class-kealoa-db.php           # Database operations
│   ├── class-kealoa-formatter.php    # Display formatting
│   ├── class-kealoa-shortcodes.php   # Shortcode handlers
│   └── class-kealoa-blocks.php       # Block registration
├── admin/
│   └── class-kealoa-admin.php        # Admin interface
├── blocks/
│   ├── rounds-table/
│   ├── round-view/
│   └── person-view/
├── assets/
│   ├── css/
│   │   ├── kealoa-frontend.css
│   │   ├── kealoa-admin.css
│   │   └── blocks-editor.css
│   └── js/
│       ├── kealoa-admin.js
│       └── blocks-editor.js
└── languages/
```

### Database Tables

The plugin creates the following custom database tables:

- `{prefix}_kealoa_persons`
- `{prefix}_kealoa_puzzles`
- `{prefix}_kealoa_puzzle_constructors`
- `{prefix}_kealoa_rounds`
- `{prefix}_kealoa_round_solutions`
- `{prefix}_kealoa_round_guessers`
- `{prefix}_kealoa_clues`
- `{prefix}_kealoa_guesses`

## License

This project is licensed under the [Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International License](https://creativecommons.org/licenses/by-nc-sa/4.0/).

[![CC BY-NC-SA 4.0](https://licensebuttons.net/l/by-nc-sa/4.0/88x31.png)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

You are free to share and adapt this work for non-commercial purposes, as long as you give appropriate credit and distribute any derivative works under the same license.

## Credits

- Game concept: Brian Cimmet
- Fill Me In podcast: [bemoresmarter.libsyn.com](https://bemoresmarter.libsyn.com)
- XWordInfo: [xwordinfo.com](https://www.xwordinfo.com)
