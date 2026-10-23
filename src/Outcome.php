<?php

declare(strict_types=1);

namespace Irodev\IndexNow;

/** What one Sync::run() did. */
final class Outcome
{
    /** IndexNow accepted the notice. */
    public const SENT = 'sent';
    /** The notice was refused or got no answer; see $result and $retryAt. */
    public const FAILED = 'failed';
    /** Everything in the list was already accepted. */
    public const NOTHING = 'nothing';
    /** There are changes, but none may go out yet; see $retryAt. */
    public const WAITING = 'waiting';
    /** Another request is running the sync right now. */
    public const BUSY = 'busy';
    /** The list was empty while earlier URLs are known: treated as a broken sitemap, nothing sent. */
    public const EMPTY_LIST = 'empty-list';

    /**
     * @param list<string> $sent  the URLs in the notice
     * @param int          $later changes kept for a later run (the per-URL interval hadn't passed)
     */
    public function __construct(
        public readonly string $status,
        public readonly ?Result $result = null,
        public readonly array $sent = [],
        public readonly int $later = 0,
        public readonly ?int $retryAt = null,
    ) {
    }
}
