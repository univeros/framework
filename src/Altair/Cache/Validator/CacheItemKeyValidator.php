<?php
namespace Altair\Cache\Validator;

use Altair\Cache\Contracts\CacheItemKeyValidatorInterface;
use Altair\Cache\Traits\FailureReasonAwareTrait;

class CacheItemKeyValidator implements CacheItemKeyValidatorInterface
{
    use FailureReasonAwareTrait;

    /**
     * @inheritdoc
     */
    public function validate(string $key): bool
    {
        if (!is_string($key)) {
            $this->reason = sprintf(
                'Cache key must be string, "%s" given.',
                is_object($key) ? get_class($key) : gettype($key)
            );

            return false;
        }
        if (!isset($key[0])) {
            $this->reason = 'Cache key must be greater than zero.';
            return false;
        }
        if (false !== strpbrk($key, '{}()/\@:')) {
            $this->reason = sprintf('The key %s is invalid. It contains one ore more reserved characters {}()/\@:', $key);
            return false;
        }
        return true;
    }
}
