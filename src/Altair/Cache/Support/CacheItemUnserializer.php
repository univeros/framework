<?php

namespace Altair\Cache\Support;

use DomainException;
use Error;
use ErrorException;

class CacheItemUnserializer
{
    const BOOL_FALSE = 'b:0;';

    /**
     * Replaces native unserialize() to be able to throw an exception if the value cannot be instantiated as a class or
     * if we are not successful on the process.
     *
     * @param string $value
     *
     * @return mixed
     * @throws ErrorException
     */
    public static function unserialize(string $value)
    {
        if (0 === (static::BOOL_FALSE <=> $value)) {
            return false;
        }
        $handler = ini_set('unserialize_callback_func', __CLASS__ . '::handleUnserializeCallback');
        try {
            if (false !== ($value = unserialize($value))) {
                return $value;
            }
            throw new DomainException('Failed to unserialize cached value.');
        } catch (Error $e) { // ensure error is an instance of \Exception
            throw new ErrorException($e->getMessage(), $e->getCode(), E_ERROR, $e->getFile(), $e->getLine());
        } finally { // reset
            ini_set('unserialize_callback_func', $handler);
        }
    }

    /**
     * If a class has not being defined, throw an error.
     *
     * @param $class
     */
    public static function handleUnserializeCallback($class)
    {
        throw new DomainException('Class not found: ' . $class);
    }
}
