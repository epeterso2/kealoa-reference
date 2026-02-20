<?php
/**
 * Play Game Block Render
 *
 * Outputs the game container and inline JSON data for a random round.
 * All game interaction is handled client-side by kealoa-game.js.
 *
 * @package KEALOA_Reference
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$db = new Kealoa_DB();

// Get all round IDs that have at least one clue
global $wpdb;
$rounds_table = $wpdb->prefix . 'kealoa_rounds';
$clues_table = $wpdb->prefix . 'kealoa_clues';
$round_ids = $wpdb->get_col(
    "SELECT DISTINCT r.id FROM {$rounds_table} r
     INNER JOIN {$clues_table} c ON c.round_id = r.id
     ORDER BY r.id"
);

if (empty($round_ids)) {
    echo '<p class="kealoa-block-placeholder">' .
        esc_html__('No rounds with clues found.', 'kealoa-reference') .
        '</p>';
    return;
}

// Build a compact data payload with all playable round IDs
// The full round data will be loaded on demand via REST API
$force_round = isset($_GET['round']) ? absint($_GET['round']) : 0;
?>
<div class="kealoa-game"
     data-rest-url="<?php echo esc_url(rest_url('kealoa/v1/game-round')); ?>"
     data-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>"
     data-round-ids="<?php echo esc_attr(wp_json_encode(array_map('intval', $round_ids))); ?>"
     <?php if ($force_round && in_array($force_round, array_map('intval', $round_ids), true)): ?>
     data-force-round="<?php echo esc_attr($force_round); ?>"
     <?php endif; ?>>
    <div class="kealoa-game__welcome">
        <h2 class="kealoa-game__title"><?php esc_html_e('Play KEALOA!', 'kealoa-reference'); ?></h2>
        <p class="kealoa-game__description">
            <?php esc_html_e('Test your crossword knowledge! You\'ll be given clues from a real KEALOA round and asked to choose the correct answer. See how you stack up against the players who played this round on the show.', 'kealoa-reference'); ?>
        </p>
        <div class="kealoa-game__mode-buttons">
            <button type="button" class="kealoa-game__start-btn" data-mode="show">
                <?php esc_html_e('In Show Order', 'kealoa-reference'); ?>
            </button>
            <button type="button" class="kealoa-game__start-btn" data-mode="random">
                <?php esc_html_e('In Random Order', 'kealoa-reference'); ?>
            </button>
        </div>
    </div>
</div>
