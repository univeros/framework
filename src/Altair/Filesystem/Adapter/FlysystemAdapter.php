<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Filesystem\Adapter;

use Altair\Filesystem\Contracts\FilesystemAdapterInterface;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\Handler;
use League\Flysystem\PluginInterface;

/**
 * Filesystem
 *
 * Adopted adapter functionality from Flysystem
 *
 * @method FilesystemInterface addPlugin(PluginInterface $plugin)
 * @method void assertAbsent($path)
 * @method void assertPresent($path)
 * @method boolean copy($path, $newpath)
 * @method boolean createDir($dirname, array $config = [])
 * @method boolean delete($path)
 * @method boolean deleteDir($dirname)
 * @method Handler get($path, Handler $handler = null)
 * @method AdapterInterface getAdapter()
 * @method Config getConfig()
 * @method array|false getMetadata($path)
 * @method string|false getMimetype($path)
 * @method integer|false getSize($path)
 * @method integer|false getTimestamp($path)
 * @method string|false getVisibility($path)
 * @method array getWithMetadata($path, array $metadata)
 * @method boolean has($path)
 * @method array listContents($directory = '', $recursive = false)
 * @method array listFiles($path = '', $recursive = false)
 * @method array listPaths($path = '', $recursive = false)
 * @method array listWith(array $keys = [], $directory = '', $recursive = false)
 * @method boolean put($path, $contents, array $config = [])
 * @method boolean putStream($path, $resource, array $config = [])
 * @method string|false read($path)
 * @method string|false readAndDelete($path)
 * @method resource|false readStream($path)
 * @method boolean rename($path, $newpath)
 * @method boolean setVisibility($path, $visibility)
 * @method boolean update($path, $contents, array $config = [])
 * @method boolean updateStream($path, $resource, array $config = [])
 * @method boolean write($path, $contents, array $config = [])
 * @method boolean writeStream($path, $resource, array $config = [])
 *
 */
class FlysystemAdapter implements FilesystemAdapterInterface
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
     * Pass dynamic methods call onto Flysystem
     *
     * @param  string $method
     * @param  array $parameters
     *
     * @throws \BadMethodCallException
     * @return mixed
     *
     */
    public function __call($method, array $parameters)
    {
        return call_user_func_array([$this->driver, $method], $parameters);
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
                    if ($obj['type'] === 'dir') {
                        return $obj['path'];
                    }
                },
                $contents
            )
        );
    }
}
