<?php
namespace Altair\Container\Contracts;

use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

interface ReflectionInterface
{
    /**
     * Retrieves ReflectionClass instances, caching them for future retrieval
     *
     * @param string $class
     *
     * @return ReflectionClass
     */
    public function getClass(string $class): ReflectionClass;

    /**
     * Retrieves and caches the constructor (ReflectionMethod) for the specified class
     *
     * @param string $class
     *
     * @return ReflectionMethod|null
     */
    public function getConstructor(string $class): ?ReflectionMethod;

    /**
     * Retrieves and caches an array of constructor parameters for the given class
     *
     * @param string $class
     *
     * @return ReflectionParameter[]|null
     */
    public function getConstructorParameters(string $class): ?array;

    /**
     * Retrieves the class type-hint from a given ReflectionParameter
     *
     * There is no way to directly access a parameter's type-hint without
     * instantiating a new ReflectionClass instance and calling its getName()
     * method. This method stores the results of this approach so that if
     * the same parameter type-hint or ReflectionClass is needed again we
     * already have it cached.
     *
     * @param ReflectionFunctionAbstract $function
     * @param ReflectionParameter $parameter
     *
     * @return null|string
     */
    public function getParameterTypeHint(ReflectionFunctionAbstract $function, ReflectionParameter $parameter):? string;

    /**
     * Retrieves and caches a reflection for the specified function
     *
     * @param mixed $name
     *
     * @return ReflectionFunction
     */
    public function getFunction($name): ReflectionFunction;

    /**
     * Retrieves and caches a reflection for the specified class method
     *
     * @param mixed $classNameOrInstance
     * @param string $methodName
     *
     * @return ReflectionMethod
     */
    public function getMethod($classNameOrInstance, string $methodName): ReflectionMethod;
}
