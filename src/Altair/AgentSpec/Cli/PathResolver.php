<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Cli;

use Altair\AgentSpec\Exception\AgentSpecException;

/**
 * Resolves the monorepo root, source root, tests root, and output directory
 * either from explicit overrides or by walking up from the current working
 * directory until we hit a folder containing both `composer.json` and `src/Altair`.
 */
class PathResolver
{
    public function resolve(
        ?string $rootOverride,
        ?string $sourceOverride,
        ?string $testsOverride,
        ?string $outputOverride,
    ): ResolvedPaths {
        $monorepoRoot = $rootOverride !== null
            ? $this->canonical($rootOverride)
            : $this->detectMonorepoRoot();

        $sourceRoot = $sourceOverride !== null
            ? $this->canonical($sourceOverride)
            : $monorepoRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Altair';

        $testsRoot = null;
        if ($testsOverride !== null) {
            $testsRoot = $this->canonical($testsOverride);
        } else {
            $candidate = $monorepoRoot . DIRECTORY_SEPARATOR . 'tests';
            if (is_dir($candidate)) {
                $testsRoot = $candidate;
            }
        }

        $outputRoot = $outputOverride !== null
            ? $this->canonical($outputOverride)
            : $monorepoRoot . DIRECTORY_SEPARATOR . '.agent';

        if (!is_dir($sourceRoot)) {
            throw new AgentSpecException(\sprintf("Source root '%s' does not exist.", $sourceRoot));
        }

        return new ResolvedPaths($monorepoRoot, $sourceRoot, $testsRoot, $outputRoot);
    }

    private function detectMonorepoRoot(): string
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new AgentSpecException('Cannot determine current working directory.');
        }

        $current = $cwd;
        while (true) {
            if (
                is_file($current . DIRECTORY_SEPARATOR . 'composer.json')
                && is_dir($current . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Altair')
            ) {
                return $current;
            }

            $parent = \dirname($current);
            if ($parent === $current) {
                return $cwd;
            }

            $current = $parent;
        }
    }

    private function canonical(string $path): string
    {
        $real = realpath($path);

        return $real === false ? $path : $real;
    }
}
