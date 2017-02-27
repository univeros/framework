<?php
namespace Altair\Http\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface MiddlewareInterface
{
    const ATTRIBUTE_IP_ADDRESS = 'altair:http:ip-address';
    const ATTRIBUTE_ACTION = 'altair:http:action';
    const ATTRIBUTE_FORMAT = 'altair:http:format';
    const ATTRIBUTE_CSRF_HEADER = 'X-XSRF-TOKEN'; // TODO: validate CSRF tokens from headers.

    /**
     * Relay Middleware capable invokable class method.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     *
     * @return mixed
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next);
}
