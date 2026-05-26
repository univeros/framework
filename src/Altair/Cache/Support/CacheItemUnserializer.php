<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cache\Support;

use Altair\Cache\Exception\UnserializeException;
use Error;
use ErrorException;

class CacheItemUnserializer
{
    public const BOOL_FALSE = 'b:0;';

    /**
     * Replaces native unserialize() to be able to throw an exception if the value cannot be instantiated as a class or
     * if we are not successful on the process.
     *
     *
     * @throws ErrorException
     * @return mixed
     */
    public static function unserialize(string $value)
    {
        if (0 === (static::BOOL_FALSE <=> $value)) {
            return false;
        }

        $handler = ini_set('unserialize_callback_func', self::class . '::handleUnserializeCallback');
        try {
            if (false !== ($value = unserialize($value, ['allowed_classes' => true]))) {
                return $value;
            }

            throw new UnserializeException('Failed to unserialize cached value.');
        } catch (Error $error) { // ensure error is an instance of \Exception
            throw new ErrorException($error->getMessage(), $error->getCode(), E_ERROR, $error->getFile(), $error->getLine());
        } finally { // reset
            ini_set('unserialize_callback_func', $handler);
        }
    }

    /**
     * If a class has not being defined, throw an error.
     *
     * @param $class
     */
    public static function handleUnserializeCallback(string $class): void
    {
        throw new UnserializeException('Class not found: ' . $class);
    }
}
