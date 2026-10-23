<?php

declare(strict_types=1);

namespace Irodev\IndexNow\Transport;

/** How a notice reaches IndexNow. Swap it in tests, or for a proxy or an HTTP client you already use. */
interface Transport
{
    /** POST a JSON body. Returns the HTTP status, or 0 when no answer came (DNS, timeout, refused connection). */
    public function post(string $url, string $json): int;
}
