<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Reflection;

use Altair\Container\Cache\ArrayReflectionCache;
use Altair\Container\Contracts\ReflectionCacheInterface;
use Altair\Container\Contracts\ReflectorInterface;
use Override;
use ReflectionFunctionAbstract;

/**
 * Memoizes class metadata through a {@see ReflectionCacheInterface}; the heavy
 * reflection pass for each class happens once. Callable parameters are not
 * cached (they are not a hot path).
 */
final readonly class CachedReflector implements ReflectorInterface
{
    private ReflectorInterface $reflector;

    private ReflectionCacheInterface $cache;

    public function __construct(?ReflectorInterface $reflector = null, ?ReflectionCacheInterface $cache = null)
    {
        $this->reflector = $reflector ?? new Reflector();
        $this->cache = $cache ?? new ArrayReflectionCache();
    }

    #[Override]
    public function classMetadata(string $class): ClassMetadata
    {
        $key = strtolower(ltrim($class, '\\'));

        $cached = $this->cache->get($key);
        if ($cached instanceof ClassMetadata) {
            return $cached;
        }

        $metadata = $this->reflector->classMetadata($class);
        $this->cache->put($key, $metadata);

        return $metadata;
    }

    /**
     * @return list<ParameterMetadata>
     */
    #[Override]
    public function parametersOf(ReflectionFunctionAbstract $function): array
    {
        return $this->reflector->parametersOf($function);
    }
}
