<?php
namespace Altair\Container\Reflection;

use Altair\Container\Cache\ArrayCache;
use Altair\Container\Contracts\ReflectionCacheInterface;
use Altair\Container\Contracts\ReflectionInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

class CachedReflection implements ReflectionInterface
{
    /**
     * @var ReflectionInterface|StandardReflection
     */
    protected $reflector;
    /**
     * @var ArrayCache|ReflectionCacheInterface
     */
    protected $cache;

    /**
     * CachedReflection constructor.
     *
     * @param ReflectionInterface|null $reflector
     * @param ReflectionCacheInterface|null $cache
     */
    public function __construct(ReflectionInterface $reflector = null, ReflectionCacheInterface $cache = null)
    {
        $this->reflector = $reflector?? new StandardReflection();
        $this->cache = $cache?? new ArrayCache();
    }

    /**
     * @inheritdoc
     */
    public function getClass(string $class): ReflectionClass
    {
        $key = ReflectionCacheInterface::CLASSES_KEY_PREFIX . strtolower($class);
        $reflectionClass = $this->cache->get($key);
        if (!$reflectionClass) {
            $reflectionClass = $this->reflector->getClass($class);
            $this->cache->put($key, $reflectionClass);
        }

        return $reflectionClass;
    }

    /**
     * @inheritdoc
     */
    public function getConstructor(string $class): ?ReflectionMethod
    {
        $key = ReflectionCacheInterface::CONSTRUCTORS_KEY_PREFIX . strtolower($class);
        $reflectionConstructor = $this->cache->get($key);
        if ($reflectionConstructor === false) {
            $reflectionConstructor = $this->reflector->getConstructor($class);
            $this->cache->put($key, $reflectionConstructor);
        }

        return $reflectionConstructor;
    }

    /**
     * @inheritdoc
     */
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
     * @inheritdoc
     */
    public function getParameterTypeHint(ReflectionFunctionAbstract $function, ReflectionParameter $parameter):? string
    {
        $name = strtolower($parameter->getName());
        $method = strtolower($function->name);
        if ($function instanceof ReflectionMethod) {
            $class = strtolower($function->class);
            $key = ReflectionCacheInterface::CLASSES_KEY_PREFIX . "{$class}.{$method}.param-{$name}";
        } else {
            $key = $method !== '{closure}'
                ? ReflectionCacheInterface::FUNCTIONS_KEY_PREFIX . "{$method}.param-{$name}"
                : null;
        }

        $typeHint = ($key === null) ? false : $this->cache->get($key);

        if (false !== $typeHint) {
            return $typeHint;
        }

        if ($reflectionClass = $parameter->getClass()) {
            $typeHint = $reflectionClass->getName();
            $classKey = ReflectionCacheInterface::CLASSES_KEY_PREFIX . strtolower($typeHint);
            $this->cache->put($classKey, $reflectionClass);
        } else {
            $typeHint = null;
        }

        $this->cache->put($key, $typeHint);

        return $typeHint;
    }

    /**
     * @inheritdoc
     */
    public function getFunction($name): ReflectionFunction
    {
        $key = is_string($name)
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
     * @inheritdoc
     */
    public function getMethod($classNameOrInstance, string $methodName): ReflectionMethod
    {
        $className = is_string($classNameOrInstance)
            ? $classNameOrInstance
            : get_class($classNameOrInstance);

        $key = ReflectionCacheInterface::METHODS_KEY_PREFIX . strtolower($className) . '.' . strtolower($methodName);

        if (!$reflectionMethod = $this->cache->get($key)) {
            $reflectionMethod = $this->reflector->getMethod($classNameOrInstance, $methodName);
            $this->cache->put($key, $reflectionMethod);
        }

        return $reflectionMethod;
    }
}
