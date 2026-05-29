<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Builder;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Enumerates the PHP and spec files an index build should cover, returning each
 * path relative to the project root and in a stable sorted order so a rebuild
 * is deterministic. Excluded directories are pruned during traversal rather
 * than filtered afterwards, so large trees like `vendor/` are never descended.
 */
final readonly class SourceScanner
{
    public function __construct(private IndexConfig $config) {}

    /**
     * @return list<string>
     */
    public function phpFiles(): array
    {
        return $this->scan($this->config->sourcePaths, ['php']);
    }

    /**
     * @return list<string>
     */
    public function specFiles(): array
    {
        return $this->scan($this->config->specPaths, ['yaml', 'yml']);
    }

    /**
     * @param list<string> $roots
     * @param list<string> $extensions
     *
     * @return list<string>
     */
    private function scan(array $roots, array $extensions): array
    {
        $files = [];
        foreach ($roots as $root) {
            $absoluteRoot = $this->config->absolute($root);
            if (!is_dir($absoluteRoot)) {
                continue;
            }

            foreach ($this->iterate($absoluteRoot) as $file) {
                if ($file->isFile() && \in_array(strtolower($file->getExtension()), $extensions, true)) {
                    $files[] = $this->relative($file->getPathname());
                }
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function iterate(string $absoluteRoot): iterable
    {
        $excludes = $this->config->excludeDirs;

        $directories = new RecursiveDirectoryIterator($absoluteRoot, FilesystemIterator::SKIP_DOTS);
        $filtered = new RecursiveCallbackFilterIterator(
            $directories,
            static function (SplFileInfo $current) use ($excludes): bool {
                if ($current->isDir()) {
                    return !\in_array($current->getFilename(), $excludes, true);
                }

                return true;
            },
        );

        /** @var iterable<SplFileInfo> $iterator */
        $iterator = new RecursiveIteratorIterator($filtered);

        return $iterator;
    }

    private function relative(string $absolute): string
    {
        $normalized = str_replace('\\', '/', $absolute);
        $prefix = $this->config->projectRoot . '/';

        return str_starts_with($normalized, $prefix) ? substr($normalized, \strlen($prefix)) : $normalized;
    }
}
