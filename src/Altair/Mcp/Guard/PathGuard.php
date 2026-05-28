<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Guard;

use Altair\Mcp\Exception\GuardrailException;

/**
 * Blocks tool writes to paths that must never be machine-edited: `vendor/`,
 * `.git/`, the composer manifest/lock, and any `.env*` file. Also confines
 * reads to the project root. Paths are resolved lexically (no filesystem access
 * needed) so the check works for files that do not exist yet.
 *
 * Note: symlinks inside the project root that point outside it are not detected
 * — an accepted limitation of the local-dev trust model.
 */
final readonly class PathGuard
{
    private string $root;

    public function __construct(string $projectRoot)
    {
        $this->root = $this->normalize($projectRoot);
    }

    public function assertWritable(string $path): void
    {
        if ($this->isForbidden($path)) {
            throw new GuardrailException(\sprintf(
                "Refusing to write '%s': vendor/, .git/, composer.json, composer.lock and .env* are protected.",
                $path,
            ));
        }
    }

    /**
     * Confine reads to the project root: a tool may not read a path that
     * escapes it (absolute paths outside the tree, `../` traversal).
     */
    public function assertWithinRoot(string $path): void
    {
        if (!$this->isWithinRoot($path)) {
            throw new GuardrailException(\sprintf(
                "Refusing to access '%s': path escapes the project root.",
                $path,
            ));
        }
    }

    public function isWithinRoot(string $path): bool
    {
        return $this->relativeToRoot($this->toAbsolute($path)) !== null;
    }

    public function isForbidden(string $path): bool
    {
        $absolute = $this->toAbsolute($path);
        $relative = $this->relativeToRoot($absolute);

        // Outside the project root entirely — refuse.
        if ($relative === null) {
            return true;
        }

        $segments = explode('/', $relative);
        $first = $segments[0] ?? '';

        if ($first === 'vendor' || $first === '.git') {
            return true;
        }

        $basename = basename($absolute);

        if ($basename === 'composer.json' || $basename === 'composer.lock') {
            return true;
        }

        return str_starts_with($basename, '.env');
    }

    private function toAbsolute(string $path): string
    {
        $normalized = $this->normalize($path);

        if (str_starts_with($normalized, '/')) {
            return $normalized;
        }

        return $this->normalize($this->root . '/' . $normalized);
    }

    /**
     * Path relative to the project root, or null when it escapes the root.
     */
    private function relativeToRoot(string $absolute): ?string
    {
        if ($absolute === $this->root) {
            return '';
        }

        $prefix = $this->root . '/';
        if (!str_starts_with($absolute, $prefix)) {
            return null;
        }

        return substr($absolute, \strlen($prefix));
    }

    /**
     * Collapse `.` / `..` / duplicate separators lexically and normalise to
     * forward slashes. Does not touch the filesystem.
     */
    private function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $isAbsolute = str_starts_with($path, '/');

        $result = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '') {
                continue;
            }

            if ($segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if ($result !== [] && end($result) !== '..') {
                    array_pop($result);
                } elseif (!$isAbsolute) {
                    $result[] = '..';
                }

                continue;
            }

            $result[] = $segment;
        }

        $joined = implode('/', $result);

        return $isAbsolute ? '/' . $joined : $joined;
    }
}
