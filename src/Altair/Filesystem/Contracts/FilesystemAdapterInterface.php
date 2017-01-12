<?php
namespace Altair\Filesystem\Contracts;

use League\Flysystem\FilesystemInterface;

interface FilesystemAdapterInterface extends FilesystemInterface
{
    /**
     * Get the Flysystem driver.
     *
     * @return \League\Flysystem\FilesystemInterface
     */
    public function getDriver();

    /**
     * Determine if a file exists.
     *
     * @param  string $path
     *
     * @return bool
     */
    public function exists($path): bool;

    /**
     * Prepend to a file.
     *
     * @param  string $path
     * @param  string $data
     * @param  string $separator
     *
     * @return bool
     */
    public function prepend(string $path, string $data, string $separator = PHP_EOL): bool;

    /**
     * Append to a file.
     *
     * @param  string $path
     * @param  string $data
     * @param  string $separator
     *
     * @return bool
     */
    public function append(string $path, string $data, string $separator = PHP_EOL): bool;

    /**
     * @param string $directory
     * @param bool $recursive
     *
     * @return array
     */
    public function listDirectories(string $directory = '', bool $recursive = false): array;
}
