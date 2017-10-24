<?php
namespace Altair\Http\Contracts;

use Altair\Http\Exception\InvalidArgumentException;
use Altair\Http\Exception\OutOfBoundsException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface ResponderInterface
{
    /**
     * Handle marshalling a payload into an HTTP response.
     *
     * @param  ServerRequestInterface $request
     * @param  ResponseInterface $response
     * @param  PayloadInterface $payload
     *
     * @return ResponseInterface
     *
     * @throws InvalidArgumentException If the requested $statusText is not valid
     * @throws OutOfBoundsException     If not status code is found
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        PayloadInterface $payload
    ): ResponseInterface;
}
