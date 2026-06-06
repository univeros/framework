<?php

declare(strict_types=1);

use Altair\Container\Container;
use Altair\Events\EventRecordingLogger;
use Altair\Http\Middleware\ActionMiddleware;
use Altair\Http\Middleware\DispatcherMiddleware;
use Altair\Http\Middleware\ExceptionHandlerMiddleware;
use Altair\Http\Resolver\ContainerResolver;
use Altair\Http\Support\MiddlewarePriority;
use Altair\Http\Support\ModuleMiddleware;
use Altair\Http\Support\ModuleRoutes;
use Altair\Http\Support\ProblemDetailsErrorHandler;
use FastRoute\RouteCollector;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Relay\Relay;

use function FastRoute\simpleDispatcher;

require dirname(__DIR__) . '/vendor/autoload.php';

/** @var Container $container */
$container = require dirname(__DIR__) . '/config/container.php';

/** @var list<array{0: string, 1: string, 2: class-string}> $routes */
$routes = require dirname(__DIR__) . '/config/routes.php';

// Merge in routes contributed by registered modules (config/modules.php).
$routes = ModuleRoutes::collect($container, $routes);

$dispatcher = simpleDispatcher(static function (RouteCollector $collector) use ($routes, $container): void {
    foreach ($routes as [$method, $path, $action]) {
        $collector->addRoute($method, $path, $container->make($action));
    }
});

// Show full diagnostics locally; stay terse and leak-free in production.
$debug = filter_var($_SERVER['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL);

// When the Events package is enabled (config/configurations.php), server-side
// failures are recorded as `http_error` events — durable memory for agents.
// `bin/altair events:filter --kind=http_error` to review them.
$errorLogger = null;
if ($container->has(EventRecordingLogger::class)) {
    $candidate = $container->get(EventRecordingLogger::class);
    $errorLogger = $candidate instanceof LoggerInterface ? $candidate : null;
}

// The base pipeline, each stage carrying a documented priority. Modules that
// implement MiddlewareProviderInterface (config/modules.php) have their
// middleware merged in around these anchors, deterministically ordered by
// priority — so a module can register an auth/rate-limit/idempotency guard
// without this file changing. Lower priority runs earlier / more outer.
$pipeline = ModuleMiddleware::collect($container, [
    [
        // Outermost: turns any thrown exception into an RFC 7807 problem+json
        // response (negotiating HTML/plain), with the exception's own status code.
        'middleware' => new ExceptionHandlerMiddleware(
            responseFactory: new ResponseFactory(),
            handler: new ProblemDetailsErrorHandler(debug: $debug),
            capture: true,
            logger: $errorLogger,
        ),
        'priority' => MiddlewarePriority::EXCEPTION_HANDLER,
    ],
    [
        'middleware' => new DispatcherMiddleware($dispatcher),
        'priority' => MiddlewarePriority::DISPATCHER,
    ],
    [
        'middleware' => new ActionMiddleware(
            static fn(string $class): object => $container->make($class),
            new ResponseFactory(),
        ),
        'priority' => MiddlewarePriority::ACTION,
    ],
]);

// The resolver instantiates any class-string middleware a module contributes
// through the container (autowiring its dependencies); instances pass through.
$relay = new Relay($pipeline, new ContainerResolver($container));

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
