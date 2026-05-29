<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Builder;

/**
 * Where to find source, where to find specs, and where to write the index.
 *
 * Paths in `sourcePaths` / `specPaths` are relative to `projectRoot`; the index
 * stores every file path relative to the root too, so a query result is
 * portable and reads the way a developer refers to the file.
 */
final readonly class IndexConfig
{
    /**
     * @param list<string> $sourcePaths
     * @param list<string> $specPaths
     * @param list<string> $excludeDirs
     */
    public function __construct(
        public string $projectRoot,
        public string $databasePath,
        public array $sourcePaths = ['src', 'app', 'tests'],
        public array $specPaths = ['api'],
        public array $excludeDirs = ['vendor', 'node_modules', '.git', '.altair', 'build', 'runtime'],
    ) {}

    public static function forRoot(string $root, ?string $databasePath = null): self
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');

        return new self($root, $databasePath ?? $root . '/.altair/index.db');
    }

    public function absolute(string $relative): string
    {
        return $this->projectRoot . '/' . $relative;
    }
}
