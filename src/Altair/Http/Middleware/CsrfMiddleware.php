<?php
namespace Altair\Http\Middleware;


use Altair\Http\Contracts\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CsrfMiddleware implements MiddlewareInterface
{
    // TODO
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        // TODO: Implement __invoke() method.
    }


}
