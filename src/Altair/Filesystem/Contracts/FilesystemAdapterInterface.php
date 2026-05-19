<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Filesystem\Contracts;

use League\Flysystem\FilesystemOperator;

interface FilesystemAdapterInterface extends FilesystemOperator
{
    /**
     * Get the underlying Flysystem operator.
     */
    public function getDriver(): FilesystemOperator;

    /**
     * Determine if a file exists at the given path.
     */
    public function exists(string $path): bool;

    /**
     * Prepend content to a file (creates it if missing).
     */
    public function prepend(string $path, string $data, string $separator = PHP_EOL): void;

    /**
     * Append content to a file (creates it if missing).
     */
    public function append(string $path, string $data, string $separator = PHP_EOL): void;

    /**
     * List subdirectories under `$directory`.
     *
     * @return list<string>
     */
    public function listDirectories(string $directory = '', bool $recursive = false): array;
}
