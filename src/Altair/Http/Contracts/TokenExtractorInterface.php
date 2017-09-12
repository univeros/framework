<?php

namespace Altair\Http\Contracts;

use Psr\Http\Message\ServerRequestInterface;

interface TokenExtractorInterface
{
    /**
     * Returns the authorization token from the request (if any).
     *
     * @param ServerRequestInterface $request
     *
     * @return null|string
     */
    public function extract(ServerRequestInterface $request): ?string;
}
