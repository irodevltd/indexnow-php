<?php

declare(strict_types=1);

namespace Irodev\IndexNow\Tests;

use Irodev\IndexNow\Client;
use Irodev\IndexNow\JsonLog;
use Irodev\IndexNow\Outcome;
use Irodev\IndexNow\Sync;
use PHPUnit\Framework\TestCase;

final class SyncTest extends TestCase
{
    private string $dir;
    private int $now = 1_800_000_000;
    private FakeTransport $transport;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/indexnow-sync-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->transport = new FakeTransport();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    private function sync(?callable $log = null): Sync
    {
        $client = new Client('example.com', '0123456789abcdef0123456789abcdef', transport: $this->transport);
        return new Sync($client, $this->dir . '/state.json', 600, 3600, $log, fn (): int => $this->now);
    }

    /** @return array<string, string> */
    private function site(): array
    {
        return ['https://example.com/' => '2026-10-22', 'https://example.com/a' => '2026-10-22', 'https://example.com/b' => ''];
    }

    public function testTheFirstRunSendsEverything(): void
    {
        $out = $this->sync()->run($this->site());

        self::assertSame(Outcome::SENT, $out->status);
        self::assertSame(array_keys($this->site()), $this->transport->lastUrls());
    }

    public function testNothingChangedSendsNothing(): void
    {
        $this->sync()->run($this->site());
        $this->now += 3600;

        self::assertSame(Outcome::NOTHING, $this->sync()->run($this->site())->status);
        self::assertCount(1, $this->transport->calls);
    }

    public function testARedatedPageWaitsOutTheInterval(): void
    {
        $this->sync()->run($this->site());
        $site = ['https://example.com/a' => '2026-10-23'] + $this->site();

        $this->now += 120;
        $out = $this->sync()->run($site);
        self::assertSame(Outcome::WAITING, $out->status);
        self::assertSame(1, $out->later);
        self::assertSame($this->now - 120 + 600, $out->retryAt);

        $this->now += 600;
        $out = $this->sync()->run($site);
        self::assertSame(Outcome::SENT, $out->status);
        self::assertSame(['https://example.com/a'], $this->transport->lastUrls());
    }

    public function testANewPageGoesOutWhileARecentOneWaits(): void
    {
        $this->sync()->run($this->site());
        $this->now += 60;
        $site = ['https://example.com/a' => '2026-10-23', 'https://example.com/new' => '2026-10-23'] + $this->site();

        $out = $this->sync()->run($site);

        self::assertSame(Outcome::SENT, $out->status);
        self::assertSame(['https://example.com/new'], $out->sent);
        self::assertSame(1, $out->later);
    }

    public function testARemovedPageIsSentOnceAndForgotten(): void
    {
        $this->sync()->run($this->site());
        $this->now += 3600;
        $site = $this->site();
        unset($site['https://example.com/b']);

        self::assertSame(['https://example.com/b'], $this->sync()->run($site)->sent);
        $this->now += 3600;
        self::assertSame(Outcome::NOTHING, $this->sync()->run($site)->status);
    }

    public function testAFailureWaitsAnHourUnlessForced(): void
    {
        $this->transport->statuses = [403];
        $out = $this->sync()->run($this->site());
        self::assertSame(Outcome::FAILED, $out->status);
        self::assertSame($this->now + 3600, $out->retryAt);

        $this->now += 1800;
        self::assertSame(Outcome::WAITING, $this->sync()->run($this->site())->status);
        self::assertSame(Outcome::SENT, $this->sync()->run($this->site(), force: true)->status);
        self::assertCount(2, $this->transport->calls);
    }

    public function testAFailedPageIsRetriedLater(): void
    {
        $this->transport->statuses = [0];
        $this->sync()->run($this->site());
        $this->now += 3600;

        $out = $this->sync()->run($this->site());

        self::assertSame(Outcome::SENT, $out->status);
        self::assertCount(3, $out->sent);
    }

    public function testAllSendsEveryPage(): void
    {
        $this->sync()->run($this->site());
        $this->now += 30;

        $out = $this->sync()->run($this->site(), force: true, all: true);

        self::assertSame(Outcome::SENT, $out->status);
        self::assertCount(3, $out->sent);
    }

    public function testAnUnreadableOrEmptySitemapSendsNothing(): void
    {
        self::assertSame(Outcome::EMPTY_LIST, $this->sync()->run(null)->status);
        $this->sync()->run($this->site());
        $this->now += 3600;

        self::assertSame(Outcome::EMPTY_LIST, $this->sync()->run([])->status);
        self::assertCount(1, $this->transport->calls);
    }

    public function testOtherHostsAreIgnored(): void
    {
        $out = $this->sync()->run(['https://example.com/' => '', 'https://cdn.example.net/x' => '']);

        self::assertSame(['https://example.com/'], $out->sent);
    }

    public function testASecondRequestIsBusyWhileTheFirstHoldsTheLock(): void
    {
        $lock = fopen($this->dir . '/state.json.lock', 'c');
        self::assertNotFalse($lock);
        flock($lock, LOCK_EX);
        try {
            self::assertSame(Outcome::BUSY, $this->sync()->run($this->site())->status);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function testPendingShowsWhatWouldGoOut(): void
    {
        $this->sync()->run($this->site());
        $site = ['https://example.com/a' => '2026-10-23', 'https://example.com/c' => ''] + $this->site();
        unset($site['https://example.com/b']);

        self::assertSame([['https://example.com/a', 'https://example.com/c'], ['https://example.com/b']], $this->sync()->pending($site));
    }

    public function testTheLogGetsOneLinePerNotice(): void
    {
        $log = new JsonLog($this->dir . '/indexnow.log');
        $this->sync($log)->run($this->site());
        $this->now += 3600;
        $this->transport->statuses = [429];
        $this->sync($log)->run(['https://example.com/new' => ''] + $this->site());

        $rows = $log->recent();
        self::assertCount(2, $rows);
        self::assertSame(429, $rows[0]['status']);
        self::assertSame(3, $rows[1]['count']);
    }
}
