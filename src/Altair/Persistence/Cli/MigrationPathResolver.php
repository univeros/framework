<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Cli;

/**
 * Resolves project-relative paths for the persistence CLI commands.
 *
 * Mirrors the helper used by {@see \Altair\Scaffold\Cli\PathResolver}; we
 * don't reuse that one to avoid leaking a Scaffold dep into Persistence.
 */
final class MigrationPathResolver
{
    public function resolveProjectRoot(?string $override): string
    {
        if ($override !== null && $override !== '') {
            return rtrim($override, DIRECTORY_SEPARATOR);
        }

        $cwd = getcwd();

        return $cwd === false ? '.' : $cwd;
    }

    public function resolveMigrationsDirectory(string $projectRoot, ?string $override): string
    {
        $relative = $override !== null && $override !== ''
            ? $override
            : 'database' . DIRECTORY_SEPARATOR . 'migrations';

        return $projectRoot . DIRECTORY_SEPARATOR . $relative;
    }
}
