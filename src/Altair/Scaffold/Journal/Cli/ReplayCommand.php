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
use Altair\Scaffold\Emitter\EmissionPlan;
use Altair\Scaffold\Journal\Exception\EntryNotFoundException;
use Altair\Scaffold\Journal\Exception\JournalException;
use Altair\Scaffold\Journal\Journal;
use Altair\Scaffold\Journal\JournalEntry;
use Altair\Scaffold\Spec\Parser;
use Altair\Scaffold\Spec\Validator;
use Altair\Scaffold\Writer\FileWriter;

use const STDIN;

use Throwable;

/**
 * `bin/altair journal:replay [--from=<id>|--all] [<id>]` — re-emit one
 * or more entries from the embedded spec content. Drift is reported
 * (file content now differs from what the journal recorded → usually
 * means the scaffolder itself changed).
 *
 * Replay reads the spec content out of the entry's `spec.content_inline`
 * — it never touches the original spec file (which may have been
 * edited or deleted since the original scaffold).
 */
#[Command(
    name: 'journal:replay',
    description: 'Re-apply scaffold operations from the journal (single, from-point, or all).',
)]
final readonly class ReplayCommand
{
    public function __construct(
        private Journal $journal,
        private Parser $parser = new Parser(),
        private Validator $validator = new Validator(),
        private EmissionPlan $plan = new EmissionPlan(),
    ) {}

    public function __invoke(
        #[Option(description: 'Replay a single entry by id (or unambiguous prefix).')]
        ?string $id = null,
        #[Option(description: 'Replay every entry from this id forward (inclusive).')]
        ?string $from = null,
        #[Option(description: 'Replay the whole journal start-to-end. Confirms before running.')]
        bool $all = false,
        #[Option(description: 'Overwrite existing files instead of skipping.')]
        bool $force = false,
    ): int {
        try {
            $entries = $this->resolveEntries($id, $from, $all);
        } catch (EntryNotFoundException $e) {
            echo $e->getMessage(), "\n";

            return 1;
        } catch (JournalException $e) {
            echo $e->getMessage(), "\n";

            return 2;
        }

        if ($entries === []) {
            echo "No entries to replay.\n";

            return 0;
        }

        if ($all) {
            echo "About to replay " . \count($entries) . " entry(ies). Continue? [y/N] ";
            $line = trim((string) fgets(STDIN));
            if (strtolower($line) !== 'y') {
                echo "Aborted.\n";

                return 0;
            }
        }

        $writer = new FileWriter($this->journal->projectRoot());
        $drift = [];
        $written = 0;
        $skipped = 0;

        foreach ($entries as $entry) {
            $specPath = $entry->spec['path'];
            try {
                $parsed = $this->parser->parseString($entry->spec['content_inline'], $specPath);
                $this->validator->assertValid($parsed);
                $spec = $parsed;
            } catch (Throwable $throwable) {
                echo \sprintf('Skipping %s: spec is no longer parseable — %s%s', $entry->id, $throwable->getMessage(), PHP_EOL);

                continue;
            }

            foreach ($this->plan->build($spec) as $file) {
                $outcome = $writer->write($file, $force);
                echo $outcome->status->value, ' ', $outcome->relativePath, "\n";

                if ($outcome->status->value === 'skipped') {
                    $skipped++;
                } else {
                    $written++;
                }

                $expectedSha = $this->expectedShaFor($entry, $file->relativePath);
                $actualSha = hash('sha256', $file->contents);
                if ($expectedSha !== null && $expectedSha !== $actualSha) {
                    $drift[] = ['entry' => $entry->id, 'path' => $file->relativePath, 'expected' => $expectedSha, 'actual' => $actualSha];
                }
            }
        }

        echo "Replayed " . \count($entries) . " entry(ies). Wrote {$written}, skipped {$skipped} existing file(s).\n";

        if ($drift !== []) {
            echo \count($drift), " file(s) drifted from journal:\n";
            foreach ($drift as $d) {
                echo "  - {$d['path']}  (entry {$d['entry']})\n";
            }

            return 1;
        }

        return 0;
    }

    /**
     * @return list<JournalEntry>
     */
    private function resolveEntries(?string $id, ?string $from, bool $all): array
    {
        if ($all) {
            return iterator_to_array($this->journal->history(), false);
        }

        if ($from !== null) {
            $start = $this->journal->findById($from);
            $out = [];
            $started = false;
            foreach ($this->journal->history() as $entry) {
                if ($entry->id === $start->id) {
                    $started = true;
                }

                if ($started) {
                    $out[] = $entry;
                }
            }

            return $out;
        }

        if ($id !== null) {
            return [$this->journal->findById($id)];
        }

        return [];
    }

    private function expectedShaFor(JournalEntry $entry, string $relativePath): ?string
    {
        foreach ($entry->filesCreated as $snapshot) {
            if ($snapshot->path === $relativePath) {
                return $snapshot->shaAfter;
            }
        }

        foreach ($entry->filesModified as $snapshot) {
            if ($snapshot->path === $relativePath) {
                return $snapshot->shaAfter;
            }
        }

        return null;
    }
}
