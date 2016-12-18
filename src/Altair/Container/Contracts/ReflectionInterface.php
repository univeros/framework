<?php
namespace Altair\Container\Contracts;

use ReflectionFunctionAbstract;
use ReflectionParameter;
use ReflectionClass;
use ReflectionMethod;
use ReflectionFunction;

interface ReflectionInterface
{
    /**
     * Retrieves ReflectionClass instances, caching them for future retrieval
     *
     * @param string $class
     *
     * @return \ReflectionClass
     */
    public function getClass(string $class): ReflectionClass;

    /**
     * Retrieves and caches the constructor (ReflectionMethod) for the specified class
     *
     * @param string $class
     *
     * @return \ReflectionMethod|null
     */
    public function getConstructor(string $class);

    /**
     * Retrieves and caches an array of constructor parameters for the given class
     *
     * @param string $class
     *
     * @return ReflectionParameter[]|null
     */
    public function getConstructorParameters(string $class);

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
     */
    public function getParameterTypeHint(ReflectionFunctionAbstract $function, ReflectionParameter $parameter);

    /**
     * Retrieves and caches a reflection for the specified function
     *
     * @param string $functionName
     *
     * @return ReflectionFunction
     */
    public function getFunction(string $functionName): ReflectionFunction;

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
