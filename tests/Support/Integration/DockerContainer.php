<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Support\Integration;

use RuntimeException;

/**
 * Boots a throwaway service container for integration tests by shelling out to
 * the `docker` CLI — deliberately dependency-free (testcontainers-php pulls in a
 * Docker client that requires psr/http-message ^2, which conflicts with this
 * tree's relay/relay ~1.0 and neomerx/cors-psr7 ^1.0).
 *
 * A container is booted at most once per image+port per PHP process (so paratest
 * workers each get their own isolated instance) and torn down on shutdown. Tests
 * that need a service should resolve their endpoint through a per-service helper
 * (e.g. {@see RedisServer}) which skips gracefully when neither a running service
 * nor Docker is available.
 */
final class DockerContainer
{
    private static ?bool $available = null;

    /** @var array<string, self> One booted container per image+port, reused within the process. */
    private static array $shared = [];

    private function __construct(
        private readonly string $id,
        private readonly string $host,
        private readonly int $port,
    ) {}

    public static function dockerAvailable(): bool
    {
        if (self::$available === null) {
            exec('docker info > /dev/null 2>&1', $output, $code);
            self::$available = $code === 0;
        }

        return self::$available;
    }

    /**
     * Whether something is already accepting TCP connections at host:port — used
     * both to reuse an existing service (e.g. a CI service container) and as the
     * readiness probe after a boot.
     */
    public static function tcpIsOpen(string $host, int $port, float $timeout = 0.5): bool
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($connection === false) {
            return false;
        }

        fclose($connection);

        return true;
    }

    /**
     * @param array<string, string> $env environment variables passed to the container
     */
    public static function boot(string $image, int $containerPort, array $env = []): self
    {
        $key = $image . '/' . $containerPort;
        if (isset(self::$shared[$key])) {
            return self::$shared[$key];
        }

        $envFlags = '';
        foreach ($env as $name => $value) {
            $envFlags .= ' -e ' . escapeshellarg($name . '=' . $value);
        }

        $id = trim((string) shell_exec(\sprintf('docker run -d -P%s %s 2>/dev/null', $envFlags, escapeshellarg($image))));
        if ($id === '') {
            throw new RuntimeException(\sprintf("Failed to start a Docker container for image '%s'.", $image));
        }

        $container = new self($id, '127.0.0.1', self::mappedPort($id, $containerPort));
        self::$shared[$key] = $container;
        register_shutdown_function(static fn(): null => $container->stop());

        return $container;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function port(): int
    {
        return $this->port;
    }

    /**
     * Blocks until the mapped port accepts connections, so callers never race a
     * still-starting service.
     */
    public function waitUntilReady(float $timeoutSeconds = 20.0): self
    {
        $deadline = microtime(true) + $timeoutSeconds;
        while (microtime(true) < $deadline) {
            if (self::tcpIsOpen($this->host, $this->port)) {
                return $this;
            }

            usleep(100_000);
        }

        throw new RuntimeException(\sprintf('Container %s did not accept connections on %s:%d within %.0fs.', substr($this->id, 0, 12), $this->host, $this->port, $timeoutSeconds));
    }

    public function stop(): null
    {
        exec(\sprintf('docker rm -f %s > /dev/null 2>&1', escapeshellarg($this->id)));

        return null;
    }

    private static function mappedPort(string $id, int $containerPort): int
    {
        $format = \sprintf('{{(index (index .NetworkSettings.Ports "%d/tcp") 0).HostPort}}', $containerPort);
        $port = trim((string) shell_exec(\sprintf('docker inspect --format %s %s 2>/dev/null', escapeshellarg($format), escapeshellarg($id))));
        if ($port === '' || !ctype_digit($port)) {
            throw new RuntimeException(\sprintf('Could not read the host port mapped to %d/tcp for container %s.', $containerPort, substr($id, 0, 12)));
        }

        return (int) $port;
    }
}
