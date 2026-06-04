<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Support\Integration;

/**
 * Resolves a reachable Redis endpoint for integration tests, in priority order:
 *
 *   1. `REDIS_HOST` / `REDIS_PORT` env (an explicitly configured server, e.g. CI);
 *   2. an already-listening server on 127.0.0.1:6379 (a CI service container or a
 *      locally-running Redis) — reused as-is, no Docker needed;
 *   3. a throwaway `redis:7-alpine` container via {@see DockerContainer};
 *   4. none available → the caller skips.
 *
 * Returns `[host, port]`, or `null` when there is no server and no Docker — letting
 * the unit suite stay green and Docker-free on a machine without either.
 *
 * @phpstan-type Endpoint array{0: string, 1: int}
 */
final class RedisServer
{
    private const string IMAGE = 'redis:7-alpine';

    private const int DEFAULT_PORT = 6379;

    /**
     * @return array{0: string, 1: int}|null
     */
    public static function endpoint(): ?array
    {
        $envHost = getenv('REDIS_HOST');
        if (\is_string($envHost) && $envHost !== '') {
            $envPort = getenv('REDIS_PORT');

            return [$envHost, \is_string($envPort) && ctype_digit($envPort) ? (int) $envPort : self::DEFAULT_PORT];
        }

        if (DockerContainer::tcpIsOpen('127.0.0.1', self::DEFAULT_PORT)) {
            return ['127.0.0.1', self::DEFAULT_PORT];
        }

        if (DockerContainer::dockerAvailable()) {
            $container = DockerContainer::boot(self::IMAGE, self::DEFAULT_PORT)->waitUntilReady();

            return [$container->host(), $container->port()];
        }

        return null;
    }
}
