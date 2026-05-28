<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
     * @return ReflectionClass<object>
     */
    public function getClass(string $class): ReflectionClass;

    /**
     * Retrieves and caches the constructor (ReflectionMethod) for the specified class
     *
     *
     */
    public function getConstructor(string $class): ?ReflectionMethod;

    /**
     * Retrieves and caches an array of constructor parameters for the given class
     *
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
     *
     */
    public function getParameterTypeHint(ReflectionFunctionAbstract $function, ReflectionParameter $parameter): ?string;

    /**
     * Retrieves and caches a reflection for the specified function
     *
     *
     */
    public function getFunction(mixed $name): ReflectionFunction;

    /**
     * Retrieves and caches a reflection for the specified class method
     *
     *
     */
    public function getMethod(mixed $classNameOrInstance, string $methodName): ReflectionMethod;
}
