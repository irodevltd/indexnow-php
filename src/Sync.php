<?php

declare(strict_types=1);

namespace Irodev\IndexNow;

/**
 * Keeps IndexNow in step with your sitemap. Each run compares the current list (URL => <lastmod>) with what IndexNow
 * last accepted, kept in a small JSON file, and sends the difference: new URLs, re-dated URLs, and URLs that left the
 * list, so the engines recrawl them and see them gone.
 *
 * - One URL goes out at most once per $minInterval seconds (IndexNow asks for about ten minutes between notices for
 *   a page edited often). A change that comes sooner waits for a later run.
 * - After a refused or failed notice nothing goes out for $retryAfter seconds, unless the run is forced.
 * - An empty list while URLs are known is treated as a broken sitemap: nothing is sent.
 * - A lock file keeps two requests from sending the same notice.
 */
final class Sync
{
    /** @var callable(): int */
    private $clock;

    /** @var (callable(array<string, mixed>): void)|null */
    private $log;

    /**
     * @param string                                  $stateFile where the accepted list is kept (writable, not public)
     * @param (callable(array<string, mixed>): void)|null $log  gets one entry per notice sent (see JsonLog)
     * @param (callable(): int)|null                  $clock    the current Unix time (for tests)
     */
    public function __construct(
        private readonly Client $client,
        private readonly string $stateFile,
        private readonly int $minInterval = 600,
        private readonly int $retryAfter = 3600,
        ?callable $log = null,
        ?callable $clock = null,
    ) {
        $this->log = $log;
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * Send what changed since the last accepted notice.
     *
     * @param array<string, string>|null $entries URL => <lastmod>, e.g. from Sitemap::fromFile(); null (an unreadable
     *                                            sitemap) sends nothing
     * @param bool                       $force   skip the per-URL interval and the wait after a failure
     * @param bool                       $all     send every URL in the list, not only the changes
     */
    public function run(?array $entries, bool $force = false, bool $all = false): Outcome
    {
        if ($entries === null) {
            return new Outcome(Outcome::EMPTY_LIST);
        }
        $lock = @fopen($this->stateFile . '.lock', 'c');
        if ($lock === false) {
            throw new \RuntimeException('Could not open ' . $this->stateFile . '.lock; is the directory writable?');
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return new Outcome(Outcome::BUSY);
        }
        try {
            return $this->runLocked(array_filter($entries, fn ($_, $u) => $this->client->owns((string) $u), ARRAY_FILTER_USE_BOTH), $force, $all);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * What run() would send now: [new or re-dated URLs, URLs that left the list].
     *
     * @param array<string, string> $entries
     * @return array{0: list<string>, 1: list<string>}
     */
    public function pending(array $entries): array
    {
        $sent = $this->state()['sent'];
        $changed = array_keys(array_filter($entries, fn ($mod, $u) => ($sent[$u]['m'] ?? null) !== $mod, ARRAY_FILTER_USE_BOTH));
        return [array_map('strval', $changed), array_map('strval', array_keys(array_diff_key($sent, $entries)))];
    }

    /** @param array<string, string> $entries */
    private function runLocked(array $entries, bool $force, bool $all): Outcome
    {
        $now = ($this->clock)();
        $state = $this->state();
        if ($entries === [] && $state['sent'] !== []) {
            return new Outcome(Outcome::EMPTY_LIST);
        }
        [$changed, $gone] = $this->pending($entries);
        if ($all) {
            $changed = array_map('strval', array_keys($entries));
        }
        $todo = array_merge($changed, $gone);
        if ($todo === []) {
            return new Outcome(Outcome::NOTHING);
        }
        if (!$force && $now < $state['wait']) {
            return new Outcome(Outcome::WAITING, later: count($todo), retryAt: $state['wait']);
        }
        $ready = fn (string $u): int => (int) ($state['sent'][$u]['t'] ?? 0) + $this->minInterval;
        $send = $force ? $todo : array_values(array_filter($todo, fn (string $u) => $ready($u) <= $now));
        $later = count($todo) - count($send);
        if ($send === []) {
            return new Outcome(Outcome::WAITING, later: $later, retryAt: min(array_map($ready, $todo)));
        }

        $result = $this->client->submit($send);
        if ($this->log !== null) {
            ($this->log)(['time' => gmdate('c', $now), 'status' => $result->status, 'count' => count($send), 'urls' => $send]);
        }
        if ($result->accepted()) {
            foreach ($send as $u) {
                if (isset($entries[$u])) {
                    $state['sent'][$u] = ['m' => $entries[$u], 't' => $now];
                } else {
                    unset($state['sent'][$u]);
                }
            }
            $state['wait'] = 0;
        } else {
            $state['wait'] = $now + $this->retryAfter;
        }
        $this->save($state);
        return new Outcome($result->accepted() ? Outcome::SENT : Outcome::FAILED, $result, $send, $later,
            $result->accepted() ? null : $state['wait']);
    }

    /** @return array{sent: array<string, array{m: string, t: int}>, wait: int} */
    private function state(): array
    {
        $raw = is_file($this->stateFile) ? file_get_contents($this->stateFile) : false;
        $data = $raw === false ? null : json_decode($raw, true);
        return [
            'sent' => is_array($data['sent'] ?? null) ? $data['sent'] : [],
            'wait' => (int) ($data['wait'] ?? 0),
        ];
    }

    /** @param array{sent: array<string, array{m: string, t: int}>, wait: int} $state */
    private function save(array $state): void
    {
        $tmp = $this->stateFile . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $json = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        if (file_put_contents($tmp, $json) === false || !rename($tmp, $this->stateFile)) {
            @unlink($tmp);
            throw new \RuntimeException('Could not write ' . $this->stateFile);
        }
    }
}
