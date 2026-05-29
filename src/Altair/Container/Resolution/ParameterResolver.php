<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Resolution;

use Altair\Container\Attribute\Autowire;
use Altair\Container\Attribute\Inject;
use Altair\Container\Container;
use Altair\Container\Exception\AutowireException;
use Altair\Container\Exception\CircularDependencyException;
use Altair\Container\Exception\ContainerException;
use Altair\Container\Reflection\ParameterMetadata;
use Closure;

/**
 * Resolves a single constructor/callable parameter, and assembles the full
 * positional argument list. Strategy order per parameter: explicit call-time
 * value -> contextual binding -> #[Inject]/#[Autowire] -> type (binding or
 * autowire) -> default -> nullable null -> fail.
 */
final readonly class ParameterResolver
{
    public function __construct(private Container $container) {}

    /**
     * @param list<ParameterMetadata> $parameters
     * @param array<string, mixed>    $callTime
     *
     * @return list<mixed>
     */
    public function buildArguments(array $parameters, ?string $consumer, array $callTime, ResolutionStack $stack): array
    {
        $arguments = [];

        foreach ($parameters as $parameter) {
            if ($parameter->isVariadic) {
                if (\array_key_exists($parameter->name, $callTime)) {
                    $value = $callTime[$parameter->name];
                    foreach (\is_array($value) ? $value : [$value] as $item) {
                        $arguments[] = $item;
                    }
                }

                continue;
            }

            $arguments[] = $this->resolve($parameter, $consumer, $callTime, $stack);
        }

        return $arguments;
    }

    /**
     * @param array<string, mixed> $callTime
     */
    public function resolve(ParameterMetadata $parameter, ?string $consumer, array $callTime, ResolutionStack $stack): mixed
    {
        if (\array_key_exists($parameter->name, $callTime)) {
            return $callTime[$parameter->name];
        }

        if ($consumer !== null) {
            foreach ($parameter->classTypes as $type) {
                $binding = $this->container->contextualBinding($consumer, $type);
                if ($binding instanceof Closure) {
                    return $binding($this->container);
                }
            }
        }

        if ($parameter->inject instanceof Inject) {
            return $this->container->get($parameter->inject->id);
        }

        if ($parameter->autowire instanceof Autowire) {
            if ($parameter->autowire->service !== null) {
                return $this->container->get($parameter->autowire->service);
            }

            if ($parameter->autowire->param !== null) {
                return $this->container->parameterValue($parameter->autowire->param);
            }
        }

        [$found, $value] = $this->resolveByType($parameter);
        if ($found) {
            return $value;
        }

        if ($parameter->hasDefault) {
            return $parameter->default;
        }

        if ($parameter->allowsNull) {
            return null;
        }

        throw AutowireException::unresolvableParameter($parameter->name, $consumer ?? '(callable)', $stack->path());
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    private function resolveByType(ParameterMetadata $parameter): array
    {
        if ($parameter->classTypes === []) {
            return [false, null];
        }

        if ($parameter->isIntersection) {
            foreach ($parameter->classTypes as $type) {
                if (!$this->container->has($type)) {
                    continue;
                }

                $candidate = $this->container->get($type);
                if ($this->satisfiesAll($candidate, $parameter->classTypes)) {
                    return [true, $candidate];
                }
            }

            return [false, null];
        }

        foreach ($parameter->classTypes as $type) {
            try {
                return [true, $this->container->get($type)];
            } catch (CircularDependencyException $exception) {
                // a genuine cycle is fatal — never mask it as a "try the next type"
                throw $exception;
            } catch (ContainerException) {
                // this union member is unresolvable; try the next, else fall through
            }
        }

        return [false, null];
    }

    /**
     * @param list<string> $types
     */
    private function satisfiesAll(mixed $candidate, array $types): bool
    {
        foreach ($types as $type) {
            if (!$candidate instanceof $type) {
                return false;
            }
        }

        return true;
    }
}
