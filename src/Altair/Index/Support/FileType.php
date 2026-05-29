<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Support;

/**
 * Classifies an indexed file path so impact analysis can separate the tests and
 * specs that depend on a symbol from ordinary source files.
 */
final class FileType
{
    public static function isTest(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        return str_ends_with($normalized, 'Test.php')
            || str_contains($normalized, '/tests/')
            || str_starts_with($normalized, 'tests/');
    }

    public static function isSpec(string $path): bool
    {
        $lower = strtolower($path);

        return str_ends_with($lower, '.yaml') || str_ends_with($lower, '.yml');
    }
}
