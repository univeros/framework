<?php

namespace Altair\Http\Contracts;

interface TokenInterface
{
    const TOKEN_KEY = 'altair:http:token';

    /**
     * @return string
     */
    public function getToken(): string;

    /**
     * Returns a value from its metadata if any.
     *
     * @param string|null $key
     *
     * @return mixed
     */
    public function getMetadata(string $key = null);
}
