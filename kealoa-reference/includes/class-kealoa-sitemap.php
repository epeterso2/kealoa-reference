<?php
/**
 * @copyright 2026 Eric Peterson (eric@puzzlehead.org)
 * @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
 *
 * KEALOA Sitemap Hooks
 *
 * Registers the KEALOA sitemap provider with the WordPress core
 * Sitemaps API (WP 5.5+) and with Yoast SEO when it is active.
 *
 * The provider class itself lives in class-kealoa-sitemap-provider.php
 * and is loaded on demand because WP_Sitemaps_Provider is not available
 * at plugin boot time.
 *
 * @package Kealoa_Reference
 */

declare(strict_types=1);

namespace com\epeterso2\kealoa;

use WP_Sitemaps;

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// WORDPRESS CORE SITEMAPS
// =============================================================================

/**
 * Register the KEALOA sitemap provider with WordPress core sitemaps.
 */
function kealoa_register_sitemap_provider(WP_Sitemaps $wp_sitemaps): void {
    require_once __DIR__ . '/class-kealoa-sitemap-provider.php';
    $wp_sitemaps->registry->add_provider('kealoa', new Kealoa_Sitemap_Provider());
}
add_action('wp_sitemaps_init', __NAMESPACE__ . '\\kealoa_register_sitemap_provider');

// =============================================================================
// YOAST SEO INTEGRATION
// =============================================================================

/**
 * The subtypes exposed in the Yoast sitemap index.
 */
const KEALOA_SITEMAP_SUBTYPES = ['rounds', 'persons', 'puzzles'];

/**
 * Maximum URLs per Yoast sitemap page.
 *
 * Yoast uses 1 000 by default; we honour the same constant if available,
 * otherwise fall back to 1 000.
 */
function kealoa_yoast_max_urls(): int {
    return defined('YOAST_SEO_SITEMAP_MAX_URLS')
        ? (int) YOAST_SEO_SITEMAP_MAX_URLS
        : 1000;
}

/**
 * Add per-subtype KEALOA sitemap entries to the Yoast SEO sitemap index.
 *
 * Produces entries like:
 *   kealoa-rounds-sitemap.xml
 *   kealoa-rounds-sitemap2.xml  (page 2, if needed)
 *   kealoa-persons-sitemap.xml
 *   kealoa-puzzles-sitemap.xml
 *
 * @param string $index_xml Current index XML string.
 * @return string Modified index XML.
 */
function kealoa_yoast_sitemap_index(string $index_xml): string {
    require_once __DIR__ . '/class-kealoa-sitemap-provider.php';
    $provider = new Kealoa_Sitemap_Provider();
    $max = kealoa_yoast_max_urls();

    foreach (KEALOA_SITEMAP_SUBTYPES as $subtype) {
        $total = match ($subtype) {
            'rounds'  => (new Kealoa_DB())->count_rounds(),
            'persons' => (new Kealoa_DB())->count_persons(),
            'puzzles' => (new Kealoa_DB())->count_puzzles(),
            default   => 0,
        };
        if ($total === 0) {
            continue;
        }

        $pages = (int) ceil($total / $max);
        $lastmod = $provider->get_subtype_last_modified($subtype)
            ?? gmdate('Y-m-d\TH:i:s+00:00');

        for ($page = 1; $page <= $pages; $page++) {
            $suffix = $page > 1 ? (string) $page : '';
            $loc = home_url("/kealoa-{$subtype}-sitemap{$suffix}.xml");

            $index_xml .= '<sitemap>'
                . '<loc>' . esc_url($loc) . '</loc>'
                . '<lastmod>' . esc_xml($lastmod) . '</lastmod>'
                . '</sitemap>' . "\n";
        }
    }

    return $index_xml;
}

/**
 * Output a per-subtype KEALOA sitemap XML page when Yoast requests it.
 *
 * Yoast fires `wpseo_do_sitemap_kealoa-{subtype}` for the registered
 * sitemap slug.  The page number is derived from the request URI.
 */
