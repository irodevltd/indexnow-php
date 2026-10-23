<?php

declare(strict_types=1);

namespace Irodev\IndexNow\Transport;

/** The default transport: one HTTPS POST with cURL, certificates verified. */
final class CurlTransport implements Transport
{
    /**
     * @param string|null $caFile a CA bundle (PEM) for hosts whose PHP has none configured
     */
    public function __construct(
        private readonly int $timeout = 15,
        private readonly int $connectTimeout = 8,
        private readonly ?string $caFile = null,
        private readonly string $userAgent = 'irodevnet/indexnow (+https://github.com/irodevltd/indexnow-php)',
    ) {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('CurlTransport needs the curl extension; pass another Transport to the Client.');
        }
    }

    public function post(string $url, string $json): int
    {
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
        ];
        if ($this->caFile !== null) {
            $options[CURLOPT_CAINFO] = $this->caFile;
        }
        curl_setopt_array($ch, $options);
        return curl_exec($ch) !== false ? (int) curl_getinfo($ch, CURLINFO_HTTP_CODE) : 0;
    }
}
