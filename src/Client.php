<?php

declare(strict_types=1);

namespace Irodev\IndexNow;

use Irodev\IndexNow\Transport\CurlTransport;
use Irodev\IndexNow\Transport\Transport;

/**
 * Sends URLs to IndexNow. The shared endpoint passes every notice on to all participating search engines (Bing,
 * Yandex, Seznam, Naver, Yep). Google does not take part: it reads your sitemap.
 */
final class Client
{
    public const ENDPOINT = 'https://api.indexnow.org/indexnow';

    /** The most URLs one notice may carry. */
    public const MAX_URLS = 10000;

    private readonly string $host;
    private readonly Transport $transport;

    /**
     * @param string      $host        the site's host exactly as its URLs use it: "example.com" and "www.example.com" differ
     * @param string|null $keyLocation where the key file is served; by default https://<host>/<key>.txt
     */
    public function __construct(
        string $host,
        private readonly string $key,
        private readonly ?string $keyLocation = null,
        ?Transport $transport = null,
        private readonly string $endpoint = self::ENDPOINT,
    ) {
        if (!Key::isValid($key)) {
            throw new \InvalidArgumentException('An IndexNow key is 8–128 characters of a–z, A–Z, 0–9 and "-".');
        }
        $this->host = strtolower($host);
        $this->transport = $transport ?? new CurlTransport();
    }

    public function host(): string
    {
        return $this->host;
    }

    public function keyLocation(): string
    {
        return $this->keyLocation ?? 'https://' . $this->host . '/' . Key::fileName($this->key);
    }

    /** Is this an absolute http(s) URL on this client's host? IndexNow refuses other hosts' URLs (HTTP 422). */
    public function owns(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && strtolower((string) ($parts['host'] ?? '')) === $this->host;
    }

    /**
     * Send URLs to IndexNow. Duplicates and other hosts' URLs are dropped first.
     *
     * @param list<string> $urls at most 10,000 (split bigger lists with array_chunk())
     */
    public function submit(array $urls): Result
    {
        $urls = array_values(array_unique(array_filter($urls, fn ($u) => is_string($u) && $this->owns($u))));
        if ($urls === []) {
            throw new \InvalidArgumentException('No URLs on ' . $this->host . ' to send.');
        }
        if (count($urls) > self::MAX_URLS) {
            throw new \LengthException('IndexNow takes at most ' . self::MAX_URLS . ' URLs per notice; split the list.');
        }
        $body = json_encode(
            ['host' => $this->host, 'key' => $this->key, 'keyLocation' => $this->keyLocation(), 'urlList' => $urls],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        return new Result($this->transport->post($this->endpoint, $body), $urls);
    }
}
