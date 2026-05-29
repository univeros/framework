<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Resolution;

use Altair\Container\Container;
use Altair\Container\Contracts\ReflectorInterface;
use Altair\Container\Exception\ContainerException;
use Closure;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

/**
 * DI-aware invocation of closures, `[class-or-object, method]` pairs,
 * `Class::method` strings, function names, and invokable objects/classes.
 */
final readonly class Invoker
{
    public function __construct(
        private Container $container,
        private ReflectorInterface $reflector,
        private ParameterResolver $parameters,
    ) {}

    /**
     * @param callable|array{0: object|class-string, 1: string}|string $target
     * @param array<string, mixed>                                     $callTime
     */
    public function call(callable|array|string $target, array $callTime, ResolutionStack $stack): mixed
    {
        [$function, $invoke] = $this->resolveCallable($target);

        $arguments = $this->parameters->buildArguments(
            $this->reflector->parametersOf($function),
            null,
            $callTime,
            $stack
        );

        return $invoke($arguments);
    }

    /**
     * @param callable|array{0: object|class-string, 1: string}|string $target
     *
     * @return array{0: ReflectionFunctionAbstract, 1: Closure(list<mixed>): mixed}
     */
    private function resolveCallable(callable|array|string $target): array
    {
        if ($target instanceof Closure) {
            return [new ReflectionFunction($target), static fn(array $args): mixed => $target(...$args)];
        }

        if (\is_string($target)) {
            if (str_contains($target, '::')) {
                [$class, $method] = explode('::', $target, 2);

                return $this->staticOrInstanceMethod($class, $method);
            }

            if (\function_exists($target)) {
                return [new ReflectionFunction($target), static fn(array $args): mixed => $target(...$args)];
            }

            if (class_exists($target)) {
                return $this->methodOnInstance($this->container->get($target), '__invoke');
            }

            throw new ContainerException(\sprintf('Callable "%s" is not invocable.', $target));
        }

        if (\is_array($target)) {
            [$classOrObject, $method] = $target;

            return \is_object($classOrObject)
                ? $this->methodOnInstance($classOrObject, $method)
                : $this->staticOrInstanceMethod($classOrObject, $method);
        }

        if (\is_object($target) && method_exists($target, '__invoke')) {
            return $this->methodOnInstance($target, '__invoke');
        }

        throw new ContainerException('Unsupported callable.');
    }

    /**
     * @return array{0: ReflectionFunctionAbstract, 1: Closure(list<mixed>): mixed}
     */
    private function staticOrInstanceMethod(string $class, string $method): array
    {
        $reflection = new ReflectionMethod($class, $method);

        if ($reflection->isStatic()) {
            return [$reflection, static fn(array $args): mixed => $class::$method(...$args)];
        }

        return $this->methodOnInstance($this->container->get($class), $method);
    }

    /**
     * @return array{0: ReflectionFunctionAbstract, 1: Closure(list<mixed>): mixed}
     */
    private function methodOnInstance(object $instance, string $method): array
    {
        return [
            new ReflectionMethod($instance, $method),
            static fn(array $args): mixed => $instance->{$method}(...$args),
        ];
    }
}
