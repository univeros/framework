<?php

declare(strict_types=1);

namespace Tests\Feature;

use Altair\Container\Container;
use Altair\Http\Middleware\ActionMiddleware;
use Altair\Http\Middleware\DispatcherMiddleware;
use FastRoute\RouteCollector;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\TestCase;
use Relay\Relay;

use function FastRoute\simpleDispatcher;

/**
 * Proof-of-life: a real GET /ping request travels the same pipeline the front
 * controller uses and returns 200 with the health payload.
 */
final class PingTest extends TestCase
{
    public function testPingReturns200WithHealthPayload(): void
    {
        $container = new Container();
        $container->share($container);

        /** @var list<array{0: string, 1: string, 2: class-string}> $routes */
        $routes = require __DIR__ . '/../../config/routes.php';

        $dispatcher = simpleDispatcher(static function (RouteCollector $collector) use ($routes, $container): void {
            foreach ($routes as [$method, $path, $action]) {
                $collector->addRoute($method, $path, $container->make($action));
            }
        });

        $relay = new Relay([
            new DispatcherMiddleware($dispatcher),
            new ActionMiddleware(static fn(string $class): object => $container->make($class), new ResponseFactory()),
        ]);

        $response = $relay->handle(new ServerRequest(uri: '/ping', method: 'GET'));

        self::assertSame(200, $response->getStatusCode());

        /** @var array{message: string, timestamp: string} $body */
        $body = json_decode((string) $response->getBody(), true);
        self::assertSame('ok', $body['message']);
        self::assertNotEmpty($body['timestamp']);
    }
}
