<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Idempotency\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Idempotency\Contracts\IdempotencyStoreInterface;
use Altair\Idempotency\Storage\InMemoryStore;
use Override;

/**
 * Wires the idempotency primitive into the container.
 *
 * v1: binds `InMemoryStore` as the default `IdempotencyStoreInterface`
 * implementation. Host applications swap to `ApcuStore` or `RedisStore`
 * by overriding the binding in their own Configuration after this one
 * has applied, or by re-binding via `Container::bind()` at boot.
 *
 * The TTL / scope / mode policy on a per-endpoint basis is sourced from
 * the generated Action's `idempotency()` accessor (#174) — this
 * Configuration only owns the storage adapter binding, not the
 * middleware lifecycle.
 */
final readonly class IdempotencyConfiguration implements ConfigurationInterface
{
    #[Override]
    public function apply(Container $container): void
    {
        $container->alias(IdempotencyStoreInterface::class, InMemoryStore::class);
    }
}
