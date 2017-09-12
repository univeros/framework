<?php

namespace Altair\Http\Contracts;

interface TokenGeneratorInterface
{
    /**
     * Generates a JWT authentication token string
     *
     * @param array $claims
     *
     * @return string
     */
    public function generate(array $claims = []): string;
}
