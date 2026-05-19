<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Middleware;

use Laminas\Diactoros\Response;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Laminas\Diactoros\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Relay\Relay;

abstract class AbstractMiddlewareTest extends TestCase
{
    /**
     * @param array<string, string|array<string>> $headers
     * @param array<string, mixed>                $server
     */
    protected function request(string $uri = '', array $headers = [], array $server = []): ServerRequest
    {
        return (new ServerRequest($server, [], $uri, null, 'php://temp', $headers))
            ->withUri(new Uri($uri));
    }

    /**
     * @param array<string, string|array<string>> $headers
     */
    protected function response(array $headers = []): Response
    {
        return new Response('php://temp', 200, $headers);
    }

    protected function stream(string $content = ''): Stream
    {
        $stream = new Stream('php://temp', 'r+');
        if ($content !== '') {
            $stream->write($content);
        }

        return $stream;
    }

    protected function responseFactory(): ResponseFactoryInterface
    {
        return new ResponseFactory();
    }

    /**
     * Dispatch a PSR-15 middleware pipeline against a request, terminating with a no-op handler that returns 200.
     *
     * @param list<MiddlewareInterface> $middlewares
     */
    protected function dispatch(array $middlewares, ServerRequestInterface $request): ResponseInterface
    {
        $relay = new Relay([...$middlewares, $this->terminalHandler()]);

        return $relay->handle($request);
    }

    /**
     * @param list<MiddlewareInterface> $middlewares
     * @param array<string, string|array<string>> $headers
     */
    protected function execute(array $middlewares, string $url = '', array $headers = []): ResponseInterface
    {
        return $this->dispatch($middlewares, $this->request($url, $headers));
    }

    private function terminalHandler(): MiddlewareInterface
    {
        return new class () implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                return new Response('php://temp', 200);
            }
        };
    }
}
