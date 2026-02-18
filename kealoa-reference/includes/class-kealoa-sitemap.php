<?php
/**
 * KEALOA Sitemap Provider
 *
 * Registers dynamic KEALOA pages (rounds, players, constructors, editors)
 * with the WordPress core Sitemaps API (WP 5.5+) and with Yoast SEO
 * when it is active.
 *
 * @package Kealoa_Reference
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress core sitemap provider for KEALOA virtual pages.
 */
class Kealoa_Sitemap_Provider extends WP_Sitemaps_Provider {

    /**
     * @var Kealoa_DB
     */
    private Kealoa_DB $db;

    public function __construct() {
        $this->name        = 'kealoa';
        $this->object_type = 'kealoa';
        $this->db          = new Kealoa_DB();
    }

    /**
     * Return the object subtypes exposed by this provider.
     *
     * @return array<string, object> Keyed by subtype slug.
     */
    public function get_object_subtypes(): array {
        return [
            'rounds'       => (object) ['name' => 'rounds',       'label' => 'KEALOA Rounds'],
            'players'      => (object) ['name' => 'players',      'label' => 'KEALOA Players'],
            'constructors' => (object) ['name' => 'constructors', 'label' => 'KEALOA Constructors'],
            'editors'      => (object) ['name' => 'editors',      'label' => 'KEALOA Editors'],
        ];
    }

    /**
     * Return the URL list for a given page and object subtype.
     *
     * @param int    $page_num       1-based page number.
     * @param string $object_subtype The subtype slug.
     * @return array<int, array{loc: string}> Array of sitemap entry arrays.
     */
    public function get_url_list(int $page_num, string $object_subtype = ''): array {
        /**
         * WordPress core paginates sitemaps at 2000 URLs per page.
         *
         * @see wp_sitemaps_get_max_urls()
         */
        $max = wp_sitemaps_get_max_urls($this->object_type);
        $offset = ($page_num - 1) * $max;

        return match ($object_subtype) {
            'rounds'       => $this->get_round_urls($max, $offset),
            'players'      => $this->get_player_urls($max, $offset),
            'constructors' => $this->get_constructor_urls($max, $offset),
            'editors'      => $this->get_editor_urls($max, $offset),
            default        => [],
        };
    }

    /**
     * Return the maximum number of pages for a subtype.
     *
     * @param string $object_subtype The subtype slug.
     * @return int
     */
    public function get_max_num_pages(string $object_subtype = ''): int {
        $max = wp_sitemaps_get_max_urls($this->object_type);
        $total = match ($object_subtype) {
            'rounds'       => $this->db->count_rounds(),
            'players'      => $this->db->count_persons(),
            'constructors' => $this->db->count_constructors(),
            'editors'      => $this->count_editors(),
            default        => 0,
        };

        return (int) ceil($total / $max);
    }

    // =========================================================================
    // URL BUILDERS (private)
    // =========================================================================

    /**
     * @return array<int, array{loc: string}>
     */
    private function get_round_urls(int $limit, int $offset): array {
        $rounds = $this->db->get_rounds([
            'limit'   => $limit,
            'offset'  => $offset,
            'orderby' => 'round_date',
            'order'   => 'DESC',
        ]);

        $urls = [];
        foreach ($rounds as $round) {
            $urls[] = [
                'loc' => home_url('/kealoa/round/' . $round->id . '/'),
            ];
        }
        return $urls;
    }

    /**
     * @return array<int, array{loc: string}>
     */
    private function get_player_urls(int $limit, int $offset): array {
        $persons = $this->db->get_persons([
            'limit'  => $limit,
            'offset' => $offset,
        ]);

        $urls = [];
        foreach ($persons as $person) {
            $slug = str_replace(' ', '_', $person->full_name);
            $urls[] = [
                'loc' => home_url('/kealoa/person/' . urlencode($slug) . '/'),
            ];
        }
        return $urls;
    }

    /**
     * @return array<int, array{loc: string}>
     */
    private function get_constructor_urls(int $limit, int $offset): array {
        $constructors = $this->db->get_constructors([
            'limit'  => $limit,
            'offset' => $offset,
        ]);

        $urls = [];
        foreach ($constructors as $constructor) {
            $slug = str_replace(' ', '_', $constructor->full_name);
            $urls[] = [
                'loc' => home_url('/kealoa/constructor/' . urlencode($slug) . '/'),
            ];
        }
        return $urls;
    }

