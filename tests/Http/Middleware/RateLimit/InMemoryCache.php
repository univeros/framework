<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Http\Middleware\RateLimit;

use DateInterval;
use DateTimeImmutable;
use Override;
use Psr\SimpleCache\CacheInterface;

/**
 * Minimal in-process PSR-16 implementation for unit-testing the rate-limit
 * middleware. Honours TTL so the window-rollover test path is real; otherwise
 * a plain `unset` is enough. Not for production use.
 */
final class InMemoryCache implements CacheInterface
{
    /**
     * @var array<string, array{value: mixed, expires_at: ?int}>
     */
    private array $entries = [];

    #[Override]
    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->entries[$key] ?? null;
        if ($entry === null) {
            return $default;
        }

        if ($entry['expires_at'] !== null && $entry['expires_at'] <= time()) {
            unset($this->entries[$key]);

            return $default;
        }

        return $entry['value'];
    }

    #[Override]
    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->entries[$key] = [
            'value' => $value,
            'expires_at' => $this->resolveExpiry($ttl),
        ];

        return true;
    }

    #[Override]
    public function delete(string $key): bool
    {
        unset($this->entries[$key]);

        return true;
    }

    #[Override]
    public function clear(): bool
    {
        $this->entries = [];

        return true;
    }

    #[Override]
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->get($key, $default);
        }

        return $out;
    }

    #[Override]
    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    #[Override]
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    #[Override]
    public function has(string $key): bool
    {
        return $this->get($key, '__altair_miss__') !== '__altair_miss__';
    }

    private function resolveExpiry(null|int|DateInterval $ttl): ?int
    {
        if ($ttl === null) {
            return null;
        }

        if ($ttl instanceof DateInterval) {
            return (new DateTimeImmutable())->add($ttl)->getTimestamp();
        }

        return time() + $ttl;
    }
}
