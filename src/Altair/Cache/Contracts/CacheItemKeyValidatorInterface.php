<?php
namespace Altair\Cache\Contracts;

interface CacheItemKeyValidatorInterface extends FailureReasonAwareInterface
{
    /**
     * Checks whether a cache key is valid and if not. If valid will return true, false otherwise.
     *
     * @param string $key
     *
     * @return bool
     */
    public function validate(string $key): bool;
}
