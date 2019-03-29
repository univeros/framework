<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Common\Contracts;

interface RegistryInterface
{
    /**
     * Returns a configuration key from the settings or $default if key is not found.
     *
     * @param string $key
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Sets a value into the registry
     *
     * @param string $key
     * @param mixed $value
     *
     * @return static
     */
    public function set(string $key, $value);
}
