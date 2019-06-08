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
    public const CLASSES_KEY_PREFIX = 'class.';
    public const CONSTRUCTORS_KEY_PREFIX = 'const.';
    public const CONSTRUCTOR_PARAMETERS_KEY_PREFIX = 'const-params.';
    public const FUNCTIONS_KEY_PREFIX = 'func.';
    public const FUNCTION_PARAMETERS_KEY_PREFIX = 'func-params.';
    public const METHODS_KEY_PREFIX = 'method.';

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $key);

    /**
     * @param string $key
     * @param mixed $data
     *
     * @return ReflectionCacheInterface
     */
    public function put(string $key, $data): ReflectionCacheInterface;
}
