<?php

namespace Altair\Http\Contracts;

use Altair\Http\Exception\InvalidTokenException;

interface TokenParserInterface
{
    /**
     * Parses a token string and returns a Token instance.
     *
     * @param string $token
     *
     * @return TokenInterface
     *
     * @throws InvalidTokenException
     */
    public function parse(string $token): TokenInterface;
}
