<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Reflection;

use Altair\Container\Contracts\ReflectionInterface;
use Override;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

class StandardReflection implements ReflectionInterface
{
    /**
     * @inheritDoc
     * @throws ReflectionException
     * @return ReflectionClass<object>
     */
    #[Override]
    public function getClass(string $class): ReflectionClass
    {
        if (!class_exists($class) && !interface_exists($class)) {
            throw new ReflectionException(\sprintf('Class "%s" does not exist.', $class));
        }

        return new ReflectionClass($class);
    }

    /**
     * @inheritDoc
     * @throws ReflectionException
     */
    #[Override]
    public function getConstructor(string $class): ?ReflectionMethod
    {
        $reflectionClass = $this->getClass($class);

        return $reflectionClass->getConstructor();
    }

    /**
     * @inheritDoc
     * @throws ReflectionException
     */
    #[Override]
    public function getConstructorParameters(string $class): ?array
    {
        $reflectionConstructor = $this->getConstructor($class);

        return ($reflectionConstructor instanceof ReflectionMethod)
            ? $reflectionConstructor->getParameters()
            : null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getParameterTypeHint(ReflectionFunctionAbstract $function, ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        return $type->getName();
    }

    /**
     * @inheritDoc
     * @throws ReflectionException
     */
    #[Override]
    public function getFunction(mixed $name): ReflectionFunction
    {
        return new ReflectionFunction($name);
    }

    /**
     * @inheritDoc
     *
     * @return ReflectionParameter[]|null
     */
    public function getFunctionParameters(ReflectionFunction $reflectionFunction): ?array
    {
        return $reflectionFunction->getParameters();
    }

    /**
     * @inheritDoc
     * @throws ReflectionException
     */
    #[Override]
    public function getMethod(mixed $classNameOrInstance, string $methodName): ReflectionMethod
    {
        $className = \is_string($classNameOrInstance)
            ? $classNameOrInstance
            : $classNameOrInstance::class;

        return new ReflectionMethod($className, $methodName);
    }
}
