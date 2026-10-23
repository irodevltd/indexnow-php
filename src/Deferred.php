<?php

declare(strict_types=1);

namespace Irodev\IndexNow;

/**
 * Runs a job after the page has gone to the visitor, at most once per interval: a cron-free way to keep the sync
 * going on any host. One file stat per request decides; the file's modification time is the last run.
 */
final class Deferred
{
    /**
     * Schedule $job for the end of this request if $every seconds have passed since the last time. On PHP-FPM and
     * LiteSpeed the response is finished first, so the visitor never waits; under other servers the job still runs,
     * just before the connection closes. Returns whether the job was scheduled.
     *
     * @param string   $stampFile a writable file outside the web root
     * @param callable $job       e.g. fn () => $sync->run(Sitemap::fromFile($path))
     */
    public static function every(int $every, string $stampFile, callable $job): bool
    {
        if (!self::due($every, $stampFile, time()) || !@touch($stampFile)) {
            return false;
        }
        register_shutdown_function(static function () use ($job): void {
            ignore_user_abort(true);
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();                       // the visitor's next request must not wait for the job
            }
            if (function_exists('litespeed_finish_request')) {
                \litespeed_finish_request();
            } elseif (function_exists('fastcgi_finish_request')) {
                \fastcgi_finish_request();
            }
            $job();
        });
        return true;
    }

    /** Has $every seconds passed since $stampFile was last touched (or does it not exist yet)? */
    public static function due(int $every, string $stampFile, int $now): bool
    {
        clearstatcache(true, $stampFile);
        $last = @filemtime($stampFile);
        return $last === false || $now - $last >= $every;
    }
}
