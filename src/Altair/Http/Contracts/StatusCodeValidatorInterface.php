<?php
namespace Altair\Http\Contracts;

interface StatusCodeValidatorInterface
{
    /**
     * Checks whether the response status code is valid or not.
     *
     * @param int $code
     *
     * @return bool
     */
    public function __invoke(int $code): bool;
}
