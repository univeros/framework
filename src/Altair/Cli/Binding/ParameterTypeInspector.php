<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cli\Binding;

use Altair\Cli\Exception\InvalidCommandException;
use BackedEnum;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Small read-only helper to interpret a ReflectionParameter the way the
 * CLI binders need: native named type, array-ness, enum-ness, default value.
 */
class ParameterTypeInspector
{
    public function namedType(ReflectionParameter $parameter): ?ReflectionNamedType
    {
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType) {
            return null;
        }

        return $type;
    }

    public function typeName(ReflectionParameter $parameter): ?string
    {
        return $this->namedType($parameter)?->getName();
    }

    public function isBool(ReflectionParameter $parameter): bool
    {
        return $this->typeName($parameter) === 'bool';
    }

    public function isArray(ReflectionParameter $parameter): bool
    {
        return $this->typeName($parameter) === 'array';
    }

    public function isBackedEnum(ReflectionParameter $parameter): bool
    {
        $name = $this->typeName($parameter);
        if ($name === null) {
            return false;
        }

        return is_subclass_of($name, BackedEnum::class);
    }

    /**
     * Returns the default value of the parameter as a primitive that
     * Symfony Console can store (string, int, bool, null, or array).
     */
    public function defaultForConsole(ReflectionParameter $parameter): mixed
    {
        if (!$parameter->isDefaultValueAvailable()) {
            return null;
        }

        $default = $parameter->getDefaultValue();
        if ($default instanceof BackedEnum) {
            return $default->value;
        }

        return $default;
    }

    public function kebabCase(string $name): string
    {
        $result = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $name);
        if ($result === null) {
            throw new InvalidCommandException(
                \sprintf("Cannot derive kebab-case name from '%s'.", $name),
            );
        }

        return strtolower($result);
    }
}
