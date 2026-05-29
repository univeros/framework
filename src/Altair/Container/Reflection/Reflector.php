<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Reflection;

use Altair\Container\Attribute\Autowire;
use Altair\Container\Attribute\Factory;
use Altair\Container\Attribute\Inject;
use Altair\Container\Attribute\Lazy;
use Altair\Container\Attribute\Tag;
use Altair\Container\Contracts\ReflectorInterface;
use Override;
use ReflectionClass;
use ReflectionException;
use ReflectionFunctionAbstract;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * Extracts immutable {@see ClassMetadata}/{@see ParameterMetadata} from PHP
 * reflection. Stateless and side-effect free; caching is layered on top by
 * {@see CachedReflector}.
 */
final class Reflector implements ReflectorInterface
{
    /**
     * @throws ReflectionException
     */
    #[Override]
    public function classMetadata(string $class): ClassMetadata
    {
        if (!class_exists($class) && !interface_exists($class)) {
            throw new ReflectionException(\sprintf('Class "%s" does not exist.', $class));
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        return new ClassMetadata(
            name: $class,
            isInstantiable: $reflection->isInstantiable(),
            hasConstructor: $constructor !== null,
            parameters: $constructor !== null ? $this->parametersOf($constructor) : [],
            factory: $this->factoryAttribute($reflection),
            isLazy: $reflection->getAttributes(Lazy::class) !== [],
            tags: $this->tagAttributes($reflection),
        );
    }

    /**
     * @return list<ParameterMetadata>
     */
    #[Override]
    public function parametersOf(ReflectionFunctionAbstract $function): array
    {
        return array_map(
            $this->parameter(...),
            $function->getParameters()
        );
    }

    private function parameter(ReflectionParameter $parameter): ParameterMetadata
    {
        [$types, $classTypes, $isIntersection] = $this->typeInfo($parameter->getType());

        $injectAttributes = $parameter->getAttributes(Inject::class);
        $autowireAttributes = $parameter->getAttributes(Autowire::class);

        return new ParameterMetadata(
            name: $parameter->getName(),
            position: $parameter->getPosition(),
            types: $types,
            classTypes: $classTypes,
            isIntersection: $isIntersection,
            allowsNull: $parameter->allowsNull(),
            isVariadic: $parameter->isVariadic(),
            hasDefault: $parameter->isDefaultValueAvailable(),
            default: $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
            inject: ($injectAttributes[0] ?? null)?->newInstance(),
            autowire: ($autowireAttributes[0] ?? null)?->newInstance(),
        );
    }

    /**
     * @return array{0: list<string>, 1: list<string>, 2: bool}
     */
    private function typeInfo(?ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();

            return $type->isBuiltin() ? [[$name], [], false] : [[$name], [$name], false];
        }

        if ($type instanceof ReflectionUnionType) {
            $names = [];
            $classes = [];
            foreach ($type->getTypes() as $member) {
                if ($member instanceof ReflectionNamedType) {
                    $names[] = $member->getName();
                    if (!$member->isBuiltin()) {
                        $classes[] = $member->getName();
                    }
                }
            }

            return [$names, $classes, false];
        }

        if ($type instanceof ReflectionIntersectionType) {
            $names = [];
            foreach ($type->getTypes() as $member) {
                if ($member instanceof ReflectionNamedType) {
                    $names[] = $member->getName();
                }
            }

            return [$names, $names, true];
        }

        return [[], [], false];
    }

    /**
     * @param ReflectionClass<object> $reflection
     */
    private function factoryAttribute(ReflectionClass $reflection): ?Factory
    {
        $attributes = $reflection->getAttributes(Factory::class);

        return ($attributes[0] ?? null)?->newInstance();
    }

    /**
     * @param ReflectionClass<object> $reflection
     *
     * @return list<string>
     */
    private function tagAttributes(ReflectionClass $reflection): array
    {
        $tags = [];
        foreach ($reflection->getAttributes(Tag::class) as $attribute) {
            foreach ($attribute->newInstance()->names as $name) {
                $tags[] = $name;
            }
        }

        return array_values(array_unique($tags));
    }
}
