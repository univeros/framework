<?php

namespace Altair\Tests\Middleware;

use Altair\Middleware\MiddlewareManager;
use Altair\Middleware\Payload;
use Altair\Middleware\Runner;
use Altair\Structure\Queue;
use PHPUnit\Framework\TestCase;

class MiddlewareManagerTest extends TestCase
{
    public function test()
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
        $manager = new MiddlewareManager($runner);

        $response = $manager($payload);

        $actual = $response->getAttribute('count');

        $this->assertSame('123456', $actual);
    }
}
