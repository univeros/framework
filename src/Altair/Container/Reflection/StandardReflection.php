<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Reflection;

use Altair\Container\Contracts\ReflectionInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

class StandardReflection implements ReflectionInterface
{
    /**
     * @inheritdoc
     */
    public function getClass(string $class): ReflectionClass
    {
        return new ReflectionClass($class);
    }

    /**
     * @inheritdoc
     */
    public function getConstructor(string $class): ?ReflectionMethod
    {
        $reflectionClass = $this->getClass($class);

        return $reflectionClass->getConstructor();
    }

    /**
     * @inheritdoc
     */
    public function getConstructorParameters(string $class): ?array
    {
        $reflectionConstructor = $this->getConstructor($class);

        return ($reflectionConstructor instanceof ReflectionMethod)
            ? $reflectionConstructor->getParameters()
            : null;
    }

    /**
     * @inheritdoc
     */
    public function getParameterTypeHint(ReflectionFunctionAbstract $function, ReflectionParameter $parameter): ?string
    {
        $reflectionClass = $parameter->getClass();

        return $reflectionClass instanceof ReflectionClass
            ? $reflectionClass->getName()
            : null;
    }

    /**
     * @inheritdoc
     */
    public function getFunction($name): ReflectionFunction
    {
        return new ReflectionFunction($name);
    }

    /**
     * @inheritdoc
     */
    public function getFunctionParameters(ReflectionFunction $reflectionFunction): ?array
    {
        return $reflectionFunction->getParameters();
    }

    /**
     * @inheritdoc
     */
    public function getMethod($classNameOrInstance, string $methodName): ReflectionMethod
    {
        $className = is_string($classNameOrInstance)
            ? $classNameOrInstance
            : get_class($classNameOrInstance);

        return new ReflectionMethod($className, $methodName);
    }
}
