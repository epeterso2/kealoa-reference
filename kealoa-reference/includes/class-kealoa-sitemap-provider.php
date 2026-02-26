<?php
/**
 * @copyright 2026 Eric Peterson (eric@puzzlehead.org)
 * @license   CC BY-NC-SA 4.0 <https://creativecommons.org/licenses/by-nc-sa/4.0/>
 *
 * KEALOA Sitemap Provider Class
 *
 * WordPress core sitemap provider for KEALOA virtual pages.
 * This file is loaded on demand (not at plugin boot) because
 * WP_Sitemaps_Provider is only available after the sitemaps
 * module has been initialised.
 *
 * @package Kealoa_Reference
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

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
    public function get_object_subtypes() {
        return [
            'rounds'  => (object) ['name' => 'rounds',  'label' => 'KEALOA Rounds'],
            'persons' => (object) ['name' => 'persons', 'label' => 'KEALOA Persons'],
            'puzzles' => (object) ['name' => 'puzzles', 'label' => 'KEALOA Puzzles'],
        ];
    }

    /**
     * Return the URL list for a given page and object subtype.
     *
     * @param int    $page_num       1-based page number.
     * @param string $object_subtype The subtype slug.
     * @return array<int, array{loc: string}> Array of sitemap entry arrays.
     */
    public function get_url_list($page_num, $object_subtype = '') {
        $page_num = (int) $page_num;
        $object_subtype = (string) $object_subtype;
        $max = wp_sitemaps_get_max_urls($this->object_type);
        $offset = ($page_num - 1) * $max;

        return match ($object_subtype) {
            'rounds'  => $this->get_round_urls($max, $offset),
            'persons' => $this->get_person_urls($max, $offset),
            'puzzles' => $this->get_puzzle_urls($max, $offset),
            default   => [],
        };
    }

    /**
     * Return the maximum number of pages for a subtype.
     *
     * @param string $object_subtype The subtype slug.
     * @return int
     */
    public function get_max_num_pages($object_subtype = '') {
        $object_subtype = (string) $object_subtype;
        $max = wp_sitemaps_get_max_urls($this->object_type);
        $total = match ($object_subtype) {
            'rounds'  => $this->db->count_rounds(),
            'persons' => $this->db->count_persons(),
            'puzzles' => $this->db->count_puzzles(),
            default   => 0,
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
    private function get_person_urls(int $limit, int $offset): array {
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
    private function get_puzzle_urls(int $limit, int $offset): array {
        $puzzles = $this->db->get_puzzles([
            'limit'   => $limit,
            'offset'  => $offset,
            'orderby' => 'publication_date',
            'order'   => 'DESC',
        ]);

        $urls = [];
        foreach ($puzzles as $puzzle) {
            $urls[] = [
                'loc' => home_url('/kealoa/puzzle/' . $puzzle->publication_date . '/'),
            ];
        }
        return $urls;
    }

    // =========================================================================
    // ALL URLS (flat list — used by Yoast integration)
    // =========================================================================

    /**
     * Return every KEALOA URL as a flat array.
     *
     * @return array<int, array{loc: string, subtype: string}>
     */
    public function get_all_urls(): array {
        $urls = [];

        foreach ($this->get_round_urls(50000, 0) as $u) {
            $urls[] = array_merge($u, ['subtype' => 'rounds']);
        }
        foreach ($this->get_person_urls(50000, 0) as $u) {
            $urls[] = array_merge($u, ['subtype' => 'persons']);
        }
        foreach ($this->get_puzzle_urls(50000, 0) as $u) {
            $urls[] = array_merge($u, ['subtype' => 'puzzles']);
        }

        return $urls;
    }
}
