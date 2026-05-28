<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Protocol;

/**
 * A parsed JSON-RPC 2.0 request (or notification, when {@see $id} is null).
 */
final readonly class Request
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public string $method,
        public array $params = [],
        public int|string|null $id = null,
    ) {}

    /**
     * A notification has no id and therefore receives no response.
     */
    public function isNotification(): bool
    {
        return $this->id === null;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }
}
