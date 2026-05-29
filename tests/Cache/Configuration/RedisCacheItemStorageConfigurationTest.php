<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Cache\Configuration;

use Altair\Cache\Configuration\RedisCacheItemStorageConfiguration;
use Altair\Cache\Contracts\CacheItemStorageInterface;
use Altair\Cache\Storage\PredisCacheItemStorage;
use Altair\Cache\Storage\RedisCacheItemStorage;
use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RedisCacheItemStorageConfiguration::class)]
final class RedisCacheItemStorageConfigurationTest extends TestCase
{
    public function testWiresCacheItemStorageInterfaceToRedisNotPredis(): void
    {
        $container = new Container();
        (new RedisCacheItemStorageConfiguration(new Env([])))->apply($container);

        // The interface and the ext-redis concrete are both wired; the Predis
        // storage must NOT be bound by this configuration.
        self::assertTrue($container->has(CacheItemStorageInterface::class));
        self::assertTrue($container->has(RedisCacheItemStorage::class));
        self::assertFalse(
            $container->has(PredisCacheItemStorage::class),
            'RedisCacheItemStorageConfiguration must wire the ext-redis storage, not Predis.',
        );
    }
}
