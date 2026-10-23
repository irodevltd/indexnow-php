<?php

declare(strict_types=1);

namespace Irodev\IndexNow\Tests;

use Irodev\IndexNow\Client;
use Irodev\IndexNow\Key;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private const KEY = '0123456789abcdef0123456789abcdef';

    public function testSendsTheProtocolBody(): void
    {
        $t = new FakeTransport([202]);
        $result = (new Client('Example.com', self::KEY, transport: $t))->submit(['https://example.com/a', 'https://example.com/b']);

        self::assertTrue($result->accepted());
        self::assertSame(Client::ENDPOINT, $t->calls[0]['url']);
        self::assertSame([
            'host' => 'example.com',
            'key' => self::KEY,
            'keyLocation' => 'https://example.com/' . self::KEY . '.txt',
            'urlList' => ['https://example.com/a', 'https://example.com/b'],
        ], $t->calls[0]['body']);
    }

    public function testDropsDuplicatesAndOtherHosts(): void
    {
        $t = new FakeTransport();
        $result = (new Client('example.com', self::KEY, transport: $t))->submit([
            'https://example.com/a', 'https://example.com/a', 'https://www.example.com/b', 'https://other.org/c', 'ftp://example.com/d', '/relative',
        ]);

        self::assertSame(['https://example.com/a'], $result->urls);
        self::assertSame(['https://example.com/a'], $t->lastUrls());
    }

    public function testUsesACustomKeyLocation(): void
    {
        $t = new FakeTransport();
        (new Client('example.com', self::KEY, 'https://example.com/keys/indexnow.txt', $t))->submit(['https://example.com/']);

        self::assertSame('https://example.com/keys/indexnow.txt', $t->calls[0]['body']['keyLocation']);
    }

    public function testRefusesAnEmptyList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Client('example.com', self::KEY, transport: new FakeTransport()))->submit(['https://other.org/']);
    }

    public function testRefusesMoreThanTheProtocolAllows(): void
    {
        $urls = array_map(fn (int $i) => 'https://example.com/p' . $i, range(1, Client::MAX_URLS + 1));
        $this->expectException(\LengthException::class);
        (new Client('example.com', self::KEY, transport: new FakeTransport()))->submit($urls);
    }

    public function testRefusesAnInvalidKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Client('example.com', 'short', transport: new FakeTransport());
    }

    #[DataProvider('statuses')]
    public function testExplainsEachStatus(int $status, bool $accepted, string $starts): void
    {
        $result = (new Client('example.com', self::KEY, transport: new FakeTransport([$status])))->submit(['https://example.com/']);

        self::assertSame($accepted, $result->accepted());
        self::assertStringStartsWith($starts, $result->reason());
    }

    /** @return iterable<array{int, bool, string}> */
    public static function statuses(): iterable
    {
        yield [200, true, 'OK'];
        yield [202, true, 'Accepted'];
        yield [400, false, 'Bad request'];
        yield [403, false, 'Forbidden'];
        yield [422, false, 'Unprocessable'];
        yield [429, false, 'Too many requests'];
        yield [0, false, 'No answer'];
        yield [500, false, 'Unexpected answer: HTTP 500'];
    }

    public function testKeysFollowTheProtocol(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', Key::generate());
        self::assertTrue(Key::isValid('abc-DEF-123'));
        self::assertFalse(Key::isValid('1234567'));
        self::assertFalse(Key::isValid(str_repeat('a', 129)));
        self::assertFalse(Key::isValid('has space'));
    }

    public function testWritesAndChecksTheKeyFile(): void
    {
        $dir = sys_get_temp_dir() . '/indexnow-key-' . bin2hex(random_bytes(4));
        mkdir($dir);
        try {
            self::assertFalse(Key::fileMatches($dir, self::KEY));
            $path = Key::writeFile($dir, self::KEY);
            self::assertSame(self::KEY, file_get_contents($path));
            self::assertTrue(Key::fileMatches($dir, self::KEY));
            self::assertFalse(Key::fileMatches($dir, 'another-key-123'));
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }
}