    /**
     * @return array<int, array{loc: string}>
     */
    private function get_editor_urls(int $limit, int $offset): array {
        $editors = $this->get_editor_names($limit, $offset);

        $urls = [];
        foreach ($editors as $name) {
            $urls[] = [
                'loc' => home_url('/kealoa/editor/' . urlencode($name) . '/'),
            ];
        }
        return $urls;
    }

    // =========================================================================
    // EDITOR HELPERS (editors are not a first-class entity with an id)
    // =========================================================================

    /**
     * Count distinct editor names.
     */
    private function count_editors(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'kealoa_puzzles';
        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT editor_name)
             FROM {$table}
             WHERE editor_name IS NOT NULL AND editor_name != ''"
        );
    }

    /**
     * Get a paginated list of editor names.
     *
     * @return string[]
     */
    private function get_editor_names(int $limit, int $offset): array {
        global $wpdb;
        $table = $wpdb->prefix . 'kealoa_puzzles';
        return $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT editor_name
                 FROM {$table}
                 WHERE editor_name IS NOT NULL AND editor_name != ''
                 ORDER BY editor_name ASC
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    // =========================================================================
    // ALL URLS (flat list — used by Yoast integration)
    // =========================================================================

    /**
     * Return every KEALOA URL as a flat array. Used by the Yoast SEO
     * integration since Yoast expects the plugin to output its own XML.
     *
     * @return array<int, array{loc: string, subtype: string}>
     */
    public function get_all_urls(): array {
        $urls = [];

        foreach ($this->get_round_urls(50000, 0) as $u) {
            $urls[] = array_merge($u, ['subtype' => 'rounds']);
        }
        foreach ($this->get_player_urls(50000, 0) as $u) {
            $urls[] = array_merge($u, ['subtype' => 'players']);
        }
        foreach ($this->get_constructor_urls(50000, 0) as $u) {
            $urls[] = array_merge($u, ['subtype' => 'constructors']);
        }
        foreach ($this->get_editor_urls(50000, 0) as $u) {
            $urls[] = array_merge($u, ['subtype' => 'editors']);
        }

        return $urls;
    }
}

// =============================================================================
// REGISTRATION HOOKS
// =============================================================================

/**
 * Register the KEALOA sitemap provider with WordPress core sitemaps.
 */
function kealoa_register_sitemap_provider(WP_Sitemaps $wp_sitemaps): void {
    $wp_sitemaps->registry->add_provider('kealoa', new Kealoa_Sitemap_Provider());
}
add_action('wp_sitemaps_init', 'kealoa_register_sitemap_provider');

// =============================================================================
// YOAST SEO INTEGRATION
// =============================================================================

/**
 * Add KEALOA sitemap entries to the Yoast SEO sitemap index.
 *
 * @param string $index_xml Current index XML string.
 * @return string Modified index XML.
 */
function kealoa_yoast_sitemap_index(string $index_xml): string {
    $date = gmdate('Y-m-d\TH:i:s+00:00');

    $index_xml .= '<sitemap>'
        . '<loc>' . esc_url(home_url('/kealoa-sitemap.xml')) . '</loc>'
        . '<lastmod>' . esc_xml($date) . '</lastmod>'
        . '</sitemap>' . "\n";

    return $index_xml;
}

/**
 * Output the custom KEALOA sitemap XML when Yoast requests it.
 */
function kealoa_yoast_sitemap_content(): void {
    $provider = new Kealoa_Sitemap_Provider();
    $urls = $provider->get_all_urls();

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url(home_url('/main-sitemap.xsl')) . '"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($urls as $entry) {
        $xml .= "\t<url>\n";
        $xml .= "\t\t<loc>" . esc_xml($entry['loc']) . "</loc>\n";
        $xml .= "\t</url>\n";
    }

    $xml .= '</urlset>';

    /** @var WPSEO_Sitemaps $wpseo_sitemaps */
    global $wpseo_sitemaps;
    if (isset($wpseo_sitemaps)) {
        $wpseo_sitemaps->set_sitemap($xml);
    }
}

/**
 * Hook Yoast integration only when Yoast SEO is active.
 */
function kealoa_maybe_init_yoast_sitemap(): void {
    if (!class_exists('WPSEO_Sitemaps')) {
        return;
    }

    add_filter('wpseo_sitemap_index', 'kealoa_yoast_sitemap_index');
    add_action('wpseo_do_sitemap_kealoa', 'kealoa_yoast_sitemap_content');
}
add_action('init', 'kealoa_maybe_init_yoast_sitemap');
