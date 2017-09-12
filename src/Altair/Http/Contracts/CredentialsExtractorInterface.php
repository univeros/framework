<?php

namespace Altair\Http\Contracts;

use Psr\Http\Message\ServerRequestInterface;

interface CredentialsExtractorInterface
{
    /**
     * Returns the credentials within the request (if any).
     *
     * @param ServerRequestInterface $request
     *
     * @return array|null
     */
    public function extract(ServerRequestInterface $request): ?array;
}
