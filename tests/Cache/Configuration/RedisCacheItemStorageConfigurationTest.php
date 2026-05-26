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
use ReflectionClass;

#[CoversClass(RedisCacheItemStorageConfiguration::class)]
final class RedisCacheItemStorageConfigurationTest extends TestCase
{
    public function testAliasesCacheItemStorageInterfaceToRedisNotPredis(): void
    {
        $container = new Container();
        (new RedisCacheItemStorageConfiguration(new Env([])))->apply($container);

        $aliases = (new ReflectionClass($container))->getProperty('aliases')->getValue($container);
        [$resolved] = $aliases->resolve(CacheItemStorageInterface::class);

        self::assertSame(
            RedisCacheItemStorage::class,
            ltrim((string) $resolved, '\\'),
            'RedisCacheItemStorageConfiguration must wire the ext-redis storage, not Predis.',
        );
        self::assertNotSame(PredisCacheItemStorage::class, ltrim((string) $resolved, '\\'));
    }
}