function kealoa_yoast_sitemap_subtype(string $subtype): void {
    require_once __DIR__ . '/class-kealoa-sitemap-provider.php';
    $provider = new Kealoa_Sitemap_Provider();
    $max = kealoa_yoast_max_urls();

    // Determine page number from the request URI (e.g. kealoa-rounds-sitemap2.xml → page 2)
    $page = 1;
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('/sitemap(\d+)\.xml/i', $uri, $m)) {
        $page = max(1, (int) $m[1]);
    }

    $offset = ($page - 1) * $max;
    $urls = $provider->get_subtype_urls($subtype, $max, $offset);

    $xml  = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($urls as $entry) {
        $xml .= "\t<url>\n";
        $xml .= "\t\t<loc>" . esc_xml($entry['loc']) . "</loc>\n";
        if (!empty($entry['lastmod'])) {
            $xml .= "\t\t<lastmod>" . esc_xml($entry['lastmod']) . "</lastmod>\n";
        }
        $xml .= "\t</url>\n";
    }

    $xml .= '</urlset>';

    /** @var \WPSEO_Sitemaps $wpseo_sitemaps */
    global $wpseo_sitemaps;
    if (isset($wpseo_sitemaps)) {
        $wpseo_sitemaps->set_sitemap($xml);
    }
}

/**
 * Hook Yoast integration only when Yoast SEO is active.
 */
function kealoa_maybe_init_yoast_sitemap(): void {
    if (!defined('WPSEO_VERSION')) {
        return;
    }

    add_filter('wpseo_sitemap_index', __NAMESPACE__ . '\\kealoa_yoast_sitemap_index');

    // Register a sitemap handler for each subtype
    foreach (KEALOA_SITEMAP_SUBTYPES as $subtype) {
        add_action(
            "wpseo_do_sitemap_kealoa-{$subtype}",
            fn() => kealoa_yoast_sitemap_subtype($subtype)
        );
    }

    // SEO meta for virtual pages (runs on template_redirect, priority 20 = after KEALOA sets up the virtual post)
    add_action('template_redirect', __NAMESPACE__ . '\\kealoa_yoast_virtual_page_meta', 20);
}
add_action('init', __NAMESPACE__ . '\\kealoa_maybe_init_yoast_sitemap');

// =============================================================================
// YOAST SEO META FOR VIRTUAL PAGES
// =============================================================================

/**
 * When a KEALOA virtual page is active, register Yoast filters
 * that provide title, meta description, canonical URL and OpenGraph data.
 */
function kealoa_yoast_virtual_page_meta(): void {
    $type = $GLOBALS['kealoa_object_type'] ?? null;
    $id   = $GLOBALS['kealoa_object_id']   ?? null;

    if (!$type || !$id) {
        return;
    }

    $meta = kealoa_build_yoast_meta($type, (int) $id);
    if (!$meta) {
        return;
    }

    // Store for the closures below
    $GLOBALS['kealoa_yoast_meta'] = $meta;

    add_filter('wpseo_title', fn() => $meta['title']);
    add_filter('wpseo_metadesc', fn() => $meta['description']);
    add_filter('wpseo_canonical', fn() => $meta['canonical']);
    add_filter('wpseo_opengraph_url', fn() => $meta['canonical']);
    add_filter('wpseo_opengraph_title', fn() => $meta['og_title']);
    add_filter('wpseo_opengraph_desc', fn() => $meta['description']);
    add_filter('wpseo_opengraph_type', fn() => 'article');

    if (!empty($meta['og_image'])) {
        add_action('wpseo_add_opengraph_images', function ($og_image) use ($meta) {
            $og_image->add_image($meta['og_image']);
        });
    }
}

/**
 * Build Yoast meta array for a KEALOA virtual page.
 *
 * @param string $type 'person', 'round', or 'puzzle'.
 * @param int    $id   Entity ID.
 * @return array{title: string, description: string, canonical: string, og_title: string, og_image: string}|null
 */
