<?php
namespace Altair\Cache\Contracts;

interface CacheItemKeyValidatorInterface
{
    /**
     * Checks whether a cache key is valid and if not. If valid will return true, false otherwise and will set the error
     * to the second parameter passed by reference.
     *
     * @param string $key
     * @param string $reason
     *
     * @return bool
     */
    public function validate(string $key, string &$reason): bool;
}
