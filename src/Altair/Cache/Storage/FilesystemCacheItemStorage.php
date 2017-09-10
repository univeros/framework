<?php
namespace Altair\Cache\Storage;

use Altair\Cache\Contracts\CacheItemStorageInterface;
use Altair\Cache\Exception\InvalidArgumentException;
use Altair\Filesystem\Filesystem;

class FilesystemCacheItemStorage implements CacheItemStorageInterface
{
    protected $directory;
    protected $filesystem;
    protected $tmp;

    /**
     * FilesystemCacheItemPoolStorage constructor.
     *
     * @param Filesystem $filesystem
     * @param string|null $directory
     */
    public function __construct(Filesystem $filesystem, string $directory = null)
    {
        $directory = $directory?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'univeros-cache' . DIRECTORY_SEPARATOR;

        if (!$filesystem->exists($directory)) {
            $filesystem->makeDirectory($directory, 0777, true, true);
        }

        if (false === $directory = realpath($directory) ?: ($filesystem->exists($directory) ? $directory : false)) {
            throw new InvalidArgumentException(sprintf('Cache directory does not exist "%s"', $directory));
        }

        if (!$filesystem->isWritable($directory)) {
            throw new InvalidArgumentException(sprintf('Cache directory is not writtable "%s".', $directory));
        }

        $directory .= DIRECTORY_SEPARATOR;
        if ('\\' === DIRECTORY_SEPARATOR && strlen($directory) > 234) { // windows allows max of 258
            throw new InvalidArgumentException(sprintf('Cache directory path is too long "%s"', $directory));
        }
        $this->directory = $directory;
        $this->filesystem = $filesystem;
        $this->tmp = $this->directory . uniqid('', true);
    }

    /**
     * @inheritdoc
     */
    public function getMaxIdLength(): ?int
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getItems(array $keys = []): array
    {
        $items = [];
        $now = time();
        foreach ($keys as $id) {
            $file = $this->getFilePath($id);
            if (!$this->filesystem->exists($file) ||
                !$this->filesystem->isFile($file) ||
                !($item = $this->filesystem->getRequiredFileValue($file))
            ) {
                continue;
            }
            if ($now >= (int)$item->expiresAt) {
                $this->filesystem->delete($file);
                continue;
            }
            $items[$id] = $item->value;
        }

        return $items;
    }

    /**
     * @inheritdoc
     */
    public function hasItem(string $key): bool
    {
        $file = $this->getFilePath($key);

        return $this->filesystem->exists($file) &&
            (@$this->filesystem->getLastModified($file) > time() || (bool)$this->getItems([$key]));
    }

    /**
     * @inheritdoc
     */
    public function clear(): bool
    {
        return @$this->filesystem->clearDirectory($this->directory);
    }

    /**
     * @inheritdoc
     */
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
     * @inheritdoc
     */
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
     *
     * @param string $id
     * @param $value
     * @param int|null $expiresAt
     *
     * @return bool
     */
    protected function put(string $id, $value, int $expiresAt = null): bool
    {
        $file = $this->getFilePath($id, true);

        $item = new \stdClass();
        $item->id = $id;
        $item->value = $value;
        $item->expiresAt = $expiresAt;
        $data = str_replace('stdClass::__set_state', '(object)', var_export($item, true));
        $contents = '<?php return ' . $data . ';';

        if (false === @file_put_contents($this->tmp, $contents)) {
            return false;
        }
        if (null !== $expiresAt) {
            @touch($this->tmp, $expiresAt);
        }
        if (@rename($this->tmp, $file)) {
            return true;
        }
        $this->filesystem->delete($this->tmp);

        return false;
    }

    /**
     * Returns the full file path where to store the cache value.
     *
     * @param string $id
     * @param bool $force
     *
     * @return string
     */
    protected function getFilePath(string $id, bool $force = false): string
    {
        $hash = str_replace('/', '-', base64_encode(hash('sha256', $this->directory . $id, true)));
        $directory = $this->directory . strtoupper($hash[0] . DIRECTORY_SEPARATOR . $hash[1] . DIRECTORY_SEPARATOR);

        if ($force && !$this->filesystem->exists($directory)) {
            $this->filesystem->makeDirectory($directory, 0777, true, true);
        }

        return $directory . substr($hash, 2, 20);
    }
}
