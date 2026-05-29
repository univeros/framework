<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Cache;

use Altair\Container\Contracts\ReflectionCacheInterface;
use Altair\Container\Reflection\ClassMetadata;
use Override;

/**
 * In-memory, per-request reflection cache (the default).
 */
final class ArrayReflectionCache implements ReflectionCacheInterface
{
    /**
     * @var array<string, ClassMetadata>
     */
    private array $store = [];

    #[Override]
    public function get(string $key): ?ClassMetadata
    {
        return $this->store[$key] ?? null;
    }

    #[Override]
    public function put(string $key, ClassMetadata $metadata): void
    {
        $this->store[$key] = $metadata;
    }
}
