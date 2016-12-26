<?php
namespace Altair\Http\Middleware;

use Altair\Http\Contracts\CsrfTokenGeneratorInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Support\MimeType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CsrfMiddleware implements MiddlewareInterface
{
    public $mimeType;
    public $generator;

    public function __construct(MimeType $mimeType, CsrfTokenGeneratorInterface $generator = null)
    {
        $this->mimeType = $mimeType;
        $this->generator = $generator?? function (string $uri, ServerRequestInterface $request): array {
        };
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
    }
}
