<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Journal\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Scaffold\Journal\Exception\EntryNotFoundException;
use Altair\Scaffold\Journal\Exception\JournalException;
use Altair\Scaffold\Journal\Exception\RewindRefusedException;
use Altair\Scaffold\Journal\Journal;
use Altair\Scaffold\Journal\JournalEntry;

/**
 * `bin/altair journal:rewind [--to=<id>] [--dry-run] [--force]` — undo
 * scaffold operations, newest-first, by re-deleting created files and
 * restoring modified files from the embedded `content_before`.
 *
 * Refuses to clobber files the user has hand-edited since the original
 * scaffold (sha mismatch). `--force` overrides with a clear warning.
 */
#[Command(
    name: 'journal:rewind',
    description: 'Undo scaffold operations newest-first (single entry by default).',
)]
final readonly class RewindCommand
{
    public function __construct(
        private Journal $journal,
    ) {}

    public function __invoke(
        #[Option(description: 'Undo every entry up to and including this id (inclusive). Defaults to the most recent entry.')]
        ?string $to = null,
        #[Option(description: 'Print what would be undone without writing.', name: 'dry-run')]
        bool $dryRun = false,
        #[Option(description: 'Override the hand-edit safety check.')]
        bool $force = false,
    ): int {
        try {
            $targets = $this->collectTargets($to);
        } catch (EntryNotFoundException $e) {
            echo $e->getMessage(), "\n";

            return 1;
        } catch (JournalException $e) {
            echo $e->getMessage(), "\n";

            return 2;
        }

        if ($targets === []) {
            echo "No entries to rewind.\n";

            return 0;
        }

        if ($dryRun) {
            echo "Would rewind " . \count($targets) . " entry(ies):\n";
            foreach ($targets as $entry) {
                echo "  - {$entry->id}  ({$entry->spec['path']})\n";
            }

            return 0;
        }

        $totalDeleted = 0;
        $totalRestored = 0;
        $totalSkipped = 0;
        foreach ($targets as $entry) {
            try {
                $result = $this->journal->rewind($entry, $force);
            } catch (RewindRefusedException $e) {
                echo $e->getMessage(), "\n";

                return 3;
            }

            echo \sprintf('Rewound %s — deleted: ', $entry->id) . \count($result['deleted'])
                . ", restored: " . \count($result['restored'])
                . ", skipped: " . \count($result['skipped']) . "\n";
            $totalDeleted += \count($result['deleted']);
            $totalRestored += \count($result['restored']);
            $totalSkipped += \count($result['skipped']);
        }

        echo "Done — deleted {$totalDeleted}, restored {$totalRestored}, skipped {$totalSkipped}.\n";

        return 0;
    }

    /**
     * @return list<JournalEntry> Newest-first list of entries to undo.
     */
    private function collectTargets(?string $to): array
    {
        if ($to !== null) {
            $stopAt = $this->journal->findById($to);
            $out = [];
            foreach ($this->journal->tail() as $entry) {
                if ($entry->isReverted()) {
                    continue;
                }

                $out[] = $entry;
                if ($entry->id === $stopAt->id) {
                    return $out;
                }
            }

            // We walked the entire log without hitting `$to`.
            return $out;
        }

        $latest = null;
        foreach ($this->journal->tail(1) as $entry) {
            if (!$entry->isReverted()) {
                $latest = $entry;
            }
        }

        return $latest === null ? [] : [$latest];
    }
}
