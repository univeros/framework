<?php
namespace Altair\Http\Contracts;

use Psr\Http\Message\ResponseInterface;

interface CacheLimiterInterface
{
    const EXPIRED = 'Thu, 19 Nov 1981 08:52:00 GMT';

    /**
     * Implements cache limiter to the response message
     *
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function apply(ResponseInterface $response): ResponseInterface;
}
