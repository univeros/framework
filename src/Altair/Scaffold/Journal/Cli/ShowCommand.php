<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Journal\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Scaffold\Journal\Exception\EntryNotFoundException;
use Altair\Scaffold\Journal\Exception\JournalException;
use Altair\Scaffold\Journal\Journal;
use DateTimeInterface;

/**
 * `bin/altair journal:show <id>` — full detail for one journal entry.
 */
#[Command(
    name: 'journal:show',
    description: 'Show one journal entry by id (or unambiguous prefix).',
)]
final readonly class ShowCommand
{
    public function __construct(
        private Journal $journal,
    ) {}

    public function __invoke(
        #[Argument(description: 'Journal entry id (or unambiguous prefix).')]
        string $id,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        try {
            $entry = $this->journal->findById($id);
        } catch (EntryNotFoundException $e) {
            echo $e->getMessage(), "\n";

            return 1;
        } catch (JournalException $e) {
            echo $e->getMessage(), "\n";

            return 2;
        }

        if ($format === 'json') {
            echo $entry->toJson(), "\n";

            return 0;
        }

        echo \sprintf('id:        %s%s', $entry->id, PHP_EOL);
        echo \sprintf('operation: %s%s', $entry->operation->value, PHP_EOL);
        echo \sprintf('timestamp: %s%s', $entry->timestamp->format(DateTimeInterface::RFC3339_EXTENDED), PHP_EOL);
        echo \sprintf('command:   %s%s', $entry->command, PHP_EOL);
        echo "spec:      {$entry->spec['path']}  (sha256: {$entry->spec['sha256']})\n";
        if ($entry->isReverted()) {
            echo "reverted:  " . $entry->revertedAt?->format(DateTimeInterface::RFC3339_EXTENDED) . "\n";
        }

        if ($entry->filesCreated !== []) {
            echo "created:\n";
            foreach ($entry->filesCreated as $snapshot) {
                echo "  - {$snapshot->path}  ({$snapshot->sizeBytes} bytes)\n";
            }
        }

        if ($entry->filesModified !== []) {
            echo "modified:\n";
            foreach ($entry->filesModified as $snapshot) {
                echo "  - {$snapshot->path}  ({$snapshot->shaBefore} → {$snapshot->shaAfter})\n";
            }
        }

        if ($entry->filesSkipped !== []) {
            echo "skipped:\n";
            foreach ($entry->filesSkipped as $path) {
                echo \sprintf('  - %s%s', $path, PHP_EOL);
            }
        }

        return 0;
    }
}
