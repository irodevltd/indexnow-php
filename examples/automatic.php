<?php

declare(strict_types=1);

// Automatic notices without cron: include this from the code that renders your pages (a front controller, a
// shared header). At most every 10 minutes, after the page has gone to the visitor, the sitemap is compared with
// what IndexNow last accepted and the difference is sent.

require __DIR__ . '/../vendor/autoload.php';

use Irodev\IndexNow\Client;
use Irodev\IndexNow\Deferred;
use Irodev\IndexNow\JsonLog;
use Irodev\IndexNow\Sitemap;
use Irodev\IndexNow\Sync;

$storage = '/var/www/example.com/storage';           // writable, outside the web root
$docroot = '/var/www/example.com/public';

// Only the live site should send: a local or staging copy would announce pages it doesn't serve.
if (($_SERVER['HTTP_HOST'] ?? '') === 'example.com') {
    Deferred::every(600, $storage . '/indexnow.tick', static function () use ($storage, $docroot): void {
        $client = new Client('example.com', 'your-indexnow-key');
        $sync = new Sync($client, $storage . '/indexnow.json', log: new JsonLog($storage . '/indexnow.log'));
        $sync->run(Sitemap::merge(
            Sitemap::fromFile($docroot . '/sitemap.xml'),
            Sitemap::fromFile($docroot . '/sitemap-blog.xml'),
        ));
    });
}

// When an editor saves or deletes a post you can run the same sync at once; the per-URL interval still applies:
// $outcome = $sync->run($entries);
// if ($outcome->status === Outcome::WAITING) { /* goes out with a later page view, after $outcome->retryAt */ }
