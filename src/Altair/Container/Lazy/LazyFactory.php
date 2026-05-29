<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Lazy;

use Altair\Container\Exception\ContainerException;
use Closure;
use ReflectionClass;

/**
 * Produces a placeholder that defers real construction until first use.
 *
 * On PHP 8.4+ it uses native lazy objects (`ReflectionClass::newLazyProxy`),
 * detected at runtime. On PHP 8.3 — where native lazy objects do not exist —
 * it falls back to eager construction, so behaviour is identical even though
 * instantiation is no longer deferred.
 */
final class LazyFactory
{
    public function supportsNative(): bool
    {
        return method_exists(ReflectionClass::class, 'newLazyProxy');
    }

    /**
     * @param class-string|null      $class       concrete class to proxy (null = no proxyable type)
     * @param Closure(): mixed       $initializer builds the real instance
     */
    public function create(?string $class, Closure $initializer): object
    {
        if ($class !== null && $this->supportsNative() && class_exists($class)) {
            $reflection = new ReflectionClass($class);
            if ($reflection->isInstantiable()) {
                // PHP 8.4 only (guarded by supportsNative); ignored for the 8.3 analysis.
                return $reflection->newLazyProxy(static fn(object $object): mixed => $initializer());
            }
        }

        $object = $initializer();
        if (!\is_object($object)) {
            throw new ContainerException('A lazy binding must resolve to an object.');
        }

        return $object;
    }
}
