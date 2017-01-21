<?php
namespace Altair\Cache\Validator;

use Altair\Cache\Contracts\CacheItemKeyValidatorInterface;

class CacheItemKeyValidator implements CacheItemKeyValidatorInterface
{
    public function validate(string $key, string &$reason): bool
    {
        if (!is_string($key)) {
            $reason = sprintf(
                'Cache key must be string, "%s" given.',
                is_object($key) ? get_class($key) : gettype($key)
            );

            return false;
        }
        if (!isset($key[0])) {
            $reason = 'Cache key must be greater than zero.';
            return false;
        }
        if (false !== strpbrk($key, '{}()/\@:')) {
            $reason = sprintf('The key %s is invalid. It contains one ore more reserved characters {}()/\@:', $key);
            return false;
        }
        return true;
    }
}
