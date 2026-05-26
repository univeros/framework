<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Configuration\Support;

class Env
{
    /**
     * Returns a specific environment value.
     *
     * @param string $name the environment value name
     *
     * @return null|string|mixed
     */
    public function get($name, mixed $default = null)
    {
        $value = match (true) {
            array_key_exists($name, $_ENV) => $_ENV[$name],
            array_key_exists($name, $_SERVER) => $_SERVER[$name],
            default => getenv($name),
        };

        return $value === false ? $default : $value;
    }
}
