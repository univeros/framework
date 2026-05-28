<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container;

use Closure;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

class Executable
{
    protected ReflectionFunction|ReflectionMethod $callableReflection;

    /**
     * @var mixed|null The object to invoke the method on if $reflectionFunction is a ReflectionMethod. In
     * case of static methods it can be null.
     */
    protected $object;

    /**
     * Executable constructor.
     *
     * @param mixed|null $object The object to invoke the method on if $reflectionFunction is a ReflectionMethod. In
     * case of static methods, you can pass null to this parameter.
     *
     */
    public function __construct(ReflectionFunctionAbstract $reflectionFunction, $object = null)
    {
        if ($reflectionFunction instanceof ReflectionMethod) {
            $this->setMethodCallable($reflectionFunction, $object);
        } elseif ($reflectionFunction instanceof ReflectionFunction) {
            $this->callableReflection = $reflectionFunction;
        } else {
            throw new InvalidArgumentException(\sprintf('Unsupported reflection type "%s".', $reflectionFunction::class));
        }
    }

    /**
     * @return mixed
     */
    public function __invoke(mixed ...$args)
    {
        $reflection = $this->callableReflection;
        if ($reflection instanceof ReflectionMethod) {
            return $reflection->invokeArgs($this->object, $args);
        }

        return $this->callableReflection->isClosure()
            ? $this->invokeClosure($reflection, $args)
            : $reflection->invokeArgs($args);
    }

    public function getCallableReflection(): ReflectionFunctionAbstract
    {
        return $this->callableReflection;
    }

    /**
     * @return mixed|null
     */
    public function getObject()
    {
        return $this->object;
    }

    protected function setMethodCallable(ReflectionMethod $reflection, mixed $object): void
    {
        if (\is_object($object)) {
            $this->callableReflection = $reflection;
            $this->object = $object;
        } elseif ($reflection->isStatic()) {
            $this->callableReflection = $reflection;
        } else {
            throw new InvalidArgumentException('ReflectionMethod callables must specify an invocation object');
        }
    }

    /**
     * @param array<int|string, mixed> $args
     */
    protected function invokeClosure(ReflectionFunction $reflection, array $args): mixed
    {
        $scope = $reflection->getClosureScopeClass();

        $closure = Closure::bind(
            $reflection->getClosure(),
            $reflection->getClosureThis(),
            $scope ? $scope->name : null
        );

        return \call_user_func_array($closure, $args);
    }
}
