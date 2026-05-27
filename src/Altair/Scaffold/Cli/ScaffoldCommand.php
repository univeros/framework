<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Events\Actor;
use Altair\Events\Changes;
use Altair\Events\Contracts\RecorderInterface;
use Altair\Events\Event as RecorderEvent;
use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Altair\Scaffold\Emitter\EmissionPlan;
use Altair\Scaffold\Journal\Journal;
use Altair\Scaffold\Journal\JournalEntry;
use Altair\Scaffold\Journal\SnapshotCollector;
use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Scaffold\Spec\SpecLoader;
use Altair\Scaffold\Writer\FileWriter;
use Altair\Scaffold\Writer\WriteStatus;
use Throwable;

/**
 * `bin/altair spec:scaffold <path>` — read a YAML endpoint spec (or a
 * directory of specs) and emit the action/input/responder/domain
 * stub/test/openapi/route files.
 *
 * **Journal-aware:** when a {@see Journal} is bound in the container,
 * every successful scaffold writes a `.altair/journal/<id>.json` entry
 * (one per spec). The journal lets `bin/altair journal:rewind` and
 * `journal:replay` undo / re-apply scaffold operations after the fact.
 *
 * **Event-aware:** when a {@see RecorderInterface} is bound, each
 * scaffold (and its overall success/failure) emits a mutation event
 * into the `.altair/events.jsonl` log so agents have a chronological
 * record of "what just changed?" across sessions.
 *
 * Both integrations are optional — the command works standalone when
 * either dependency is absent.
 */
#[Command(
    name: 'spec:scaffold',
    description: 'Emit Action / Input / Responder / Test / OpenAPI files from an endpoint spec.',
)]
final readonly class ScaffoldCommand
{
    public function __construct(
        private SpecLoader $loader = new SpecLoader(),
        private EmissionPlan $plan = new EmissionPlan(),
        private PathResolver $paths = new PathResolver(),
        private ?Journal $journal = null,
        private ?RecorderInterface $events = null,
    ) {}

    public function __invoke(
        #[Argument(description: 'Spec file or directory containing YAML specs.')]
        string $path,
        #[Option(description: 'Print planned files without writing.', name: 'dry-run')]
        bool $dryRun = false,
        #[Option(description: 'Overwrite existing files instead of skipping.')]
        bool $force = false,
        #[Option(description: 'Override the project root used as base for emitted paths.')]
        ?string $root = null,
    ): int {
        $projectRoot = $this->paths->resolveProjectRoot($root);
        $writer = new FileWriter($projectRoot);

        try {
            $specs = $this->loader->load($path);
        } catch (Throwable $throwable) {
            $this->recordEvent(EventStatus::Fail, $path, 0, null, $throwable->getMessage());
            echo \sprintf('Spec load failed: %s%s', $throwable->getMessage(), PHP_EOL);

            return 1;
        }

        $totalWritten = 0;
        $totalSkipped = 0;
        $startedAt = microtime(true);

        foreach ($specs as $spec) {
            $collector = new SnapshotCollector($projectRoot);
            $written = 0;
            $skipped = 0;

            foreach ($this->plan->build($spec) as $file) {
                if ($dryRun) {
                    echo '--- ' . $file->relativePath . " ---\n";
                    echo $file->contents;
                    echo "\n";

                    continue;
                }

                $before = $collector->captureBefore($file);
                $outcome = $writer->write($file, $force);
                $collector->record($file, $outcome, $before);
                echo $outcome->status->value, ' ', $outcome->relativePath, "\n";

                if ($outcome->status === WriteStatus::Skipped) {
                    $skipped++;
                } else {
                    $written++;
                }
            }

            if (!$dryRun) {
                $this->recordJournal($spec, $collector);
            }

            $totalWritten += $written;
            $totalSkipped += $skipped;
        }

        if (!$dryRun) {
            $duration = (int) ((microtime(true) - $startedAt) * 1000);
            $this->recordEvent(EventStatus::Ok, $path, $duration, $totalWritten, null);
            echo "Wrote {$totalWritten} file(s); skipped {$totalSkipped} existing file(s).\n";
        }

        return 0;
    }

    private function recordJournal(Spec $spec, SnapshotCollector $collector): void
    {
        if (!$this->journal instanceof Journal) {
            return;
        }

        try {
            $specPath = $this->specPath($spec);
            $specContent = is_file($specPath) ? (string) @file_get_contents($specPath) : '';

            $entry = JournalEntry::scaffold(
                command: 'bin/altair spec:scaffold ' . $specPath,
                specPath: $specPath,
                specContent: $specContent,
                scaffoldVersion: JournalEntry::VERSION,
                filesCreated: $collector->created(),
                filesModified: $collector->modified(),
                filesSkipped: $collector->skipped(),
            );
            $this->journal->record($entry);
        } catch (Throwable) {
            // Journaling is best-effort — never fail the scaffold over it.
        }
    }

    private function recordEvent(EventStatus $status, string $path, int $durationMs, ?int $createdCount, ?string $error): void
    {
        if (!$this->events instanceof RecorderInterface) {
            return;
        }

        try {
            $this->events->record(RecorderEvent::create(
                actor: Actor::Cli,
                command: 'bin/altair spec:scaffold ' . $path,
                kind: EventKind::Scaffold,
                status: $status,
                durationMs: $durationMs,
                changes: $createdCount !== null ? new Changes(['created' => array_fill(0, $createdCount, '*')]) : null,
                error: $error,
            ));
        } catch (Throwable) {
            // Event recording is best-effort.
        }
    }

    /**
     * `Spec::$sourcePath` is empty when the spec was constructed in
     * memory (typical in tests); fall back to a synthetic identifier
     * so the journal entry has something readable to display.
     */
    private function specPath(Spec $spec): string
    {
        return $spec->sourcePath !== '' ? $spec->sourcePath : '(in-memory) ' . $spec->artifactName();
    }
}
