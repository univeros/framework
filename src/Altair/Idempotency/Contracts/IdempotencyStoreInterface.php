<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Idempotency\Contracts;

use Altair\Idempotency\Storage\StoredResponse;

/**
 * Backing store for the idempotency middleware.
 *
 * Implementations coordinate three operations:
 *
 * - {@see self::claim()} atomically reserves a key for in-progress
 *   execution. The return value disambiguates the three meaningful
 *   states: `null` = the caller now owns the key and must execute;
 *   `StoredResponse with inProgress=true` = another caller is already
 *   executing; `StoredResponse with inProgress=false` = a previous
 *   execution already completed and the cached response can be
 *   replayed.
 *
 * - {@see self::complete()} persists the captured response under a key
 *   previously claimed by the same caller. Overwrites the in-progress
 *   marker.
 *
 * - {@see self::release()} drops a claim — used when the handler threw
 *   and the framework does not want to stick callers with a cached 5xx
 *   for the whole TTL window.
 *
 * Adapters that cannot guarantee atomic claim ({@see self::claim()}'s
 * primary contract) should throw {@see \Altair\Idempotency\Exception\IdempotencyException}
 * at construction time rather than silently degrading.
 */
interface IdempotencyStoreInterface
{
    /**
     * Atomically reserve a key with the given request hash, or return
     * the existing record when the key has been seen before.
     *
     * @param string $key            Idempotency-Key header value (already validated).
     * @param string $requestHash    SHA-256 of the request body bytes.
     * @param int    $ttlSeconds     Lifetime of the claim in seconds.
     *
     * @return ?StoredResponse `null` when the caller now owns the key
     *                        (must execute the request); a `StoredResponse`
     *                        when the key was already present (replay or
     *                        in-progress, distinguished by `inProgress`).
     */
    public function claim(string $key, string $requestHash, int $ttlSeconds): ?StoredResponse;

    /**
     * Persist the captured response under a previously-claimed key.
     * The TTL is re-applied so a completed entry survives at least
     * `$ttlSeconds` from the moment of completion.
     */
    public function complete(string $key, StoredResponse $response, int $ttlSeconds): void;

    /**
     * Drop the claim on a key without persisting a completed response.
     */
    public function release(string $key): void;

    /**
     * Read the current entry for a key, or `null` if absent / expired.
     */
    public function get(string $key): ?StoredResponse;
}
