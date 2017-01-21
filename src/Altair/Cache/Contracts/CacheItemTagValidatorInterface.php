<?php
namespace Altair\Cache\Contracts;

interface CacheItemTagValidatorInterface
{
    /**
     * Checks whether a cache tag is valid and if not. If valid will return true, false otherwise and will set the error
     * to the second parameter passed by reference.
     *
     * @param string $tag
     * @param string $reason
     *
     * @return bool
     */
    public function validate(string $tag, string &$reason): bool;
}
