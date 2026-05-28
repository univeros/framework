<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Support;

use const DIRECTORY_SEPARATOR;

use Symfony\Component\Yaml\Yaml;

/**
 * Merges the per-endpoint OpenAPI fragments under `docs/openapi/*.yaml` into a
 * single OpenAPI 3.1 document — the same merge `spec:emit-openapi` performs,
 * shared by the emit_openapi and emit_sdk tools.
 */
final readonly class OpenApiFragments
{
    public function __construct(private ProjectContext $context) {}

    public function directory(): string
    {
        return $this->context->path('docs', 'openapi');
    }

    public function exists(): bool
    {
        return is_dir($this->directory());
    }

    /**
     * @return array<string, mixed>
     */
    public function merge(): array
    {
        $merged = [
            'openapi' => '3.1.0',
            'info' => ['title' => 'Univeros API', 'version' => '0.0.0'],
            'paths' => [],
            'components' => ['schemas' => []],
        ];

        $files = glob($this->directory() . DIRECTORY_SEPARATOR . '*.yaml') ?: [];
        sort($files);

        foreach ($files as $file) {
            $fragment = Yaml::parseFile($file);
            if (!\is_array($fragment)) {
                continue;
            }

            if (\is_array($fragment['paths'] ?? null)) {
                /** @var array<string, mixed> $paths */
                $paths = $merged['paths'];
                $merged['paths'] = array_merge($paths, $fragment['paths']);
            }

            if (\is_array($fragment['components']['schemas'] ?? null)) {
                /** @var array<string, mixed> $schemas */
                $schemas = $merged['components']['schemas'];
                $merged['components']['schemas'] = array_merge($schemas, $fragment['components']['schemas']);
            }
        }

        return $merged;
    }

    public function mergeYaml(): string
    {
        return Yaml::dump($this->merge(), 8, 2);
    }
}
