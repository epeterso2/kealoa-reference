<?php
/**
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
    require_once __DIR__ . '/class-kealoa-sitemap-provider.php';
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
