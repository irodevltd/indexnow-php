<?php

declare(strict_types=1);

namespace Irodev\IndexNow;

/**
 * Reads sitemaps (sitemaps.org protocol). Their <lastmod> dates are what the sync compares, so keep them accurate:
 * change a page's date when its content changes, and only then.
 */
final class Sitemap
{
    /**
     * URL => <lastmod> ('' when the entry has none) from a <urlset> sitemap. A sitemap index lists sitemaps, not
     * pages: it gives [] here, and its children come from childSitemaps(). Null when the XML can't be read, for
     * example while a new sitemap is still being uploaded.
     *
     * @return array<string, string>|null
     */
    public static function entries(string $xml): ?array
    {
        $doc = self::load($xml);
        if ($doc === null) {
            return null;
        }
        $out = [];
        foreach ($doc->url as $url) {
            $loc = trim((string) $url->loc);
            if ($loc !== '') {
                $out[$loc] = trim((string) $url->lastmod);
            }
        }
        return $out;
    }

    /**
     * The sitemaps a sitemap index lists ([] for an ordinary sitemap).
     *
     * @return list<string>
     */
    public static function childSitemaps(string $xml): array
    {
        $doc = self::load($xml);
        $out = [];
        foreach ($doc?->sitemap ?? [] as $sitemap) {
            $loc = trim((string) $sitemap->loc);
            if ($loc !== '') {
                $out[] = $loc;
            }
        }
        return $out;
    }

    /**
     * Entries from a sitemap file on this server. Null when the file is missing or unreadable.
     *
     * @return array<string, string>|null
     */
    public static function fromFile(string $path): ?array
    {
        $xml = is_file($path) ? file_get_contents($path) : false;
        return $xml === false ? null : self::entries($xml);
    }

    /**
     * Merge several sitemaps' entries. Null when any of them is null, so a broken sitemap can never make its pages
     * look removed.
     *
     * @param array<string, string>|null ...$lists
     * @return array<string, string>|null
     */
    public static function merge(?array ...$lists): ?array
    {
        $out = [];
        foreach ($lists as $list) {
            if ($list === null) {
                return null;
            }
            $out = array_merge($out, $list);
        }
        return $out;
    }

    private static function load(string $xml): ?\SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        return $doc === false ? null : $doc;
    }
}
