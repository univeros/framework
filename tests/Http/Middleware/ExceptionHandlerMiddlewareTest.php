<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Middleware;

use Altair\Http\Contracts\ErrorHandlerInterface;
use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Exception\HttpMethodNotAllowedException;
use Altair\Http\Exception\HttpNotFoundException;
use Altair\Http\Middleware\ExceptionHandlerMiddleware;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Stringable;

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

    public function testThrownHttpExceptionRendersItsOwnStatus(): void
    {
        $middleware = new ExceptionHandlerMiddleware(new ResponseFactory(), $this->passthroughHandler(), capture: true);

        $response = $this->dispatch(
            [$middleware, $this->handlerThrowing(new HttpNotFoundException('/'))],
            new ServerRequest(),
        );

        $this->assertSame(HttpStatusCodeInterface::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testThrownMethodNotAllowedAppliesAllowHeader(): void
    {
        $middleware = new ExceptionHandlerMiddleware(new ResponseFactory(), $this->passthroughHandler(), capture: true);

        $response = $this->dispatch(
            [$middleware, $this->handlerThrowing(new HttpMethodNotAllowedException(['GET', 'POST'], 'nope', 405))],
            new ServerRequest(),
        );

        $this->assertSame(HttpStatusCodeInterface::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
        $this->assertSame('GET,POST', $response->getHeaderLine('Allow'));
    }

    public function testServerErrorsAreLogged(): void
    {
        $logger = $this->spyLogger();
        $middleware = new ExceptionHandlerMiddleware(
            new ResponseFactory(),
            $this->passthroughHandler(),
            capture: true,
            logger: $logger,
        );

        $this->dispatch(
            [$middleware, $this->handlerThrowing(new RuntimeException('boom'))],
            new ServerRequest(),
        );

        $this->assertCount(1, $logger->records);
        $this->assertSame('boom', $logger->records[0]['message']);
        $this->assertSame(500, $logger->records[0]['context']['status']);
    }

    public function testClientErrorsAreNotLogged(): void
    {
        $logger = $this->spyLogger();
        $middleware = new ExceptionHandlerMiddleware(
            new ResponseFactory(),
            $this->passthroughHandler(),
            capture: true,
            logger: $logger,
        );

        $this->dispatch(
            [$middleware, $this->handlerThrowing(new HttpNotFoundException('/'))],
            new ServerRequest(),
        );

        $this->assertSame([], $logger->records);
    }

    private function passthroughHandler(): ErrorHandlerInterface
    {
        return new class () implements ErrorHandlerInterface {
            public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
            {
                return $response;
            }
        };
    }

    /**
     * @return AbstractLogger&object{records: list<array{message: string, context: array<string, mixed>}>}
     */
    private function spyLogger(): LoggerInterface
    {
        return new class () extends AbstractLogger {
            /** @var list<array{message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['message' => (string) $message, 'context' => $context];
            }
        };
    }

    private function handlerReturning(int $code): PsrMiddlewareInterface
    {
        return new readonly class ($code) implements PsrMiddlewareInterface {
            public function __construct(private int $code)
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
        return new readonly class ($e) implements PsrMiddlewareInterface {
            public function __construct(private \Throwable $e)
            {
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                throw $this->e;
            }
        };
    }
}
