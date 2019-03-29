<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Contracts;

interface ReflectionCacheInterface
{
    const CLASSES_KEY_PREFIX = 'class.';
    const CONSTRUCTORS_KEY_PREFIX = 'const.';
    const CONSTRUCTOR_PARAMETERS_KEY_PREFIX = 'const-params.';
    const FUNCTIONS_KEY_PREFIX = 'func.';
    const FUNCTION_PARAMETERS_KEY_PREFIX = 'func-params.';
    const METHODS_KEY_PREFIX = 'method.';

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key);

    /**
     * @param string $key
     * @param $data
     *
     * @return ReflectionCacheInterface
     */
    public function put(string $key, $data): ReflectionCacheInterface;
}
