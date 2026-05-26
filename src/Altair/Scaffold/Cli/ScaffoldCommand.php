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
use Altair\Scaffold\Emitter\EmissionPlan;
use Altair\Scaffold\Spec\SpecLoader;
use Altair\Scaffold\Writer\FileWriter;
use Altair\Scaffold\Writer\WriteStatus;

/**
 * `bin/altair spec scaffold <path>` — read a YAML endpoint spec (or a directory
 * of specs) and emit the action/input/responder/domain stub/test/openapi/route
 * files.
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
        $specs = $this->loader->load($path);
        $writer = new FileWriter($projectRoot);

        $written = 0;
        $skipped = 0;
        foreach ($specs as $spec) {
            foreach ($this->plan->build($spec) as $file) {
                if ($dryRun) {
                    echo '--- ' . $file->relativePath . " ---\n";
                    echo $file->contents;
                    echo "\n";
                    continue;
                }

                $outcome = $writer->write($file, $force);
                echo $outcome->status->value, ' ', $outcome->relativePath, "\n";
                if ($outcome->status === WriteStatus::Skipped) {
                    $skipped++;
                } else {
                    $written++;
                }
            }
        }

        if (!$dryRun) {
            echo "Wrote {$written} file(s); skipped {$skipped} existing file(s).\n";
        }

        return 0;
    }
}
