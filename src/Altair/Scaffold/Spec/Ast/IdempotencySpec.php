<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Spec\Ast;

/**
 * Optional `idempotency:` block on a Spec.
 *
 * When present, the scaffolder emits an `idempotency()` accessor on the
 * generated Action exposing the configured TTL, scope, and mode. The
 * `Altair\Idempotency\Middleware\IdempotencyKeyMiddleware` consumes
 * those values at runtime via {@see \Altair\Idempotency\Configuration\IdempotencyConfiguration}.
 *
 * `ttl` is kept as the raw string (e.g. `"24h"`) so the same value
 * round-trips byte-for-byte through `x-altair-idempotency` in
 * OpenAPI (#163).
 */
final readonly class IdempotencySpec
{
    public const string MODE_OPTIONAL = 'optional';

    public const string MODE_REQUIRED = 'required';

    public const string DEFAULT_SCOPE = 'tenant';

    public function __construct(
        public string $ttl,
        public string $scope = self::DEFAULT_SCOPE,
        public string $mode = self::MODE_OPTIONAL,
    ) {}
}
