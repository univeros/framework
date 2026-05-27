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
use Altair\Scaffold\Journal\Exception\EntryNotFoundException;
use Altair\Scaffold\Journal\Exception\JournalException;
use Altair\Scaffold\Journal\Journal;

/**
 * `bin/altair journal:diff <id>` — print the embedded diffs for one entry.
 *
 * Created files are printed as "+++ <path>" with full content; modified
 * files print their embedded unified-diff hunks.
 */
#[Command(
    name: 'journal:diff',
    description: 'Print the per-file diffs embedded in a journal entry.',
)]
final readonly class DiffCommand
{
    public function __construct(
        private Journal $journal,
    ) {}

    public function __invoke(
        #[Argument(description: 'Journal entry id (or unambiguous prefix).')]
        string $id,
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

        if ($entry->filesCreated === [] && $entry->filesModified === []) {
            echo "Entry '{$entry->id}' touched no files.\n";

            return 0;
        }

        foreach ($entry->filesCreated as $snapshot) {
            echo "+++ {$snapshot->path}  (created, sha256: {$snapshot->shaAfter}, {$snapshot->sizeBytes} bytes)\n";
        }

        foreach ($entry->filesModified as $snapshot) {
            echo \sprintf('~~~ %s%s', $snapshot->path, PHP_EOL);
            echo $snapshot->diff ?? "(no embedded diff)\n";
        }

        return 0;
    }
}
