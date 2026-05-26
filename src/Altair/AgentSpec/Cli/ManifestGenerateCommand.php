<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Cli;

use Altair\AgentSpec\Generator\ManifestPipeline;
use Altair\AgentSpec\Generator\ManifestPipelineOptions;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;

/**
 * Walks every framework sub-package, generates a Markdown manifest for each,
 * and writes them under `.agent/packages/` plus the index at `.agent/MANIFEST.md`.
 *
 * With `--check`, no files are written; the command exits non-zero if any
 * regenerated manifest differs from what is already on disk.
 */
#[Command(
    name: 'manifest:generate',
    description: 'Generate AI-readable manifests for every framework sub-package.',
)]
final readonly class ManifestGenerateCommand
{
    public function __construct(
        private ManifestPipeline $pipeline = new ManifestPipeline(),
        private PathResolver $paths = new PathResolver(),
        private ConsoleReporter $reporter = new ConsoleReporter(),
    ) {}

    public function __invoke(
        #[Option(description: 'Override the monorepo root used as a base for relative paths.')]
        ?string $root = null,
        #[Option(description: 'Override the source root that contains framework sub-packages.', name: 'source')]
        ?string $source = null,
        #[Option(description: 'Override the tests root used to list test file references.', name: 'tests')]
        ?string $tests = null,
        #[Option(description: 'Override the output directory for generated manifests.', name: 'output')]
        ?string $output = null,
        #[Option(description: 'Verify that on-disk manifests match what would be regenerated. Exits 1 on drift.')]
        bool $check = false,
    ): int {
        $resolved = $this->paths->resolve($root, $source, $tests, $output);
        $touched = $this->pipeline->run(new ManifestPipelineOptions(
            monorepoRoot: $resolved->monorepoRoot,
            sourceRoot: $resolved->sourceRoot,
            testsRoot: $resolved->testsRoot,
            outputRoot: $resolved->outputRoot,
            checkOnly: $check,
        ));

        if ($check) {
            return $this->reporter->reportCheck($touched);
        }

        return $this->reporter->reportWrite($touched);
    }
}
