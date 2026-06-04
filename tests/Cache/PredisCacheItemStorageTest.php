<?php

declare(strict_types=1);

namespace Altair\Tests\Cache;

use Altair\Cache\Storage\PredisCacheItemStorage;
use Altair\Tests\Support\Integration\RedisServer;
use Predis\Client;

class PredisCacheItemStorageTest extends AbstractStorageTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        $endpoint = RedisServer::endpoint();
        if ($endpoint === null) {
            self::markTestSkipped('Redis integration test needs REDIS_HOST, a Redis on 127.0.0.1:6379, or Docker.');
        }

        [$host, $port] = $endpoint;
        $redis = new Client(['host' => $host, 'port' => $port]);

        $this->store = new PredisCacheItemStorage($redis, 'test');
    }
}