function kealoa_build_yoast_meta(string $type, int $id): ?array {
    $db = new Kealoa_DB();
    $site_name = get_bloginfo('name');

    switch ($type) {
        case 'person':
            $person = $db->get_person($id);
            if (!$person) {
                return null;
            }
            $roles = $db->get_person_roles($id);
            $role_map = [
                'player'      => __('Player', 'kealoa-reference'),
                'constructor' => __('Constructor', 'kealoa-reference'),
                'editor'      => __('Editor', 'kealoa-reference'),
                'clue_giver'  => __('Host', 'kealoa-reference'),
            ];
            $role_label = !empty($roles)
                ? implode(' / ', array_map(fn($r) => $role_map[$r] ?? ucfirst($r), $roles))
                : __('Person', 'kealoa-reference');

            $slug = str_replace(' ', '_', $person->full_name);
            $canonical = home_url('/kealoa/person/' . urlencode($slug) . '/');

            $description = sprintf(
                __('%s — %s on the KEALOA quiz game from the Fill Me In podcast.', 'kealoa-reference'),
                $person->full_name,
                $role_label
            );

            $og_image = '';
            $person_media_id = (int) ($person->media_id ?? 0);
            if ($person_media_id > 0) {
                $og_image = wp_get_attachment_url($person_media_id) ?: '';
            }
            if (empty($og_image)) {
                $og_image = !empty($person->xwordinfo_image_url)
                    ? $person->xwordinfo_image_url
                    : Kealoa_Formatter::xwordinfo_image_url_from_name($person->full_name);
            }

            return [
                'title'       => sprintf('%s — %s — %s', $person->full_name, $role_label, $site_name),
                'description' => $description,
                'canonical'   => $canonical,
                'og_title'    => sprintf('%s — %s', $person->full_name, $role_label),
                'og_image'    => $og_image,
            ];

        case 'round':
            $round = $db->get_round($id);
            if (!$round) {
                return null;
            }
            $solutions = $db->get_round_solutions($id);
            $solution_text = Kealoa_Formatter::format_solution_words($solutions);
            $game_number = (int) ($round->game_number ?? $id);
            $canonical = home_url('/kealoa/round/' . $game_number . '/');

            $round_date = Kealoa_Formatter::format_date($round->round_date);
            $description = sprintf(
                __('KEALOA #%d (%s) — %s. Play along and see results from the Fill Me In podcast.', 'kealoa-reference'),
                $game_number,
                $round_date,
                $solution_text
            );

            return [
                'title'       => sprintf('KEALOA #%d — %s — %s', $game_number, $solution_text, $site_name),
                'description' => $description,
                'canonical'   => $canonical,
                'og_title'    => sprintf('KEALOA #%d — %s', $game_number, $solution_text),
                'og_image'    => '',
            ];

        case 'puzzle':
            $puzzle = $db->get_puzzle($id);
            if (!$puzzle) {
                return null;
            }
            $constructors = $db->get_puzzle_constructors($id);
            $constructor_names = implode(' & ', array_map(fn($c) => $c->full_name, $constructors));
            $formatted_date = Kealoa_Formatter::format_date($puzzle->publication_date);
            $day_name = date('l', strtotime($puzzle->publication_date));
            $canonical = home_url('/kealoa/puzzle/' . $puzzle->publication_date . '/');

            $description = sprintf(
                __('NYT Crossword from %s, %s by %s. See clues featured in KEALOA rounds on the Fill Me In podcast.', 'kealoa-reference'),
                $day_name,
                $formatted_date,
                $constructor_names ?: __('unknown constructor', 'kealoa-reference')
            );

            return [
                'title'       => sprintf('%s, %s — %s — %s', $day_name, $formatted_date, $constructor_names, $site_name),
                'description' => $description,
                'canonical'   => $canonical,
                'og_title'    => sprintf('%s, %s — %s', $day_name, $formatted_date, $constructor_names),
                'og_image'    => '',
            ];

        default:
            return null;
    }
}
