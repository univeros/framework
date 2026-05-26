<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Cli;

use Altair\Scaffold\Exception\ScaffoldException;

/**
 * Picks the project root used as the base for emitted-file paths. Walks up
 * from the current working directory until it finds a `composer.json`;
 * caller-supplied overrides take precedence.
 */
class PathResolver
{
    public function resolveProjectRoot(?string $override): string
    {
        if ($override !== null) {
            return $this->canonical($override);
        }

        $cwd = getcwd();
        if ($cwd === false) {
            throw new ScaffoldException('Cannot determine current working directory.');
        }

        $current = $cwd;
        while (true) {
            if (is_file($current . DIRECTORY_SEPARATOR . 'composer.json')) {
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
        if ($real === false) {
            throw new ScaffoldException(\sprintf("Path '%s' does not exist.", $path));
        }

        return $real;
    }
}
