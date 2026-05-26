<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Middleware;

use Altair\Http\Middleware\FormContentMiddleware;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FormContentMiddlewareTest extends AbstractMiddlewareTest
{
    public function testUrlencodedBodyIsParsedIntoRequest(): void
    {
        $captured = null;
        $body = new Stream('php://temp', 'r+');
        $body->write('name=alice&age=30');

        $request = (new ServerRequest())
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($body);

        $this->dispatch([new FormContentMiddleware(), $this->captureParsedBody($captured)], $request);

        $this->assertSame(['name' => 'alice', 'age' => '30'], $captured);
    }

    public function testNonMatchingContentTypeIsPassedThroughWithoutParsing(): void
    {
        $captured = 'unchanged';
        $body = new Stream('php://temp', 'r+');
        $body->write('name=alice');

        $request = (new ServerRequest())
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);

        $this->dispatch([new FormContentMiddleware(), $this->captureParsedBody($captured)], $request);

        $this->assertNull($captured);
    }

    public function testContentTypeWithCharsetMatches(): void
    {
        $captured = null;
        $body = new Stream('php://temp', 'r+');
        $body->write('k=v');

        $request = (new ServerRequest())
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded; charset=utf-8')
            ->withBody($body);

        $this->dispatch([new FormContentMiddleware(), $this->captureParsedBody($captured)], $request);

        $this->assertSame(['k' => 'v'], $captured);
    }

    private function captureParsedBody(mixed &$capture): PsrMiddlewareInterface
    {
        return new class ($capture) implements PsrMiddlewareInterface {
            public function __construct(private mixed &$capture)
            {
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->capture = $request->getParsedBody();

                return new Response('php://temp', 200);
            }
        };
    }
}
