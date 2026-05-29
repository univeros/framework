<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Builder;

/**
 * A summary of one index build: how many files were scanned, how many were
 * actually re-walked versus skipped as unchanged (incremental), how many were
 * dropped because they no longer exist, and the resulting totals.
 */
final readonly class BuildResult
{
    public function __construct(
        public bool $incremental,
        public int $filesScanned,
        public int $filesIndexed,
        public int $filesSkipped,
        public int $filesRemoved,
        public int $symbolCount,
        public int $usageCount,
        public int $durationMs,
    ) {}

    /**
     * @return array{mode: string, files_scanned: int, files_indexed: int, files_skipped: int, files_removed: int, symbols: int, usages: int, duration_ms: int}
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->incremental ? 'incremental' : 'full',
            'files_scanned' => $this->filesScanned,
            'files_indexed' => $this->filesIndexed,
            'files_skipped' => $this->filesSkipped,
            'files_removed' => $this->filesRemoved,
            'symbols' => $this->symbolCount,
            'usages' => $this->usageCount,
            'duration_ms' => $this->durationMs,
        ];
    }
}
