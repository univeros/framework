<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Middleware;

use Altair\Http\Contracts\ErrorHandlerInterface;
use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Middleware\ExceptionHandlerMiddleware;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

class ExceptionHandlerMiddlewareTest extends AbstractMiddlewareTest
{
    public function testSuccessfulResponsePassesThrough(): void
    {
        $middleware = new ExceptionHandlerMiddleware(new ResponseFactory());

        $response = $this->dispatch(
            [$middleware, $this->handlerReturning(200)],
            new ServerRequest(),
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testErrorStatusCodeIsRoutedThroughTheErrorHandler(): void
    {
        $handler = $this->createMock(ErrorHandlerInterface::class);
        $handler->expects($this->once())
            ->method('__invoke')
            ->willReturn(new Response('php://temp', 500));

        $middleware = new ExceptionHandlerMiddleware(new ResponseFactory(), $handler);

        $response = $this->dispatch(
            [$middleware, $this->handlerReturning(500)],
            new ServerRequest(),
        );

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testUncapturedExceptionsBubble(): void
    {
        $middleware = new ExceptionHandlerMiddleware(new ResponseFactory(), capture: false);

        $this->expectException(RuntimeException::class);

        $this->dispatch(
            [$middleware, $this->handlerThrowing(new RuntimeException('boom'))],
            new ServerRequest(),
        );
    }

    public function testCapturedExceptionsBecomeResponses(): void
    {
        $captured = null;
        $errorHandler = new class () implements ErrorHandlerInterface {
            public ?ServerRequestInterface $seen = null;

            public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
            {
                $this->seen = $request;

                return $response;
            }
        };

        $middleware = new ExceptionHandlerMiddleware(new ResponseFactory(), $errorHandler, capture: true);

        $response = $this->dispatch(
            [$middleware, $this->handlerThrowing(new RuntimeException('boom'))],
            new ServerRequest(),
        );

        $this->assertSame(HttpStatusCodeInterface::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertInstanceOf(RuntimeException::class,
            $errorHandler->seen->getAttribute(MiddlewareInterface::ATTRIBUTE_EXCEPTION));
    }

    private function handlerReturning(int $code): PsrMiddlewareInterface
    {
        return new class ($code) implements PsrMiddlewareInterface {
            public function __construct(private readonly int $code)
            {
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new Response('php://temp', $this->code);
            }
        };
    }

    private function handlerThrowing(\Throwable $e): PsrMiddlewareInterface
    {
        return new class ($e) implements PsrMiddlewareInterface {
            public function __construct(private readonly \Throwable $e)
            {
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                throw $this->e;
            }
        };
    }
}
