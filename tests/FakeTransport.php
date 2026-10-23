<?php

declare(strict_types=1);

namespace Irodev\IndexNow\Tests;

use Irodev\IndexNow\Transport\Transport;

/** Records each notice and answers with the next queued status (200 when the queue is empty). */
final class FakeTransport implements Transport
{
    /** @var list<array{url: string, body: array<string, mixed>}> */
    public array $calls = [];

    /** @param list<int> $statuses */
    public function __construct(public array $statuses = [])
    {
    }

    public function post(string $url, string $json): int
    {
        $this->calls[] = ['url' => $url, 'body' => json_decode($json, true, flags: JSON_THROW_ON_ERROR)];
        return array_shift($this->statuses) ?? 200;
    }

    /** @return list<string> the URLs of the last notice */
    public function lastUrls(): array
    {
        return $this->calls === [] ? [] : $this->calls[array_key_last($this->calls)]['body']['urlList'];
    }
}
