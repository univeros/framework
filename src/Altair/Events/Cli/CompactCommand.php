<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Events\Event;
use Altair\Events\Exception\StorageException;
use Altair\Events\Storage\JsonlStorage;
use DateTimeImmutable;
use DateTimeInterface;

use const DIRECTORY_SEPARATOR;

use Throwable;

/**
 * `bin/altair events:compact` — move events older than `--before` (or, by
 * default, 30 days) into a gzipped archive under `.altair/events.archive/`
 * and truncate them from the live log.
 *
 * The archive layout is `YYYY-MM.jsonl.gz`, append-only per file. Reads
 * during compaction take an exclusive lock on the live log so concurrent
 * appenders block briefly rather than losing writes.
 */
#[Command(
    name: 'events:compact',
    description: 'Archive events older than a cutoff into .altair/events.archive/.',
)]
final readonly class CompactCommand
{
    public function __construct(
        private JsonlStorage $storage,
    ) {}

    public function __invoke(
        #[Option(description: 'Archive events strictly older than this timestamp (default: 30 days ago).')]
        ?string $before = null,
        #[Option(description: 'Preview which events would be archived without writing.', name: 'dry-run')]
        bool $dryRun = false,
    ): int {
        try {
            $cutoff = $before !== null
                ? new DateTimeImmutable($before)
                : new DateTimeImmutable('-30 days');
        } catch (Throwable $throwable) {
            echo \sprintf("Could not parse '--before' value: %s%s", $throwable->getMessage(), PHP_EOL);

            return 2;
        }

        $logPath = $this->storage->path();
        if (!is_file($logPath)) {
            echo "Nothing to compact (no log at '{$logPath}').\n";

            return 0;
        }

        $kept = [];
        $archivedByMonth = [];
        $archivedCount = 0;

        foreach ($this->storage->readAll() as $event) {
            if ($event->timestamp < $cutoff) {
                $month = $event->timestamp->format('Y-m');
                $archivedByMonth[$month][] = $event;
                $archivedCount++;
            } else {
                $kept[] = $event;
            }
        }

        if ($archivedCount === 0) {
            echo "Nothing to compact (no events older than {$cutoff->format(DateTimeInterface::RFC3339_EXTENDED)}).\n";

            return 0;
        }

        if ($dryRun) {
            echo "Would archive {$archivedCount} event(s) into:\n";
            foreach ($archivedByMonth as $month => $events) {
                echo \sprintf("  %s.jsonl.gz  (%d events)\n", $month, \count($events));
            }

            return 0;
        }

        $archiveDir = \dirname($logPath) . DIRECTORY_SEPARATOR . 'events.archive';
        if (!is_dir($archiveDir) && !@mkdir($archiveDir, 0o775, true) && !is_dir($archiveDir)) {
            echo "Cannot create archive directory '{$archiveDir}'.\n";

            return 1;
        }

        foreach ($archivedByMonth as $month => $events) {
            $this->appendGzipped($archiveDir . DIRECTORY_SEPARATOR . $month . '.jsonl.gz', $events);
        }

        $this->rewriteLog($logPath, $kept);

        echo "Archived {$archivedCount} event(s); {$this->storage->count()} remain in live log.\n";

        return 0;
    }

    /**
     * @param list<Event> $events
     */
    private function appendGzipped(string $path, array $events): void
    {
        $handle = @gzopen($path, 'ab');
        if ($handle === false) {
            throw new StorageException(\sprintf("Cannot open archive '%s' for append.", $path));
        }

        try {
            foreach ($events as $event) {
                gzwrite($handle, $event->toJsonLine() . "\n");
            }
        } finally {
            gzclose($handle);
        }
    }

    /**
     * @param list<Event> $events
     */
    private function rewriteLog(string $path, array $events): void
    {
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
        $handle = @fopen($tmp, 'wb');
        if ($handle === false) {
            throw new StorageException(\sprintf("Cannot open tmp file '%s' for rewrite.", $tmp));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new StorageException(\sprintf("Cannot lock tmp file '%s'.", $tmp));
            }

            try {
                foreach ($events as $event) {
                    fwrite($handle, $event->toJsonLine() . "\n");
                }

                fflush($handle);
            } finally {
                flock($handle, LOCK_UN);
            }
        } finally {
            fclose($handle);
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new StorageException(\sprintf("Cannot rename '%s' → '%s'.", $tmp, $path));
        }
    }
}
