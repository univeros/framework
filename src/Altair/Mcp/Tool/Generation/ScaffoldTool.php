<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Generation;

use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Exception\GuardrailException;
use Altair\Mcp\Guard\PathGuard;
use Altair\Mcp\Guard\ServerMode;
use Altair\Mcp\Support\EventLog;
use Altair\Mcp\Support\ProjectContext;
use Altair\Scaffold\Emitter\EmissionPlan;
use Altair\Scaffold\Journal\Journal;
use Altair\Scaffold\Journal\JournalEntry;
use Altair\Scaffold\Journal\SnapshotCollector;
use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Scaffold\Spec\SpecLoader;
use Altair\Scaffold\Writer\FileWriter;
use Altair\Scaffold\Writer\WriteStatus;
use Override;
use Throwable;

#[McpTool(
    name: 'framework__scaffold',
    description: 'Run the spec scaffolder against a YAML spec; returns the files emitted, modified and skipped.',
    inputSchema: __DIR__ . '/../../Schema/scaffold-input.json',
    outputSchema: __DIR__ . '/../../Schema/scaffold-output.json',
)]
final readonly class ScaffoldTool implements McpToolInterface
{
    public function __construct(
        private ProjectContext $context,
        private PathGuard $guard,
        private ServerMode $mode,
        private EventLog $events,
        private SpecLoader $loader = new SpecLoader(),
        private EmissionPlan $plan = new EmissionPlan(),
        private ?Journal $journal = null,
    ) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $specPath = \is_string($input['spec_path'] ?? null) ? $input['spec_path'] : '';
        $dryRun = ($input['dry_run'] ?? false) === true;
        $force = ($input['force'] ?? false) === true;

        if (!$dryRun && !$this->mode->allowsFileMutation()) {
            throw new GuardrailException('Server is in readonly mode; scaffold writes are disabled.');
        }

        $this->guard->assertWithinRoot($specPath);
        $absolute = str_starts_with($specPath, '/') ? $specPath : $this->context->path($specPath);
        $started = microtime(true);

        try {
            $specs = $this->loader->load($absolute);

            $writer = new FileWriter($this->context->projectRoot);
            $emitted = [];
            $modified = [];
            $skipped = [];

            foreach ($specs as $spec) {
                $collector = new SnapshotCollector($this->context->projectRoot);

                foreach ($this->plan->build($spec) as $file) {
                    if ($dryRun) {
                        $emitted[] = $file->relativePath;
                        continue;
                    }

                    $this->guard->assertWritable($file->relativePath);

                    $before = $collector->captureBefore($file);
                    $outcome = $writer->write($file, $force);
                    $collector->record($file, $outcome, $before);

                    match ($outcome->status) {
                        WriteStatus::Written => $emitted[] = $outcome->relativePath,
                        WriteStatus::Modified => $modified[] = $outcome->relativePath,
                        WriteStatus::Skipped => $skipped[] = $outcome->relativePath,
                    };
                }

                if (!$dryRun) {
                    $this->recordJournal($spec, $collector);
                }
            }
        } catch (Throwable $throwable) {
            if (!$dryRun) {
                $this->events->record(
                    EventKind::Scaffold,
                    EventStatus::Fail,
                    'mcp framework__scaffold ' . $specPath,
                    (int) ((microtime(true) - $started) * 1000),
                    $throwable->getMessage(),
                );
            }

            throw $throwable;
        }

        if (!$dryRun) {
            $this->events->record(
                EventKind::Scaffold,
                EventStatus::Ok,
                'mcp framework__scaffold ' . $specPath,
                (int) ((microtime(true) - $started) * 1000),
            );
        }

        return ['emitted' => $emitted, 'modified' => $modified, 'skipped' => $skipped, 'dry_run' => $dryRun];
    }

    private function recordJournal(Spec $spec, SnapshotCollector $collector): void
    {
        if (!$this->journal instanceof Journal) {
            return;
        }

        try {
            $specPath = $spec->sourcePath !== '' ? $spec->sourcePath : '(in-memory) ' . $spec->artifactName();
            $specContent = is_file($specPath) ? (string) @file_get_contents($specPath) : '';

            $this->journal->record(JournalEntry::scaffold(
                command: 'mcp framework__scaffold ' . $specPath,
                specPath: $specPath,
                specContent: $specContent,
                scaffoldVersion: JournalEntry::VERSION,
                filesCreated: $collector->created(),
                filesModified: $collector->modified(),
                filesSkipped: $collector->skipped(),
            ));
        } catch (Throwable) {
            // Journaling is best-effort.
        }
    }
}
