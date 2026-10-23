<?php

declare(strict_types=1);

namespace Irodev\IndexNow\Tests;

use Irodev\IndexNow\Sitemap;
use PHPUnit\Framework\TestCase;

final class SitemapTest extends TestCase
{
    public function testReadsUrlsAndDates(): void
    {
        self::assertSame([
            'https://example.com/' => '2026-10-23',
            'https://example.com/services' => '2026-10-23T21:30:00+03:00',
            'https://example.com/contact' => '',
        ], Sitemap::fromFile(__DIR__ . '/Fixtures/sitemap.xml'));
    }

    public function testListsTheChildrenOfAnIndex(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/Fixtures/sitemap-index.xml');

        self::assertSame([], Sitemap::entries($xml));
        self::assertSame(['https://example.com/sitemap-pages.xml', 'https://example.com/sitemap-blog.xml'], Sitemap::childSitemaps($xml));
    }

    public function testBrokenXmlGivesNull(): void
    {
        $half = substr((string) file_get_contents(__DIR__ . '/Fixtures/sitemap.xml'), 0, 200);

        self::assertNull(Sitemap::entries($half));
        self::assertNull(Sitemap::fromFile(__DIR__ . '/Fixtures/missing.xml'));
        self::assertSame([], Sitemap::childSitemaps($half));
    }

    public function testMergeFailsWhenOnePartFails(): void
    {
        self::assertSame(['a' => '1', 'b' => '2'], Sitemap::merge(['a' => '1'], ['b' => '2']));
        self::assertNull(Sitemap::merge(['a' => '1'], null));
    }
}
