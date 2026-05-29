<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Log;

use Generator;

use const PHP_INT_MAX;

/**
 * Streams the JSONL files {@see \Altair\Observability\Exporter\JsonLogExporter}
 * writes. Files are dated `YYYY-MM-DD.jsonl`; reading is newest-day-first so
 * `observability:tail` and `observability:stats` see the most recent activity
 * without sorting whole files.
 *
 * Each yielded row is the decoded JSON object with its `_kind` discriminator
 * intact (`span` | `metric`), so a consumer can branch by type without
 * re-parsing.
 */
final readonly class JsonLogReader
{
    public function __construct(private string $directory) {}

    /**
     * Yield rows newest-first (newest day first; within a day, newest line first).
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function rows(int $limit = PHP_INT_MAX): Generator
    {
        if (!is_dir($this->directory)) {
            return;
        }

        $files = glob($this->directory . '/*.jsonl') ?: [];
        rsort($files);

        $yielded = 0;
        foreach ($files as $file) {
            foreach ($this->linesReversed($file) as $line) {
                $decoded = json_decode($line, true);
                if (\is_array($decoded)) {
                    yield $decoded;
                    if (++$yielded >= $limit) {
                        return;
                    }
                }
            }
        }
    }

    /**
     * @return Generator<int, string>
     */
    private function linesReversed(string $file): Generator
    {
        $contents = (string) file_get_contents($file);
        $lines = array_reverse(array_values(array_filter(explode("\n", $contents), static fn(string $l): bool => $l !== '')));

        foreach ($lines as $line) {
            yield $line;
        }
    }
}
