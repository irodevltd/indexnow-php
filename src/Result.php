<?php

declare(strict_types=1);

namespace Irodev\IndexNow;

/** IndexNow's answer to one notice. */
final class Result
{
    /**
     * @param int          $status the HTTP status, 0 when no answer came
     * @param list<string> $urls   the addresses that were sent
     */
    public function __construct(
        public readonly int $status,
        public readonly array $urls,
    ) {
    }

    /** 200 (received) and 202 (received, the key is still being checked) mean the engines have the URLs. */
    public function accepted(): bool
    {
        return $this->status === 200 || $this->status === 202;
    }

    /** What the status means, in the words of the IndexNow documentation. */
    public function reason(): string
    {
        return match ($this->status) {
            200 => 'OK: the URLs were received.',
            202 => 'Accepted: the URLs were received; the key is being validated.',
            400 => 'Bad request: the request is not in a valid format.',
            403 => 'Forbidden: the key file was not found at the key location, or it holds a different key.',
            422 => 'Unprocessable: the URLs belong to another host, or the key does not match the protocol\'s format.',
            429 => 'Too many requests: possible spam. Send less often.',
            0 => 'No answer: the request did not reach IndexNow (DNS, timeout or refused connection).',
            default => 'Unexpected answer: HTTP ' . $this->status . '.',
        };
    }
}
