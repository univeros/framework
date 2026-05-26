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
use Altair\Scaffold\Linter\DriftDetector;
use Altair\Scaffold\Linter\DriftReport;
use Altair\Scaffold\Spec\SpecLoader;

/**
 * `bin/altair spec lint [<path>]` — compare every spec against the on-disk
 * generated code and report drift. Exits non-zero on drift so CI catches it.
 */
#[Command(
    name: 'spec:lint',
    description: 'Detect drift between endpoint specs and generated code.',
)]
final readonly class LintCommand
{
    public function __construct(
        private SpecLoader $loader = new SpecLoader(),
        private PathResolver $paths = new PathResolver(),
    ) {}

    public function __invoke(
        #[Argument(description: 'Spec file or directory (default: api/).')]
        ?string $path = null,
        #[Option(description: 'Override the project root.')]
        ?string $root = null,
    ): int {
        $projectRoot = $this->paths->resolveProjectRoot($root);
        $path ??= $projectRoot . DIRECTORY_SEPARATOR . 'api';

        $specs = $this->loader->load($path);
        $detector = new DriftDetector($projectRoot);

        $report = new DriftReport([]);
        foreach ($specs as $spec) {
            $report = $report->withMany($detector->detect($spec)->findings);
        }

        foreach ($report->findings as $finding) {
            echo '[', $finding->kind->value, '] ', $finding->message, "\n";
        }

        if ($report->hasDrift()) {
            echo \count($report->findings), " drift finding(s).\n";

            return 1;
        }

        echo "No drift detected.\n";

        return 0;
    }
}
