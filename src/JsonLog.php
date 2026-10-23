<?php

declare(strict_types=1);

namespace Irodev\IndexNow;

/**
 * A small log for Sync: one JSON line per notice. Past $maxBytes only the newest $keepLines lines are kept. Pass it
 * as Sync's $log and read it back with recent(), for an admin page say. Keep the file out of the web root.
 */
final class JsonLog
{
    public function __construct(
        private readonly string $file,
        private readonly int $maxBytes = 262144,
        private readonly int $keepLines = 50,
    ) {
    }

    /** @param array<string, mixed> $entry */
    public function __invoke(array $entry): void
    {
        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        clearstatcache(true, $this->file);
        if (is_file($this->file) && filesize($this->file) > $this->maxBytes) {
            $lines = array_slice(file($this->file) ?: [], -$this->keepLines);
            file_put_contents($this->file, implode('', $lines) . $line, LOCK_EX);
            return;
        }
        file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * The newest entries first.
     *
     * @return list<array<string, mixed>>
     */
    public function recent(int $n = 10): array
    {
        $lines = is_file($this->file) ? (file($this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [];
        $rows = array_map(static fn (string $l) => json_decode($l, true), array_slice($lines, -$n));
        return array_reverse(array_values(array_filter($rows, 'is_array')));
    }
}
