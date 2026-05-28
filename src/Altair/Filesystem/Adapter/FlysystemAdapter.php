<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Filesystem\Adapter;

use Altair\Filesystem\Contracts\FilesystemAdapterInterface;
use DateTimeInterface;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Override;

/**
 * Adapter that decorates a Flysystem v3 FilesystemOperator with prepend/append/exists/listDirectories helpers.
 */
class FlysystemAdapter implements FilesystemAdapterInterface
{
    public function __construct(
        private readonly FilesystemOperator $driver,
    ) {}

    #[Override]
    public function getDriver(): FilesystemOperator
    {
        return $this->driver;
    }

    #[Override]
    public function exists(string $path): bool
    {
        if ($this->driver->fileExists($path)) {
            return true;
        }

        return $this->driver->directoryExists($path);
    }

    #[Override]
    public function prepend(string $path, string $data, string $separator = PHP_EOL): void
    {
        $existing = $this->driver->fileExists($path) ? $this->driver->read($path) : '';

        $this->driver->write($path, $existing !== '' ? $data . $separator . $existing : $data);
    }

    #[Override]
    public function append(string $path, string $data, string $separator = PHP_EOL): void
    {
        $existing = $this->driver->fileExists($path) ? $this->driver->read($path) : '';

        $this->driver->write($path, $existing !== '' ? $existing . $separator . $data : $data);
    }

    #[Override]
    public function listDirectories(string $directory = '', bool $recursive = false): array
    {
        $listing = $this->driver->listContents($directory, $recursive);

        return array_values(array_map(
            static fn(StorageAttributes $attributes): string => $attributes->path(),
            iterator_to_array(
                $listing->filter(static fn(StorageAttributes $attributes): bool => $attributes instanceof DirectoryAttributes),
                false,
            ),
        ));
    }

    #[Override]
    public function fileExists(string $location): bool
    {
        return $this->driver->fileExists($location);
    }

    #[Override]
    public function directoryExists(string $location): bool
    {
        return $this->driver->directoryExists($location);
    }

    #[Override]
    public function has(string $location): bool
    {
        return $this->driver->has($location);
    }

    #[Override]
    public function read(string $location): string
    {
        return $this->driver->read($location);
    }

    #[Override]
    public function readStream(string $location)
    {
        return $this->driver->readStream($location);
    }

    #[Override]
    public function listContents(string $location, bool $deep = FilesystemOperator::LIST_SHALLOW): DirectoryListing
    {
        return $this->driver->listContents($location, $deep);
    }

    #[Override]
    public function lastModified(string $path): int
    {
        return $this->driver->lastModified($path);
    }

    #[Override]
    public function fileSize(string $path): int
    {
        return $this->driver->fileSize($path);
    }

    #[Override]
    public function mimeType(string $path): string
    {
        return $this->driver->mimeType($path);
    }

    #[Override]
    public function visibility(string $path): string
    {
        return $this->driver->visibility($path);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[Override]
    public function write(string $location, string $contents, array $config = []): void
    {
        $this->driver->write($location, $contents, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[Override]
    public function writeStream(string $location, $contents, array $config = []): void
    {
        $this->driver->writeStream($location, $contents, $config);
    }

    #[Override]
    public function setVisibility(string $path, string $visibility): void
    {
        $this->driver->setVisibility($path, $visibility);
    }

    #[Override]
    public function delete(string $location): void
    {
        $this->driver->delete($location);
    }

    #[Override]
    public function deleteDirectory(string $location): void
    {
        $this->driver->deleteDirectory($location);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[Override]
    public function createDirectory(string $location, array $config = []): void
    {
        $this->driver->createDirectory($location, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[Override]
    public function move(string $source, string $destination, array $config = []): void
    {
        $this->driver->move($source, $destination, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[Override]
    public function copy(string $source, string $destination, array $config = []): void
    {
        $this->driver->copy($source, $destination, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function publicUrl(string $path, array $config = []): string
    {
        return $this->driver->publicUrl($path, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function temporaryUrl(string $path, DateTimeInterface $expiresAt, array $config = []): string
    {
        return $this->driver->temporaryUrl($path, $expiresAt, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function checksum(string $path, array $config = []): string
    {
        return $this->driver->checksum($path, $config);
    }
}
