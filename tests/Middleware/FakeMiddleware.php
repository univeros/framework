<?php

namespace Altair\Tests\Middleware;

use Altair\Middleware\Contracts\MiddlewareInterface;
use Altair\Middleware\Contracts\PayloadInterface;

class FakeMiddleware implements MiddlewareInterface
{
    public static $count = 0;

    public function __invoke(PayloadInterface $payload, callable $next): PayloadInterface
    {
        $count = $payload->getAttribute('count', '');

        /** @var PayloadInterface $response */
        $response = $next($payload->withAttribute('count', $count . ++static::$count));

        return $response->withAttribute('count', $response->getAttribute('count') . ++static::$count);
    }
}
