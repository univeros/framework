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
use Altair\Mcp\Support\ProjectContext;
use Altair\Scaffold\Spec\SpecLoader;
use Override;
use Throwable;

#[McpTool(
    name: 'framework__list_endpoints',
    description: 'List every HTTP endpoint declared by the project specs (method, path, action).',
    inputSchema: __DIR__ . '/../../Schema/no-args.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class ListEndpointsTool implements McpToolInterface
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
            return ['endpoints' => [], 'count' => 0, 'note' => 'No api/ directory in this project.'];
        }

        $endpoints = [];
        foreach ($this->loader->load($specRoot, validate: false) as $spec) {
            try {
                $endpoints[] = [
                    'method' => strtoupper($spec->endpoint->method),
                    'path' => $spec->endpoint->path,
                    'summary' => $spec->endpoint->summary,
                    'action' => $spec->artifactName(),
                    'spec' => $this->relative($spec->sourcePath),
                ];
            } catch (Throwable) {
                // Skip a spec that cannot describe itself; list the rest.
            }
        }

        usort($endpoints, static fn(array $a, array $b): int => [$a['path'], $a['method']] <=> [$b['path'], $b['method']]);

        return ['endpoints' => $endpoints, 'count' => \count($endpoints)];
    }

    private function relative(string $absolute): string
    {
        $prefix = $this->context->projectRoot . '/';

        return str_starts_with($absolute, $prefix) ? substr($absolute, \strlen($prefix)) : $absolute;
    }
}
