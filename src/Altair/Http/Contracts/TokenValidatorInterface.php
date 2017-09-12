<?php

namespace Altair\Http\Contracts;

interface TokenValidatorInterface
{
    /**
     * Validates whether the given token string is valid or not. Should work in conjunction with TokenBuilderInterface.
     *
     * @param string $token
     *
     * @return bool
     */
    public function validate(string $token): bool;
}
