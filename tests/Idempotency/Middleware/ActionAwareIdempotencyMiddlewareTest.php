<?php

declare(strict_types=1);

namespace Altair\Tests\Idempotency\Middleware;

use Altair\Idempotency\Middleware\ActionAwareIdempotencyMiddleware;
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

final class ActionAwareIdempotencyMiddlewareTest extends TestCase
{
    public function testPassesThroughWhenNoActionAttribute(): void
    {
        $store = new InMemoryStore();
        $middleware = $this->middleware($store);

        $response = $middleware->process(
            $this->request('POST', '/users', body: '{}', headers: ['Idempotency-Key' => 'abc']),
            $this->handler(fn(): ResponseInterface => $this->jsonResponse(201, ['id' => 'u_1'])),
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertNull($store->get('abc'), 'no Action attribute → no caching');
    }

    public function testPassesThroughWhenActionLacksIdempotencyAccessor(): void
    {
        $store = new InMemoryStore();
        $middleware = $this->middleware($store);

        $action = new class {
            // No idempotency() method — pass-through.
        };
        $request = $this->request('POST', '/users', body: '{}', headers: ['Idempotency-Key' => 'abc'])
            ->withAttribute(ActionAwareIdempotencyMiddleware::DEFAULT_ACTION_ATTRIBUTE, $action);

        $response = $middleware->process(
            $request,
            $this->handler(fn(): ResponseInterface => $this->jsonResponse(201, ['id' => 'u_1'])),
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertNull($store->get('abc'));
    }

    public function testDelegatesToIdempotencyKeyMiddlewareWhenActionExposesPolicy(): void
    {
        $store = new InMemoryStore();
        $middleware = $this->middleware($store);

        $action = $this->actionWithPolicy('24h', 'tenant', 'optional');
        $callCount = 0;
        $handler = $this->handler(function () use (&$callCount): ResponseInterface {
            $callCount++;
            return $this->jsonResponse(201, ['id' => 'u_1']);
        });

        $body = '{"email":"a@b.c"}';
        $first = $middleware->process(
            $this->withAction($this->request('POST', '/users', body: $body, headers: ['Idempotency-Key' => 'abc']), $action),
            $handler,
        );
        $second = $middleware->process(
            $this->withAction($this->request('POST', '/users', body: $body, headers: ['Idempotency-Key' => 'abc']), $action),
            $handler,
        );

        self::assertSame(1, $callCount, 'handler should run only once');
        self::assertSame(201, $first->getStatusCode());
        self::assertSame(201, $second->getStatusCode());
        self::assertSame('true', $second->getHeaderLine(IdempotencyKeyMiddleware::HEADER_REPLAYED));

        $stored = $store->get('abc');
        self::assertInstanceOf(StoredResponse::class, $stored);
        self::assertFalse($stored->inProgress);
    }

    public function testRequiredModeFromActionRejectsMissingHeader(): void
    {
        $store = new InMemoryStore();
        $middleware = $this->middleware($store);
        $action = $this->actionWithPolicy('24h', 'tenant', 'required');

        $response = $middleware->process(
            $this->withAction($this->request('POST', '/users', body: '{}'), $action),
            $this->handler(fn(): ResponseInterface => $this->jsonResponse(201, [])),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('Idempotency-Key header required', (string) $response->getBody());
    }

    public function testCustomAttributeName(): void
    {
        $store = new InMemoryStore();
        $middleware = new ActionAwareIdempotencyMiddleware(
            store: $store,
            responseFactory: new ResponseFactory(),
            streamFactory: new StreamFactory(),
            actionAttribute: 'custom:action',
        );

        $action = $this->actionWithPolicy('24h', 'tenant', 'optional');
        $request = $this->request('POST', '/users', body: '{}', headers: ['Idempotency-Key' => 'abc'])
            ->withAttribute('custom:action', $action);

        $response = $middleware->process(
            $request,
            $this->handler(fn(): ResponseInterface => $this->jsonResponse(201, ['id' => 'u_1'])),
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertNotNull($store->get('abc'));
    }

    public function testIgnoresMalformedPolicy(): void
    {
        $store = new InMemoryStore();
        $middleware = $this->middleware($store);

        $action = new class {
            public static function idempotency(): array
            {
                return ['ttl' => 123]; // numeric, not string — invalid shape
            }
        };

        $response = $middleware->process(
            $this->withAction($this->request('POST', '/users', body: '{}', headers: ['Idempotency-Key' => 'abc']), $action),
            $this->handler(fn(): ResponseInterface => $this->jsonResponse(201, [])),
        );

        // Malformed policy → pass through instead of crashing.
        self::assertSame(201, $response->getStatusCode());
        self::assertNull($store->get('abc'));
    }

    private function middleware(InMemoryStore $store): ActionAwareIdempotencyMiddleware
    {
        return new ActionAwareIdempotencyMiddleware(
            store: $store,
            responseFactory: new ResponseFactory(),
            streamFactory: new StreamFactory(),
        );
    }

    private function actionWithPolicy(string $ttl, string $scope, string $mode): object
    {
        return new class($ttl, $scope, $mode) {
            public static string $ttl;

            public static string $scope;

            public static string $mode;

            public function __construct(string $ttl, string $scope, string $mode)
            {
                self::$ttl = $ttl;
                self::$scope = $scope;
                self::$mode = $mode;
            }

            public static function idempotency(): array
            {
                return ['ttl' => self::$ttl, 'scope' => self::$scope, 'mode' => self::$mode];
            }
        };
    }

    private function withAction(ServerRequest $request, object $action): ServerRequest
    {
        return $request->withAttribute(ActionAwareIdempotencyMiddleware::DEFAULT_ACTION_ATTRIBUTE, $action);
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
