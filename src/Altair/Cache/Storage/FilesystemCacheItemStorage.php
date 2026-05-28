<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cache\Storage;

use Altair\Cache\Contracts\CacheItemStorageInterface;
use Altair\Cache\Exception\InvalidArgumentException;
use Altair\Filesystem\Exception\FileNotFoundException;
use Altair\Filesystem\Filesystem;
use Override;
use stdClass;

class FilesystemCacheItemStorage implements CacheItemStorageInterface
{
    protected string $directory;

    protected Filesystem $filesystem;

    protected string $tmp;

    /**
     * FilesystemCacheItemPoolStorage constructor.
     */
    public function __construct(Filesystem $filesystem, ?string $directory = null)
    {
        $this->setCacheDirectory($filesystem, $directory);
        $this->filesystem = $filesystem;
        $this->tmp = $this->directory . uniqid('', true);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getMaxIdLength(): ?int
    {
        return null;
    }

    /**
     * @inheritDoc
     *
     * @throws FileNotFoundException
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function getItems(array $keys = []): array
    {
        $items = [];
        $now = time();
        foreach ($keys as $id) {
            $file = $this->getFilePath($id);
            if (!$this->filesystem->exists($file)) {
                continue;
            }

            if (!$this->filesystem->isFile($file)) {
                continue;
            }

            if (!($item = $this->filesystem->getRequiredFileValue($file))) {
                continue;
            }

            if ($now >= (int) $item->expiresAt) {
                $this->filesystem->delete($file);
                continue;
            }

            $items[$id] = $item->value;
        }

        return $items;
    }

    /**
     * @inheritDoc
     *
     * @throws FileNotFoundException
     */
    #[Override]
    public function hasItem(string $key): bool
    {
        $file = $this->getFilePath($key);

        return $this->filesystem->exists($file) && ($this->filesystem->getLastModified($file) > time()
                                                    || (bool) $this->getItems([$key]));
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function clear(): bool
    {
        return $this->filesystem->clearDirectory($this->directory);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function deleteItems(array $keys): bool
    {
        $success = true;

        foreach ($keys as $id) {
            $file = $this->getFilePath($id);
            $success = (!$this->filesystem->exists($file) || $this->filesystem->delete($file)) && $success;
        }

        return $success;
    }

    /**
     * @inheritDoc
     *
     * @param array<string, mixed> $values
     *
     * @return bool|list<string>
     */
    #[Override]
    public function save(array $values, int $lifespan)
    {
        $success = true;
        $expiresAt = time() + ($lifespan ?: 31557600); // 31557600s = 1 year

        foreach ($values as $id => $value) {
            $success = $this->put($id, $value, $expiresAt) && $success;
        }

        return $success;
    }

    /**
     * Modified version to return a boolean when writing contents to the file
     */
    protected function put(string $id, mixed $value, ?int $expiresAt = null): bool
    {
        $file = $this->getFilePath($id, true);

        $item = new stdClass();
        $item->id = $id;
        $item->value = $value;
        $item->expiresAt = $expiresAt;
        $data = str_replace('stdClass::__set_state', '(object)', var_export($item, true));
        $contents = '<?php return ' . $data . ';';

        if (false === file_put_contents($this->tmp, $contents)) {
            return false;
        }

        if (null !== $expiresAt) {
            touch($this->tmp, $expiresAt);
        }

        if (rename($this->tmp, $file)) {
            return true;
        }

        $this->filesystem->delete($this->tmp);

        return false;
    }

    /**
     * Returns the full file path where to store the cache value.
     *
     *
     */
    protected function getFilePath(string $id, bool $force = false): string
    {
        $hash = str_replace('/', '-', base64_encode(hash('sha256', $this->directory . $id, true)));
        $directory = $this->directory . strtoupper($hash[0] . '/' . $hash[1] . '/');

        if ($force && !$this->filesystem->exists($directory)) {
            $this->filesystem->makeDirectory($directory, 0o777, true, true);
        }

        return $directory . substr($hash, 2, 20);
    }

    /**
     * @param string $directory
     */
    private function setCacheDirectory(Filesystem $filesystem, ?string $directory): void
    {
        $directory ??= sys_get_temp_dir() . '/univeros-cache/';

        if (!$filesystem->exists($directory)) {
            $filesystem->makeDirectory($directory, 0o777, true, true);
        }

        $path = realpath($directory);

        $path = false !== $path && $filesystem->exists($directory) ? $directory : false;

        if (false === $path) {
            throw new InvalidArgumentException(\sprintf('Cache directory does not exist "%s"', $directory));
        }

        if (!$filesystem->isWritable($path)) {
            throw new InvalidArgumentException(\sprintf('Cache directory is not writable "%s".', $path));
        }

        $path .= '/';
        if ('\\' === '/' && \strlen($path) > 234) { // windows allows max of 258
            throw new InvalidArgumentException(\sprintf('Cache directory path is too long "%s"', $path));
        }

        $this->directory = $path;
    }
}
