<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Discovery;

use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Exception\McpException;
use Altair\Mcp\Guard\PathGuard;
use Altair\Mcp\Support\ProjectContext;
use Altair\Scaffold\Emitter\EmissionPlan;
use Altair\Scaffold\Linter\DriftDetector;
use Altair\Scaffold\Linter\DriftFinding;
use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Scaffold\Spec\SpecLoader;
use Override;
use Throwable;

#[McpTool(
    name: 'framework__describe_endpoint',
    description: 'Describe one endpoint: its spec, the files the scaffolder would emit, and any drift.',
    inputSchema: __DIR__ . '/../../Schema/describe-endpoint-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class DescribeEndpointTool implements McpToolInterface
{
    public function __construct(
        private ProjectContext $context,
        private PathGuard $guard,
        private SpecLoader $loader = new SpecLoader(),
        private EmissionPlan $plan = new EmissionPlan(),
    ) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $path = \is_string($input['spec_path'] ?? null) ? $input['spec_path'] : '';
        $this->guard->assertWithinRoot($path);
        $absolute = str_starts_with($path, '/') ? $path : $this->context->path($path);

        if (!is_file($absolute)) {
            throw new McpException(\sprintf("Spec file '%s' does not exist.", $path));
        }

        $specs = $this->loader->load($absolute);
        $spec = $specs[0] ?? throw new McpException(\sprintf("No spec found in '%s'.", $path));

        return [
            'endpoint' => [
                'method' => strtoupper($spec->endpoint->method),
                'path' => $spec->endpoint->path,
                'summary' => $spec->endpoint->summary,
                'action' => $spec->artifactName(),
            ],
            'planned_files' => $this->plannedFiles($spec),
            'drift' => $this->drift($spec),
        ];
    }

    /**
     * @return list<array{path: string, kind: string}>
     */
    private function plannedFiles(Spec $spec): array
    {
        $files = [];
        foreach ($this->plan->build($spec) as $file) {
            $files[] = ['path' => $file->relativePath, 'kind' => $file->kind->value];
        }

        return $files;
    }

    /**
     * @return list<array{kind: string, message: string, location: string}>
     */
    private function drift(Spec $spec): array
    {
        try {
            $report = (new DriftDetector($this->context->projectRoot))->detect($spec);
        } catch (Throwable) {
            return [];
        }

        return array_map(
            static fn(DriftFinding $finding): array => [
                'kind' => $finding->kind->value,
                'message' => $finding->message,
                'location' => $finding->location,
            ],
            $report->findings,
        );
    }
}
