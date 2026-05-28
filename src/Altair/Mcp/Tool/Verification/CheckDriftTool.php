<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Verification;

use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Support\ProjectContext;
use Altair\Scaffold\Linter\DriftDetector;
use Altair\Scaffold\Linter\DriftFinding;
use Altair\Scaffold\Linter\DriftReport;
use Altair\Scaffold\Spec\SpecLoader;
use Override;

#[McpTool(
    name: 'framework__check_drift',
    description: 'List mismatches between endpoint specs and the generated code on disk.',
    inputSchema: __DIR__ . '/../../Schema/no-args.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class CheckDriftTool implements McpToolInterface
{
    public function __construct(
        private ProjectContext $context,
        private SpecLoader $loader = new SpecLoader(),
    ) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $specRoot = $this->context->path('api');
        if (!is_dir($specRoot)) {
            return ['drift' => [], 'has_drift' => false, 'count' => 0, 'note' => 'No api/ directory in this project.'];
        }

        $detector = new DriftDetector($this->context->projectRoot);
        $report = new DriftReport([]);
        foreach ($this->loader->load($specRoot, validate: false) as $spec) {
            $report = $report->withMany($detector->detect($spec)->findings);
        }

        $findings = array_map(
            static fn(DriftFinding $finding): array => [
                'kind' => $finding->kind->value,
                'message' => $finding->message,
                'location' => $finding->location,
            ],
            $report->findings,
        );

        return ['drift' => $findings, 'has_drift' => $report->hasDrift(), 'count' => \count($findings)];
    }
}
