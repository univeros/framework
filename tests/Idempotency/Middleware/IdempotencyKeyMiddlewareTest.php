<?php

declare(strict_types=1);

namespace Altair\Tests\Idempotency\Middleware;

use Altair\Idempotency\Contracts\IdempotencyStoreInterface;
use Altair\Idempotency\Middleware\IdempotencyKeyMiddleware;
use Altair\Idempotency\Storage\InMemoryStore;
use Altair\Idempotency\Storage\StoredResponse;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class IdempotencyKeyMiddlewareTest extends TestCase
{
    public function testSafeMethodsPassThroughWithoutCaching(): void
    {
        $store = new InMemoryStore();
        $middleware = $this->middleware($store);
        $handler = $this->handler(fn(): ResponseInterface => $this->jsonResponse(200, ['ok' => true]));

        $response = $middleware->process(
            $this->request('GET', '/users', body: '', headers: ['Idempotency-Key' => 'abc']),
            $handler,
        );

        self::assertSame(200, $response->getStatusCode());
        // Nothing was claimed.
        self::assertNull($store->get('abc'));
    }

    public function testOptionalModeWithoutHeaderPassesThrough(): void
    {
        $middleware = $this->middleware(new InMemoryStore());
        $handler = $this->handler(fn(): ResponseInterface => $this->jsonResponse(201, ['id' => 'u_1']));

        $response = $middleware->process(
            $this->request('POST', '/users', body: '{"email":"a@b.c"}'),
            $handler,
        );

        self::assertSame(201, $response->getStatusCode());
    }

    public function testRequiredModeWithoutHeaderReturns400(): void
    {
        $middleware = $this->middleware(new InMemoryStore(), mode: IdempotencyKeyMiddleware::MODE_REQUIRED);

        $response = $middleware->process(
            $this->request('POST', '/users', body: '{"email":"a@b.c"}'),
            $this->handler(fn(): ResponseInterface => $this->jsonResponse(201, ['id' => 'u_1'])),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('Idempotency-Key header required', (string) $response->getBody());
    }

    public function testMalformedKeyReturns400(): void
    {
        $middleware = $this->middleware(new InMemoryStore());
        $handler = $this->handler(fn(): ResponseInterface => $this->jsonResponse(201, []));

        // Whitespace in the key — invalid.
        $response = $middleware->process(
            $this->request('POST', '/users', body: '{}', headers: ['Idempotency-Key' => "bad key"]),
            $handler,
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('malformed', (string) $response->getBody());
    }

    public function testTooLongKeyReturns400(): void
    {
        $middleware = $this->middleware(new InMemoryStore());
        $longKey = str_repeat('a', 256);

        $response = $middleware->process(
            $this->request('POST', '/users', body: '{}', headers: ['Idempotency-Key' => $longKey]),
            $this->handler(fn(): ResponseInterface => $this->jsonResponse(201, [])),
        );

        self::assertSame(400, $response->getStatusCode());
    }

    public function testFreshKeyExecutesHandlerAndCachesResponse(): void
    {
        $store = new InMemoryStore();
        $middleware = $this->middleware($store);
        $handler = $this->handler(fn(): ResponseInterface => $this->jsonResponse(201, ['id' => 'u_1']));

        $response = $middleware->process(
            $this->request('POST', '/users', body: '{"email":"a@b.c"}', headers: ['Idempotency-Key' => 'abc']),
            $handler,
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('{"id":"u_1"}', (string) $response->getBody());

        $stored = $store->get('abc');
        self::assertInstanceOf(StoredResponse::class, $stored);
        self::assertFalse($stored->inProgress);
        self::assertSame('{"id":"u_1"}', $stored->body);
    }

    public function testReplayReturnsCachedResponseWithReplayedHeader(): void
    {
        $store = new InMemoryStore();
        $middleware = $this->middleware($store);
        $callCount = 0;
        $handler = $this->handler(function () use (&$callCount): ResponseInterface {
            $callCount++;
            return $this->jsonResponse(201, ['id' => 'u_1']);
        });

        $body = '{"email":"a@b.c"}';
        $request = $this->request('POST', '/users', body: $body, headers: ['Idempotency-Key' => 'abc']);
        $middleware->process($request, $handler);

        // Second call with the same key + same body.
        $second = $middleware->process(
            $this->request('POST', '/users', body: $body, headers: ['Idempotency-Key' => 'abc']),
            $handler,
        );

        self::assertSame(1, $callCount, 'handler should run only once');
        self::assertSame(201, $second->getStatusCode());
        self::assertSame('{"id":"u_1"}', (string) $second->getBody());
        self::assertSame('true', $second->getHeaderLine(IdempotencyKeyMiddleware::HEADER_REPLAYED));
    }

    public function testReplayPreservesAllowedHeadersAndDropsSensitiveOnes(): void
    {
        $store = new InMemoryStore();
        $middleware = $this->middleware($store);
        $handler = $this->handler(function (): ResponseInterface {
            $response = $this->jsonResponse(201, ['id' => 'u_1']);

            return $response
                ->withHeader('Location', '/users/u_1')
                ->withHeader('Set-Cookie', 'session=secret')
                ->withHeader('Authorization', 'Bearer leaked');
        });

        $body = '{"email":"a@b.c"}';
        $middleware->process(
            $this->request('POST', '/users', body: $body, headers: ['Idempotency-Key' => 'abc']),
            $handler,
        );
        $replay = $middleware->process(
            $this->request('POST', '/users', body: $body, headers: ['Idempotency-Key' => 'abc']),
            $handler,
        );

        self::assertSame('/users/u_1', $replay->getHeaderLine('Location'));
        self::assertSame('', $replay->getHeaderLine('Set-Cookie'), 'Set-Cookie must not be replayed');
        self::assertSame('', $replay->getHeaderLine('Authorization'), 'Authorization must not be replayed');
    }

    public function testDifferentPayloadOnSameKeyReturns409(): void
    {
        $store = new InMemoryStore();
        $middleware = $this->middleware($store);
        $handler = $this->handler(fn(): ResponseInterface => $this->jsonResponse(201, ['id' => 'u_1']));

        $middleware->process(
            $this->request('POST', '/users', body: '{"email":"a@b.c"}', headers: ['Idempotency-Key' => 'abc']),
            $handler,
        );
        $response = $middleware->process(
            $this->request('POST', '/users', body: '{"email":"different@b.c"}', headers: ['Idempotency-Key' => 'abc']),
            $handler,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('different payload', (string) $response->getBody());
    }

    public function testStreamingResponseSkipsCaching(): void
    {
        $store = new InMemoryStore();
        $middleware = $this->middleware($store);
        $handler = $this->handler(fn(): ResponseInterface => (new Response())
            ->withStatus(200)
            ->withHeader('Content-Type', 'text/event-stream'));

        $middleware->process(
            $this->request('POST', '/events', body: '{}', headers: ['Idempotency-Key' => 'abc']),
            $handler,
        );

        self::assertNull($store->get('abc'), 'streaming responses must not be cached');
    }

    public function testChunkedResponseSkipsCaching(): void
    {
        $store = new InMemoryStore();
        $middleware = $this->middleware($store);
        $handler = $this->handler(fn(): ResponseInterface => (new Response())
            ->withStatus(200)
            ->withHeader('Transfer-Encoding', 'chunked'));

        $middleware->process(
            $this->request('POST', '/stream', body: '{}', headers: ['Idempotency-Key' => 'abc']),
            $handler,
        );

        self::assertNull($store->get('abc'), 'chunked responses must not be cached');
    }

    public function testHandlerThrowReleasesClaim(): void
    {
        $store = new InMemoryStore();
        $middleware = $this->middleware($store);
        $handler = $this->handler(static function (): never {
            throw new RuntimeException('boom');
        });

        try {
            $middleware->process(
                $this->request('POST', '/users', body: '{}', headers: ['Idempotency-Key' => 'abc']),
                $handler,
            );
            self::fail('Expected RuntimeException to propagate');
        } catch (RuntimeException) {
            // expected
        }

        self::assertNull($store->get('abc'), 'release() should drop the claim on exception');
    }

    public function testInProgressWithoutCompletionTimesOutWith409(): void
    {
        $store = new InMemoryStore();
        // Pre-claim the key so the middleware sees an in-progress entry on first lookup.
        $store->claim('abc', hash('sha256', '{}'), 60);

        $middleware = $this->middleware($store, maxWaitMs: 20, waitIntervalMs: 5);
        $handler = $this->handler(fn(): ResponseInterface => $this->jsonResponse(200, []));

        $response = $middleware->process(
            $this->request('POST', '/users', body: '{}', headers: ['Idempotency-Key' => 'abc']),
            $handler,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('still in progress', (string) $response->getBody());
    }

    public function testInProgressReleasedDuringWaitReturns409(): void
    {
        $store = new class implements IdempotencyStoreInterface {
            private bool $firstClaim = true;

            public function claim(string $key, string $requestHash, int $ttlSeconds): ?StoredResponse
            {
                if ($this->firstClaim) {
                    $this->firstClaim = false;

                    return StoredResponse::inProgress($requestHash, 0);
                }

                return null;
            }

            public function complete(string $key, StoredResponse $response, int $ttlSeconds): void {}

            public function release(string $key): void {}

            public function get(string $key): ?StoredResponse
            {
                return null; // Simulates a claim that was released by another worker.
            }
        };

        $middleware = $this->middleware($store, maxWaitMs: 20, waitIntervalMs: 5);
        $handler = $this->handler(fn(): ResponseInterface => $this->jsonResponse(200, []));

        $response = $middleware->process(
            $this->request('POST', '/users', body: '{}', headers: ['Idempotency-Key' => 'abc']),
            $handler,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('released', (string) $response->getBody());
    }

    public function testResponseBodyIsRebuiltSoDownstreamSeesContent(): void
    {
        // Regression guard: capturing the body in the middleware moves the
        // stream pointer; the middleware must put a fresh stream on the
        // response so the HTTP layer can still emit the body bytes.
        $store = new InMemoryStore();
        $middleware = $this->middleware($store);
        $handler = $this->handler(fn(): ResponseInterface => $this->jsonResponse(201, ['id' => 'u_1']));

        $response = $middleware->process(
            $this->request('POST', '/users', body: '{}', headers: ['Idempotency-Key' => 'abc']),
            $handler,
        );

        self::assertSame('{"id":"u_1"}', (string) $response->getBody());
    }

    private function middleware(
        IdempotencyStoreInterface $store,
        string $mode = IdempotencyKeyMiddleware::MODE_OPTIONAL,
        int $ttlSeconds = 60,
        int $maxWaitMs = 500,
        int $waitIntervalMs = 50,
    ): IdempotencyKeyMiddleware {
        return new IdempotencyKeyMiddleware(
            store: $store,
            responseFactory: new ResponseFactory(),
            streamFactory: new StreamFactory(),
            ttlSeconds: $ttlSeconds,
            mode: $mode,
            maxWaitMs: $maxWaitMs,
            waitIntervalMs: $waitIntervalMs,
        );
    }

    /**
     * @param callable(ServerRequestInterface): ResponseInterface $callable
     */
    private function handler(callable $callable): RequestHandlerInterface
    {
        return new class($callable) implements RequestHandlerInterface {
            /**
             * @param callable(ServerRequestInterface): ResponseInterface $callable
             */
            public function __construct(private $callable) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->callable)($request);
            }
        };
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $method, string $path, string $body, array $headers = []): ServerRequest
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write($body);
        $stream->rewind();

        $request = new ServerRequest();
        $request = $request->withMethod($method)->withBody($stream);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(int $status, array $payload): ResponseInterface
    {
        $stream = new Stream('php://temp', 'r+');
        $stream->write((string) json_encode($payload, JSON_UNESCAPED_SLASHES));
        $stream->rewind();

        return (new Response())
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream);
    }
}
