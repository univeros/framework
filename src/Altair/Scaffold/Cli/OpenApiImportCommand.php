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
use Altair\Events\Contracts\RecorderInterface;
use Altair\Scaffold\Exception\ScaffoldException;
use Altair\Scaffold\Journal\Journal;

/**
 * `bin/altair openapi:import <document>` — read an OpenAPI 3.1 YAML doc
 * and emit one Altair YAML spec per operation. Optionally chains into
 * `spec:scaffold` so the project is runnable in one command.
 *
 * The reverse direction of `spec:emit-openapi`. Delegates all logic to
 * {@see OpenApiImportRunner} so the command class stays a thin shell
 * that maps CLI flags to runner options and renders the receipt.
 *
 * Journal-aware and event-aware when those services are bound; both
 * dependencies are optional.
 */
#[Command(
    name: 'openapi:import',
    description: 'Import an OpenAPI 3.1 document and emit Altair YAML specs (optionally scaffold each).',
)]
final readonly class OpenApiImportCommand
{
    public function __construct(
        private PathResolver $paths = new PathResolver(),
        private ?Journal $journal = null,
        private ?RecorderInterface $events = null,
    ) {}

    public function __invoke(
        #[Argument(description: 'Path to the OpenAPI 3.1 YAML document.')]
        string $document,
        #[Option(description: 'Directory to write emitted Altair specs into.', name: 'out')]
        ?string $out = null,
        #[Option(description: 'After writing specs, run spec:scaffold on each.')]
        bool $scaffold = false,
        #[Option(description: 'Print planned changes without writing.', name: 'dry-run')]
        bool $dryRun = false,
        #[Option(description: 'Overwrite existing files instead of skipping.')]
        bool $force = false,
        #[Option(description: 'Skip operations whose schema cannot be mapped instead of aborting the whole import.', name: 'skip-unmappable')]
        bool $skipUnmappable = false,
        #[Option(description: 'Emit JSON receipt (human|json).')]
        string $format = 'human',
        #[Option(description: 'Persistence binding to infer for each spec (cycle).')]
        ?string $persistence = null,
        #[Option(description: 'Queue transport (preserved for x-altair-queue extension in #163).')]
        ?string $queue = null,
        #[Option(description: 'Override the project root used as base for emitted paths.')]
        ?string $root = null,
    ): int {
        $projectRoot = $this->paths->resolveProjectRoot($root);

        if ($persistence !== null && $persistence !== 'cycle') {
            throw new ScaffoldException(\sprintf(
                "--persistence='%s' is not supported. Only 'cycle' is available in this release.",
                $persistence,
            ));
        }

        if ($format !== 'human' && $format !== 'json') {
            throw new ScaffoldException(\sprintf("--format='%s' is not supported. Use 'human' or 'json'.", $format));
        }

        $options = new OpenApiImportOptions(
            documentPath: $document,
            projectRoot: $projectRoot,
            outDir: $out,
            scaffold: $scaffold,
            dryRun: $dryRun,
            force: $force,
            skipUnmappable: $skipUnmappable,
            persistence: $persistence,
            queue: $queue,
        );

        $runner = new OpenApiImportRunner(
            journal: $this->journal,
            events: $this->events,
        );

        $receipt = $runner->run($options);

        if ($format === 'json') {
            echo $receipt->toJson() . PHP_EOL;
        } else {
            $this->renderHuman($receipt);
        }

        return $receipt->ok ? 0 : 1;
    }

    private function renderHuman(ImportReceipt $receipt): void
    {
        if (!$receipt->ok) {
            echo \sprintf("openapi:import failed: %s%s", $receipt->error ?? '(unknown error)', PHP_EOL);
            foreach ($receipt->unmapped as $unmapped) {
                echo \sprintf('  at %s: %s%s', $unmapped['pointer'], $unmapped['message'], PHP_EOL);
            }

            if ($receipt->rolledBack !== []) {
                echo \sprintf('Rolled back %d spec file(s):%s', \count($receipt->rolledBack), PHP_EOL);
                foreach ($receipt->rolledBack as $path) {
                    echo '  - ' . $path . PHP_EOL;
                }
            }

            return;
        }

        echo \sprintf('Wrote %d spec file(s):%s', \count($receipt->specsWritten), PHP_EOL);
        foreach ($receipt->specsWritten as $path) {
            echo '  - ' . $path . PHP_EOL;
        }

        if ($receipt->unmapped !== []) {
            echo \sprintf('Skipped %d unmappable operation(s):%s', \count($receipt->unmapped), PHP_EOL);
            foreach ($receipt->unmapped as $unmapped) {
                echo \sprintf('  at %s: %s%s', $unmapped['pointer'], $unmapped['message'], PHP_EOL);
            }
        }

        if ($receipt->scaffoldRequested) {
            echo \sprintf('Scaffolded %d file(s).%s', \count($receipt->scaffolded), PHP_EOL);
        }

        foreach ($receipt->warnings as $warning) {
            echo 'warning: ' . $warning . PHP_EOL;
        }

        if ($receipt->journalId !== null) {
            echo 'Journal entry: ' . $receipt->journalId . PHP_EOL;
        }
    }
}
