<?php

declare(strict_types=1);

namespace Irodev\IndexNow\Tests;

use Irodev\IndexNow\Deferred;
use Irodev\IndexNow\JsonLog;
use PHPUnit\Framework\TestCase;

final class DeferredTest extends TestCase
{
    public function testIsDueWithoutAStampAndAfterTheInterval(): void
    {
        $stamp = sys_get_temp_dir() . '/indexnow-stamp-' . bin2hex(random_bytes(4));
        try {
            self::assertTrue(Deferred::due(600, $stamp, time()));
            touch($stamp, time() - 120);
            self::assertFalse(Deferred::due(600, $stamp, time()));
            touch($stamp, time() - 601);
            self::assertTrue(Deferred::due(600, $stamp, time()));
        } finally {
            @unlink($stamp);
        }
    }

    public function testTheLogKeepsOnlyTheNewestLinesWhenItGrows(): void
    {
        $file = sys_get_temp_dir() . '/indexnow-log-' . bin2hex(random_bytes(4));
        try {
            $log = new JsonLog($file, maxBytes: 300, keepLines: 3);
            foreach (range(1, 20) as $i) {
                $log(['n' => $i, 'pad' => str_repeat('x', 20)]);
            }
            $rows = $log->recent(100);
            self::assertLessThanOrEqual(5, count($rows));
            self::assertSame(20, $rows[0]['n']);
        } finally {
            @unlink($file);
        }
    }
}
