<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Writer;

use Altair\AgentSpec\Exception\AgentSpecException;

/**
 * Filesystem-aware writer that supports two modes:
 *
 *   write()  — create or overwrite the file with $contents
 *   check()  — return true when the file's contents already match $contents
 */
class ManifestWriter
{
    public function write(string $path, string $contents): void
    {
        $directory = \dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw new AgentSpecException(\sprintf("Cannot create directory '%s'.", $directory));
        }

        $bytes = @file_put_contents($path, $contents);
        if ($bytes === false) {
            throw new AgentSpecException(\sprintf("Cannot write to '%s'.", $path));
        }
    }

    public function check(string $path, string $contents): bool
    {
        if (!is_file($path)) {
            return false;
        }

        $existing = @file_get_contents($path);
        if ($existing === false) {
            return false;
        }

        return $existing === $contents;
    }
}
