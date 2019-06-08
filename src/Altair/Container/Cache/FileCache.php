<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Cache;

use Altair\Container\Contracts\ReflectionCacheInterface;

/**
 * FileCache
 *
 * Important: Make sure you have opcache configured
 * @see https://blog.graphiq.com/500x-faster-caching-than-redis-memcache-apc-in-php-hhvm-dcd26e8447ad#.g7g64ela5
 */
class FileCache implements ReflectionCacheInterface
{
    protected $path;

    /**
     * FileCache constructor.
     *
     * @param string|null $path
     */
    public function __construct(string $path = null)
    {
        $this->path = $path?? sys_get_temp_dir();
    }

    /**
     * @inheritDoc
     */
    public function get(string $key)
    {
        // Multiple calls of ‘include’ do not check for file modification
        // https://github.com/facebook/hhvm/issues/4797
        $path = "{$this->path}/{$key}";
        $value = file_exists($path) ? require $path : null;

        return $value?? false;
    }

    /**
     * @inheritDoc
     */
    public function put(string $key, $data): ReflectionCacheInterface
    {
        $value = var_export($data, true);
        // HHVM fails at __set_state, so just use object cast for now
        $val = str_replace('stdClass::__set_state', '(object)', $value);
        file_put_contents("{$this->path}/{$key}", '<?php return ' . $val . ';');

        return $this;
    }
}
