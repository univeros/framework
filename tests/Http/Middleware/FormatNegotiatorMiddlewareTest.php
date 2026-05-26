<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Middleware;

use Altair\Http\Contracts\FormatNegotiatorInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Middleware\FormatNegotiatorMiddleware;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FormatNegotiatorMiddlewareTest extends AbstractMiddlewareTest
{
    public function testFormatFromUriPathTakesPrecedenceOverHeader(): void
    {
        $negotiator = $this->createStub(FormatNegotiatorInterface::class);
        $negotiator->method('getFromServerRequestUriPath')->willReturn('json');
        $negotiator->method('getContentTypeByFormat')->with('json')->willReturn('application/json');

        $captured = null;
        $middleware = new FormatNegotiatorMiddleware($negotiator);

        $this->dispatch([$middleware, $this->captureFormat($captured)], new ServerRequest());

        $this->assertSame('json', $captured);
    }

    public function testFallsBackToHeaderWhenUriPathYieldsNothing(): void
    {
        $negotiator = $this->createStub(FormatNegotiatorInterface::class);
        $negotiator->method('getFromServerRequestUriPath')->willReturn(null);
        $negotiator->method('getFromServerRequestHeaderLine')->willReturn('xml');
        $negotiator->method('getContentTypeByFormat')->with('xml')->willReturn('application/xml');

        $captured = null;
        $middleware = new FormatNegotiatorMiddleware($negotiator);

        $this->dispatch([$middleware, $this->captureFormat($captured)], new ServerRequest());

        $this->assertSame('xml', $captured);
    }

    public function testContentTypeHeaderIsAddedWhenHandlerOmitsIt(): void
    {
        $negotiator = $this->createStub(FormatNegotiatorInterface::class);
        $negotiator->method('getFromServerRequestUriPath')->willReturn('json');
        $negotiator->method('getContentTypeByFormat')->willReturn('application/json');

        $middleware = new FormatNegotiatorMiddleware($negotiator);

        $response = $this->dispatch([$middleware, $this->okHandler()], new ServerRequest());

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testHandlerSuppliedContentTypeIsPreserved(): void
    {
        $negotiator = $this->createStub(FormatNegotiatorInterface::class);
        $negotiator->method('getFromServerRequestUriPath')->willReturn('json');
        $negotiator->method('getContentTypeByFormat')->willReturn('application/json');

        $handlerSettingExplicitType = new class () implements PsrMiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return (new Response('php://temp', 200))->withHeader('Content-Type', 'text/plain');
            }
        };

        $middleware = new FormatNegotiatorMiddleware($negotiator);

        $response = $this->dispatch([$middleware, $handlerSettingExplicitType], new ServerRequest());

        $this->assertSame('text/plain', $response->getHeaderLine('Content-Type'));
    }

    private function captureFormat(?string &$capture): PsrMiddlewareInterface
    {
        return new class ($capture) implements PsrMiddlewareInterface {
            public function __construct(private ?string &$capture)
            {
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->capture = $request->getAttribute(MiddlewareInterface::ATTRIBUTE_FORMAT);

                return new Response('php://temp', 200);
            }
        };
    }

    private function okHandler(): PsrMiddlewareInterface
    {
        return new class () implements PsrMiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new Response('php://temp', 200);
            }
        };
    }
}
