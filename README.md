# IndexNow for PHP

[![tests](https://github.com/irodevltd/indexnow-php/actions/workflows/tests.yml/badge.svg)](https://github.com/irodevltd/indexnow-php/actions/workflows/tests.yml)
![PHP](https://img.shields.io/badge/php-%3E%3D8.1-777bb4)
![License](https://img.shields.io/badge/license-MIT-blue)

**English** · [Türkçe](README.tr.md)

Tell Bing, Yandex, Seznam, Naver and Yep the moment a page is added, changed or removed, straight from your
sitemap. No cron job, no plugin, no external service: a few classes and a JSON file.

- **Automatic:** compares your sitemap with what IndexNow last accepted and sends only the difference.
- **Polite:** one notice per URL per 10 minutes, an hour's pause after a refusal, and a lock against double sends.
- **Safe:** a sitemap that can't be read (half uploaded, say) never makes your pages look removed.
- **Visitor-friendly:** the check runs after the page has gone to the visitor, at most once every 10 minutes.

It is the IndexNow integration that runs on [irodev.net](https://irodev.net), made standalone.

## Requirements

PHP 8.1 or newer with the `json` and `simplexml` extensions, and `curl` for the default transport.

## Installation

```bash
composer require irodevnet/indexnow
```

## 1. Create your key

IndexNow checks a key against a text file at your site root. Generate both once:

```bash
php vendor/irodevnet/indexnow/examples/create-key.php /path/to/document-root
```

or in code:

```php
use Irodev\IndexNow\Key;

$key = Key::generate();                  // 32 hexadecimal characters
Key::writeFile('/path/to/document-root', $key);
```

The file must stay reachable at `https://your-host/<key>.txt`. The key is public by design.

## 2. Send URLs

```php
use Irodev\IndexNow\Client;

$client = new Client('example.com', $key);
$result = $client->submit(['https://example.com/blog/new-post', 'https://example.com/blog']);

if (!$result->accepted()) {
    error_log('IndexNow: ' . $result->reason());
}
```

`Client` drops duplicates and URLs on other hosts (IndexNow refuses them). The host must match your URLs exactly:
`example.com` and `www.example.com` are different hosts.

## 3. Keep it automatic

Call this from the code that renders your pages, a front controller or a shared header:

```php
use Irodev\IndexNow\{Client, Deferred, JsonLog, Sitemap, Sync};

if (($_SERVER['HTTP_HOST'] ?? '') === 'example.com') {     // only the live site sends
    Deferred::every(600, $storage . '/indexnow.tick', function () use ($storage, $docroot, $key) {
        $sync = new Sync(new Client('example.com', $key), $storage . '/indexnow.json',
                         log: new JsonLog($storage . '/indexnow.log'));
        $sync->run(Sitemap::merge(
            Sitemap::fromFile($docroot . '/sitemap.xml'),
            Sitemap::fromFile($docroot . '/sitemap-blog.xml'),
        ));
    });
}
```

`Deferred` costs one file check per request. When it runs, PHP-FPM and LiteSpeed first hand the finished page to
the visitor, so nobody waits for the notice. Keep `$storage` outside the web root.

When an editor saves or deletes content, you can also call `$sync->run(...)` right away.

## How the sync decides

| Situation | What happens |
| --- | --- |
| A URL is new, or its `<lastmod>` changed | It is sent |
| A URL left the sitemap | It is sent once, so the engines recrawl it and see it gone |
| The same URL was sent less than `$minInterval` ago (600 s) | It waits for a later run (`Outcome::WAITING`) |
| The last notice was refused or got no answer | Nothing is sent for `$retryAfter` (3600 s) unless you pass `force: true` |
| The sitemap can't be read, or is empty while URLs are known | Nothing is sent (`Outcome::EMPTY_LIST`) |
| Another request is already sending | `Outcome::BUSY` |

`run($entries, force: true, all: true)` resends every URL, for example after a redesign. IndexNow asks you to send
only changed URLs, so keep it for real site-wide changes. `$sync->pending($entries)` shows what would go out now.

Because the comparison uses `<lastmod>`, keep those dates honest: change a page's date when its content changes, and
only then. Dates with a time (`2026-10-23T21:30:00+03:00`) let a page go out again the same day.

## IndexNow answers

| Status | Meaning |
| --- | --- |
| 200 | Received |
| 202 | Received; the key is still being validated |
| 400 | Invalid format |
| 403 | Key file missing at the key location, or it holds a different key |
| 422 | URLs on another host, or the key doesn't match the protocol's format |
| 429 | Too many requests |
| 0 | No answer (DNS, timeout, refused connection) |

`$result->reason()` returns the same explanation as text.

## Good to know

- Google does not take part in IndexNow; it reads your sitemap and Search Console. One notice to the shared endpoint
  reaches every participating engine.
- Use one `Client` per host. A site served on `www` and without it should redirect one to the other anyway.
- `Transport` is an interface: pass your own to `Client` to use a proxy or an HTTP client you already have.
- `JsonLog::recent()` gives the newest notices for an admin page.

## Testing

```bash
composer install
composer test
```

## License

MIT © [İRODEV LTD](https://irodev.net). Built by İrodev, a web and software studio for businesses in Turkey and the
UK. If you'd like this set up on your site, [get in touch](https://irodev.net/iletisim).
