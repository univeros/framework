<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Middleware;

use Altair\Http\Exception\HttpBadRequestException;
use Altair\Http\Middleware\JsonContentMiddleware;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class JsonContentMiddlewareTest extends AbstractMiddlewareTest
{
    public function testValidJsonBodyIsParsedIntoRequest(): void
    {
        $captured = null;
        $body = new Stream('php://temp', 'r+');
        $body->write('{"name":"alice","age":30}');

        $request = (new ServerRequest())
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);

        $this->dispatch([new JsonContentMiddleware(), $this->captureParsedBody($captured)], $request);

        $this->assertSame(['name' => 'alice', 'age' => 30], $captured);
    }

    public function testNonMatchingContentTypeIsPassedThroughWithoutParsing(): void
    {
        $captured = 'unchanged';
        $body = new Stream('php://temp', 'r+');
        $body->write('not-json');

        $request = (new ServerRequest())
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($body);

        $this->dispatch([new JsonContentMiddleware(), $this->captureParsedBody($captured)], $request);

        $this->assertNull($captured); // ServerRequest default parsedBody is null
    }

    public function testEmptyBodyIsNotParsed(): void
    {
        $captured = 'unchanged';
        $request = (new ServerRequest())->withHeader('Content-Type', 'application/json');

        $this->dispatch([new JsonContentMiddleware(), $this->captureParsedBody($captured)], $request);

        $this->assertNull($captured);
    }

    public function testMalformedJsonThrowsBadRequest(): void
    {
        $body = new Stream('php://temp', 'r+');
        $body->write('{not valid json}');

        $request = (new ServerRequest())
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);

        $this->expectException(HttpBadRequestException::class);

        $this->dispatch([new JsonContentMiddleware(), $this->okHandler()], $request);
    }

    public function testObjectMode(): void
    {
        $captured = null;
        $body = new Stream('php://temp', 'r+');
        $body->write('{"k":"v"}');

        $request = (new ServerRequest())
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);

        $this->dispatch([new JsonContentMiddleware(associative: false), $this->captureParsedBody($captured)], $request);

        $this->assertIsObject($captured);
        $this->assertSame('v', $captured->k);
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
