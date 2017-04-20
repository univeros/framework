<?php

namespace Altair\Tests\Middleware;

use Altair\Container\Container;
use Altair\Middleware\Payload;
use Altair\Middleware\Resolver\MiddlewareResolver;
use Altair\Middleware\Runner;
use Altair\Structure\Queue;
use PHPUnit\Framework\TestCase;

class RunnerTest extends TestCase
{
    public function testWithoutResolver()
    {
        FakeMiddleware::$count = 0;

        $queue = new Queue(
            [
                new FakeMiddleware(),
                new FakeMiddleware(),
                new FakeMiddleware()
            ]
        );

        $runner = new Runner($queue);
        $payload = new Payload();
        $response = $runner($payload);

        $actual = $response->getAttribute('count');

        $this->assertSame('123456', $actual);
    }

    public function testWithCallableResolver()
    {
        FakeMiddleware::$count = 0;

        $queue = new Queue(
            [
                FakeMiddleware::class,
                FakeMiddleware::class,
                FakeMiddleware::class
            ]
        );

        $resolver = function ($class) {
            return new $class();
        };

        $runner = new Runner($queue, $resolver);
        $payload = new Payload();
        $response = $runner($payload);

        $actual = $response->getAttribute('count');

        $this->assertSame('123456', $actual);
    }

    public function testWithMiddlewareResolver()
    {
        FakeMiddleware::$count = 0;

        $queue = new Queue(
            [
                FakeMiddleware::class,
                FakeMiddleware::class,
                FakeMiddleware::class
            ]
        );

        $container = new Container();

        $resolver = new MiddlewareResolver($container);

        $runner = new Runner($queue, $resolver);
        $payload = new Payload();
        $response = $runner($payload);

        $actual = $response->getAttribute('count');

        $this->assertSame('123456', $actual);
    }
}
