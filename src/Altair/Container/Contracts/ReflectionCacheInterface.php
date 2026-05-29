<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Contracts;

use Altair\Container\Reflection\ClassMetadata;

/**
 * Caches the extracted {@see ClassMetadata} (plain data — never live
 * `Reflection*` objects), keyed by normalized class name.
 */
interface ReflectionCacheInterface
{
    public function get(string $key): ?ClassMetadata;

    public function put(string $key, ClassMetadata $metadata): void;
}
