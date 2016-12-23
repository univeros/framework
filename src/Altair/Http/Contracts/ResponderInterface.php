<?php
namespace Altair\Http\Contracts;

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
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        PayloadInterface $payload
    ): ResponseInterface;
}
