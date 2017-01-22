<?php
namespace Altair\Cache\Contracts;

interface CacheItemTagValidatorInterface extends FailureReasonAwareInterface
{
    /**
     * Checks whether a cache tag is valid and if not. If valid will return true, false otherwise.
     *
     * @param string $tag
     *
     * @return bool
     */
    public function validate(string $tag): bool;
}
