<?php

declare(strict_types=1);

use Altair\Container\Container;
use Altair\Http\Middleware\ActionMiddleware;
use Altair\Http\Middleware\DispatcherMiddleware;
use FastRoute\RouteCollector;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Psr\Http\Message\ResponseInterface;
use Relay\Relay;

use function FastRoute\simpleDispatcher;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var Container $container */
$container = require dirname(__DIR__) . '/config/container.php';

/** @var list<array{0: string, 1: string, 2: class-string}> $routes */
$routes = require dirname(__DIR__) . '/config/routes.php';

$dispatcher = simpleDispatcher(static function (RouteCollector $collector) use ($routes, $container): void {
    foreach ($routes as [$method, $path, $action]) {
        $collector->addRoute($method, $path, $container->make($action));
    }
});

$relay = new Relay([
    new DispatcherMiddleware($dispatcher),
    new ActionMiddleware(
        static fn(string $class): object => $container->make($class),
        new ResponseFactory(),
    ),
]);

$response = $relay->handle(ServerRequestFactory::fromGlobals());

emit($response);

/**
 * Minimal SAPI emitter — writes the PSR-7 response to the output buffer.
 */
function emit(ResponseInterface $response): void
{
    http_response_code($response->getStatusCode());

    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header(sprintf('%s: %s', $name, $value), false);
        }
    }

    echo $response->getBody();
}
