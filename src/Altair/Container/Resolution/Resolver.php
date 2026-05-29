<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Resolution;

use Altair\Container\Attribute\Factory;
use Altair\Container\Container;
use Altair\Container\Contracts\ReflectorInterface;
use Altair\Container\Exception\AutowireException;
use Altair\Container\Exception\ContainerException;
use ReflectionException;

/**
 * Instantiates a class: honours a `#[Factory]`, autowires the constructor, and
 * fails loudly (with the resolution path) on non-instantiable types.
 */
final readonly class Resolver
{
    public function __construct(
        private Container $container,
        private ReflectorInterface $reflector,
        private ParameterResolver $parameters,
    ) {}

    /**
     * @param array<string, mixed> $callTime
     */
    public function instantiate(string $class, array $callTime, ResolutionStack $stack): object
    {
        try {
            $metadata = $this->reflector->classMetadata($class);
        } catch (ReflectionException $reflectionException) {
            throw new ContainerException(
                \sprintf('Cannot reflect "%s": %s', $class, $reflectionException->getMessage()),
                0,
                $reflectionException
            );
        }

        if ($metadata->factory instanceof Factory) {
            return $this->buildViaFactory($metadata->factory);
        }

        if (!$metadata->isInstantiable) {
            throw AutowireException::notInstantiable($class, $stack->path());
        }

        if (!$metadata->hasConstructor) {
            return new $class();
        }

        $arguments = $this->parameters->buildArguments($metadata->parameters, $class, $callTime, $stack);

        return new $class(...$arguments);
    }

    private function buildViaFactory(Factory $factory): object
    {
        $instance = $this->container->call([$this->container->get($factory->factory), $factory->method]);

        if (!\is_object($instance)) {
            throw new ContainerException(
                \sprintf('Factory %s::%s must return an object.', $factory->factory, $factory->method)
            );
        }

        return $instance;
    }
}
