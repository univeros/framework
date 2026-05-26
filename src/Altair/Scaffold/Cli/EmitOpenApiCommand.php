<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Scaffold\Exception\ScaffoldException;
use Symfony\Component\Yaml\Yaml;

/**
 * `bin/altair spec emit-openapi` — merge every `docs/openapi/*.yaml` fragment
 * into a single OpenAPI 3.1 document on stdout (or to disk via `--out`).
 */
#[Command(
    name: 'spec:emit-openapi',
    description: 'Merge per-endpoint OpenAPI fragments into a single document.',
)]
final readonly class EmitOpenApiCommand
{
    public function __construct(private PathResolver $paths = new PathResolver()) {}

    public function __invoke(
        #[Option(description: 'Override the project root.')]
        ?string $root = null,
        #[Option(description: 'Directory containing OpenAPI fragments.', name: 'fragments')]
        ?string $fragmentsDir = null,
        #[Option(description: 'Write the merged document to this file instead of stdout.', name: 'out')]
        ?string $outFile = null,
        #[Option(description: 'Pretty-print the merged YAML.')]
        bool $pretty = false,
    ): int {
        $projectRoot = $this->paths->resolveProjectRoot($root);
        $fragmentsDir ??= $projectRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'openapi';

        if (!is_dir($fragmentsDir)) {
            throw new ScaffoldException(\sprintf("Fragments directory '%s' does not exist.", $fragmentsDir));
        }

        $merged = $this->mergeFragments($fragmentsDir);
        $indent = $pretty ? 4 : 2;
        $yaml = Yaml::dump($merged, 8, $indent, Yaml::DUMP_OBJECT_AS_MAP);

        if ($outFile !== null) {
            if (file_put_contents($outFile, $yaml) === false) {
                throw new ScaffoldException(\sprintf("Failed to write '%s'.", $outFile));
            }
            echo "Wrote {$outFile}\n";
        } else {
            echo $yaml;
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeFragments(string $directory): array
    {
        $merged = [
            'openapi' => '3.1.0',
            'info' => ['title' => 'Univeros API', 'version' => '0.0.0'],
            'paths' => [],
        ];

        $files = glob($directory . DIRECTORY_SEPARATOR . '*.yaml') ?: [];
        sort($files);

        foreach ($files as $file) {
            $data = Yaml::parseFile($file);
            if (!\is_array($data) || !isset($data['paths']) || !\is_array($data['paths'])) {
                continue;
            }

            foreach ($data['paths'] as $path => $operations) {
                $merged['paths'][$path] = array_merge(
                    $merged['paths'][$path] ?? [],
                    \is_array($operations) ? $operations : [],
                );
            }
        }

        return $merged;
    }
}
