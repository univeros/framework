<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Journal;

use Altair\Scaffold\Emitter\EmittedFile;
use Altair\Scaffold\Journal\Differ\FileDiffer;
use Altair\Scaffold\Writer\WriteOutcome;
use Altair\Scaffold\Writer\WriteStatus;

use const DIRECTORY_SEPARATOR;

/**
 * Builds {@see FileSnapshot} entries from the scaffolder's write
 * outcomes — keeps the journaling logic out of {@see ScaffoldCommand}
 * so that command stays focused on the write loop.
 *
 * Sequence inside the host command:
 *
 *     $before = $collector->captureBefore($file);   // before write
 *     $outcome = $writer->write($file, $force);     // existing write
 *     $collector->record($file, $outcome, $before); // after write
 *     ...
 *     [$created, $modified, $skipped] = $collector->snapshots();
 */
final class SnapshotCollector
{
    /** @var list<FileSnapshot> */
    private array $created = [];

    /** @var list<FileSnapshot> */
    private array $modified = [];

    /** @var list<string> */
    private array $skipped = [];

    public function __construct(
        private readonly string $projectRoot,
        private readonly FileDiffer $differ = new FileDiffer(),
    ) {}

    /**
     * Read the existing on-disk content for $file (if any) — must be
     * called BEFORE writing so we can capture the `content_before`
     * for modified files.
     */
    public function captureBefore(EmittedFile $file): ?string
    {
        $absolute = $this->absolute($file->relativePath);

        return is_file($absolute) ? (string) @file_get_contents($absolute) : null;
    }

    public function record(EmittedFile $file, WriteOutcome $outcome, ?string $contentBefore): void
    {
        $absolute = $this->absolute($file->relativePath);

        switch ($outcome->status) {
            case WriteStatus::Written:
                $sha = is_file($absolute) ? (string) hash_file('sha256', $absolute) : hash('sha256', $file->contents);
                $size = is_file($absolute) ? (int) filesize($absolute) : \strlen($file->contents);
                $this->created[] = FileSnapshot::created($file->relativePath, $sha, $size);
                break;
            case WriteStatus::Modified:
                $shaBefore = $contentBefore !== null ? hash('sha256', $contentBefore) : '';
                $shaAfter = is_file($absolute) ? (string) hash_file('sha256', $absolute) : hash('sha256', $file->contents);
                $afterContent = is_file($absolute) ? (string) @file_get_contents($absolute) : $file->contents;
                $diff = $contentBefore !== null
                    ? $this->differ->diff($contentBefore, $afterContent, $file->relativePath . ' (before)', $file->relativePath . ' (after)')
                    : '';
                $this->modified[] = FileSnapshot::modified(
                    path: $file->relativePath,
                    shaBefore: $shaBefore,
                    shaAfter: $shaAfter,
                    diff: $diff,
                    contentBefore: $contentBefore,
                );
                break;
            case WriteStatus::Skipped:
                $this->skipped[] = $file->relativePath;
                break;
        }
    }

    /**
     * @return list<FileSnapshot>
     */
    public function created(): array
    {
        return $this->created;
    }

    /**
     * @return list<FileSnapshot>
     */
    public function modified(): array
    {
        return $this->modified;
    }

    /**
     * @return list<string>
     */
    public function skipped(): array
    {
        return $this->skipped;
    }

    public function reset(): void
    {
        $this->created = [];
        $this->modified = [];
        $this->skipped = [];
    }

    private function absolute(string $relativePath): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\');
    }
}
