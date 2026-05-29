<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Reflection;

use Altair\Container\Cache\ArrayCache;
use Altair\Container\Contracts\ReflectionCacheInterface;
use Altair\Container\Contracts\ReflectionInterface;
use Override;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

class CachedReflection implements ReflectionInterface
{
    protected ReflectionInterface $reflector;

    protected ReflectionCacheInterface $cache;

    /**
     * CachedReflection constructor.
     */
    public function __construct(?ReflectionInterface $reflector = null, ?ReflectionCacheInterface $cache = null)
    {
        $this->reflector = $reflector ?? new StandardReflection();
        $this->cache = $cache ?? new ArrayCache();
    }

    /**
     * @inheritDoc
     *
     * @throws ReflectionException
     * @return ReflectionClass<object>
     */
    #[Override]
    public function getClass(string $class): ReflectionClass
    {
        $key = ReflectionCacheInterface::CLASSES_KEY_PREFIX . strtolower($class);
        $reflectionClass = $this->cache->get($key);
        if (false === $reflectionClass) {
            $reflectionClass = $this->reflector->getClass($class);
            $this->cache->put($key, $reflectionClass);
        }

        return $reflectionClass;
    }

    /**
     * @inheritDoc
     *
     * @throws ReflectionException
     */
    #[Override]
    public function getConstructor(string $class): ?ReflectionMethod
    {
        $key = ReflectionCacheInterface::CONSTRUCTORS_KEY_PREFIX . strtolower($class);
        $reflectionConstructor = $this->cache->get($key);
        if (false === $reflectionConstructor) {
            $reflectionConstructor = $this->reflector->getConstructor($class);
            $this->cache->put($key, $reflectionConstructor);
        }

        return $reflectionConstructor;
    }

    /**
     * @inheritDoc
     *
     * @throws ReflectionException
     */
    #[Override]
    public function getConstructorParameters(string $class): ?array
    {
        $key = ReflectionCacheInterface::CONSTRUCTOR_PARAMETERS_KEY_PREFIX . strtolower($class);
        $reflectionConstructorParameters = $this->cache->get($key);
        if (false !== $reflectionConstructorParameters) {
            return $reflectionConstructorParameters;
        }

        $reflectionConstructorParameters = $this->reflector->getConstructorParameters($class);
        $this->cache->put($key, $reflectionConstructorParameters);

        return $reflectionConstructorParameters;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getParameterTypeHint(ReflectionFunctionAbstract $function, ReflectionParameter $parameter): ?string
    {
        $name = strtolower($parameter->getName());
        $method = strtolower($function->name);
        if ($function instanceof ReflectionMethod) {
            $class = strtolower($function->class);
            $key = ReflectionCacheInterface::CLASSES_KEY_PREFIX . \sprintf('%s.%s.param-%s', $class, $method, $name);
        } else {
            $key = (str_contains($method, '{closure}'))
                ? null
                : ReflectionCacheInterface::FUNCTIONS_KEY_PREFIX . \sprintf('%s.param-%s', $method, $name);
        }

        $typeHint = ($key === null) ? false : $this->cache->get($key);

        if (false === $typeHint) {
            $typeHint = $this->reflector->getParameterTypeHint($function, $parameter);
            if (null !== $key) {
                $this->cache->put($key, $typeHint);
            }
        }

        return $typeHint;
    }

    /**
     * @inheritDoc
     *
     * @throws ReflectionException
     */
    #[Override]
    public function getFunction(mixed $name): ReflectionFunction
    {
        $key = \is_string($name)
            ? ReflectionCacheInterface::FUNCTIONS_KEY_PREFIX . strtolower($name)
            : ReflectionCacheInterface::FUNCTIONS_KEY_PREFIX . spl_object_hash($name);

        $reflectionFunction = $this->cache->get($key);

        if (false === $reflectionFunction) {
            $reflectionFunction = $this->reflector->getFunction($name);
            $this->cache->put($key, $reflectionFunction);
        }

        return $reflectionFunction;
    }

    /**
     * @inheritDoc
     *
     * @throws ReflectionException
     */
    #[Override]
    public function getMethod(mixed $classNameOrInstance, string $methodName): ReflectionMethod
    {
        $className = \is_string($classNameOrInstance)
            ? $classNameOrInstance
            : $classNameOrInstance::class;

        $key = ReflectionCacheInterface::METHODS_KEY_PREFIX . strtolower($className) . '.' . strtolower($methodName);

        if (!$reflectionMethod = $this->cache->get($key)) {
            $reflectionMethod = $this->reflector->getMethod($classNameOrInstance, $methodName);
            $this->cache->put($key, $reflectionMethod);
        }

        return $reflectionMethod;
    }
}
