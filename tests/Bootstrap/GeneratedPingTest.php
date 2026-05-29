<?php

declare(strict_types=1);

namespace Altair\Tests\Bootstrap;

use Altair\Bootstrap\SkeletonGenerator;
use Altair\Container\Container;
use Altair\Http\Middleware\ActionMiddleware;
use Altair\Http\Middleware\DispatcherMiddleware;
use FastRoute\RouteCollector;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Relay\Relay;

use function FastRoute\simpleDispatcher;

/**
 * The headline acceptance for #73: a freshly generated project actually serves
 * GET /ping → 200 through the same pipeline its front controller uses. This
 * generates the skeleton, autoloads its App\ classes from the temp directory,
 * and dispatches a real request — exercising the full Action/Input(DTO)/
 * Domain/Responder chain end to end.
 */
#[CoversClass(SkeletonGenerator::class)]
final class GeneratedPingTest extends TestCase
{
    private string $dir;

    #[Override]
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/altair-ping-' . bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    public function testGeneratedProjectServesPing(): void
    {
        (new SkeletonGenerator())->generate($this->dir);

        $appDir = $this->dir . '/app';
        $autoloader = static function (string $class) use ($appDir): void {
            if (str_starts_with($class, 'App\\')) {
                $path = $appDir . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
                if (is_file($path)) {
                    require $path;
                }
            }
        };
        spl_autoload_register($autoloader);

        try {
            $container = new Container();
            $container->instance($container::class, $container);

            /** @var list<array{0: string, 1: string, 2: class-string}> $routes */
            $routes = require $this->dir . '/config/routes.php';

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
        } finally {
            spl_autoload_unregister($autoloader);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.') {
                continue;
            }

            if ($item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
