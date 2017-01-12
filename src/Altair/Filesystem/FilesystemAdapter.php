<?php
namespace Altair\Filesystem;

use Altair\Filesystem\Contracts\FilesystemAdapterInterface;
use League\Flysystem\FilesystemInterface;

/**
 * Filesystem
 *
 * Adopted adapter functionality from Flysystem
 *
 * @method \League\Flysystem\FilesystemInterface addPlugin(\League\Flysystem\PluginInterface $plugin)
 * @method void assertAbsent(string $path)
 * @method void assertPresent(string $path)
 * @method boolean copy(string $path, string $newpath)
 * @method boolean createDir(string $dirname, array $config = null)
 * @method boolean delete(string $path)
 * @method boolean deleteDir(string $dirname)
 * @method \League\Flysystem\Handler get(string $path, \League\Flysystem\Handler $handler = null)
 * @method \League\Flysystem\AdapterInterface getAdapter()
 * @method \League\Flysystem\Config getConfig()
 * @method array|false getMetadata(string $path)
 * @method string|false getMimetype(string $path)
 * @method integer|false getSize(string $path)
 * @method integer|false getTimestamp(string $path)
 * @method string|false getVisibility(string $path)
 * @method array getWithMetadata(string $path, array $metadata)
 * @method boolean has(string $path)
 * @method array listContents(string $directory = '', boolean $recursive = false)
 * @method array listFiles(string $path = '', boolean $recursive = false)
 * @method array listPaths(string $path = '', boolean $recursive = false)
 * @method array listWith(array $keys = [], $directory = '', $recursive = false)
 * @method boolean put(string $path, string $contents, array $config = [])
 * @method boolean putStream(string $path, resource $resource, array $config = [])
 * @method string|false read(string $path)
 * @method string|false readAndDelete(string $path)
 * @method resource|false readStream(string $path)
 * @method boolean rename(string $path, string $newpath)
 * @method boolean setVisibility(string $path, string $visibility)
 * @method boolean update(string $path, string $contents, array $config = [])
 * @method boolean updateStream(string $path, resource $resource, array $config = [])
 * @method boolean write(string $path, string $contents, array $config = [])
 * @method boolean writeStream(string $path, resource $resource, array $config = [])
 *
 */
class FilesystemAdapter implements FilesystemAdapterInterface
{
    /**
     * @var FilesystemInterface
     */
    protected $driver;

    /**
     * FlySystem constructor.
     *
     * @param FilesystemInterface $driver
     */
    public function __construct(FilesystemInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * Get the Flysystem driver.
     *
     * @return \League\Flysystem\FilesystemInterface
     */
    public function getDriver(): FilesystemInterface
    {
        return $this->driver;
    }

    /**
     * Determine if a file exists.
     *
     * @param  string $path
     *
     * @return bool
     */
    public function exists($path): bool
    {
        return $this->driver->has($path);
    }

    /**
     * Prepend to a file.
     *
     * @param  string $path
     * @param  string $data
     * @param  string $separator
     *
     * @return bool
     */
    public function prepend(string $path, string $data, string $separator = PHP_EOL): bool
    {
        if ($this->exists($path)) {
            return $this->driver->put($path, $data . $separator . $this->driver->read($path));
        }

        return $this->driver->put($path, $data);
    }

    /**
     * Append to a file.
     *
     * @param  string $path
     * @param  string $data
     * @param  string $separator
     *
     * @return bool
     */
    public function append(string $path, string $data, string $separator = PHP_EOL): bool
    {
        if ($this->exists($path)) {
            return $this->driver->put($path, $this->driver->read($path) . $separator . $data);
        }

        return $this->put($path, $data);
    }


    /**
     * @param string $directory
     * @param bool $recursive
     *
     * @return array
     */
    public function listDirectories(string $directory = '', bool $recursive = false): array
    {
        $contents = $this->driver->listContents($directory, $recursive);

        return array_filter(
            array_map(
                function ($obj) {
                    if ($obj['type'] == 'dir') {
                        return $obj['path'];
                    }
                },
                $contents
            )
        );
    }

    /**
     * Pass dynamic methods call onto Flysystem
     *
     * @param  string $method
     * @param  array $parameters
     *
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, array $parameters)
    {
        return call_user_func_array([$this->driver, $method], $parameters);
    }
}
