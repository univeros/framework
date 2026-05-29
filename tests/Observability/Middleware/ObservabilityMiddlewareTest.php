<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observability\Middleware;

use Altair\Observability\Metrics\Meter;
use Altair\Observability\Middleware\ObservabilityMiddleware;
use Altair\Observability\Recorder\InMemoryRecorder;
use Altair\Observability\Trace\SpanKind;
use Altair\Observability\Trace\SpanStatus;
use Altair\Observability\Trace\Tracer;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

#[CoversClass(ObservabilityMiddleware::class)]
final class ObservabilityMiddlewareTest extends TestCase
{
    public function testSuccessfulRequestRecordsAServerSpanAndACounter(): void
    {
        $recorder = new InMemoryRecorder();
        $middleware = new ObservabilityMiddleware(new Tracer($recorder), new Meter($recorder));

        $middleware->process(
            new ServerRequest([], [], '/users', 'GET'),
            $this->handler(new Response(body: 'ok', status: 200)),
        );

        $span = $recorder->spans()[0];
        self::assertSame('HTTP GET', $span->name);
        self::assertSame(SpanKind::Server, $span->kind);
        self::assertSame(SpanStatus::Ok, $span->status);
        self::assertSame(200, $span->attributes['http.response.status_code']);
        self::assertSame('GET', $span->attributes['http.request.method']);

        $point = $recorder->metrics()[0];
        self::assertSame('http.server.requests', $point->name);
        self::assertSame(1.0, $point->value);
        self::assertSame(200, $point->attributes['http.status_code']);
    }

    public function testFiveHundredResponseMarksTheSpanAsError(): void
    {
        $recorder = new InMemoryRecorder();
        $middleware = new ObservabilityMiddleware(new Tracer($recorder));

        $middleware->process(
            new ServerRequest([], [], '/boom', 'GET'),
            $this->handler(new Response(body: 'php://memory', status: 503)),
        );

        self::assertSame(SpanStatus::Error, $recorder->spans()[0]->status);
    }

    public function testThrownExceptionMarksTheSpanAsErrorAndRethrows(): void
    {
        $recorder = new InMemoryRecorder();
        $middleware = new ObservabilityMiddleware(new Tracer($recorder));

        self::expectException(RuntimeException::class);
        try {
            $middleware->process(
                new ServerRequest([], [], '/explode', 'POST'),
                $this->throwingHandler(new RuntimeException('boom')),
            );
        } finally {
            $span = $recorder->spans()[0];
            self::assertSame(SpanStatus::Error, $span->status);
            self::assertSame('RuntimeException', $span->attributes['exception.type']);
            self::assertSame('boom', $span->attributes['exception.message']);
        }
    }

    private function handler(ResponseInterface $response): RequestHandlerInterface
    {
        return new readonly class($response) implements RequestHandlerInterface {
            public function __construct(private ResponseInterface $response) {}

            #[Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };
    }

    private function throwingHandler(\Throwable $throwable): RequestHandlerInterface
    {
        return new readonly class($throwable) implements RequestHandlerInterface {
            public function __construct(private \Throwable $throwable) {}

            #[Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw $this->throwable;
            }
        };
    }
}
