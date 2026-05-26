<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cli\Binding;

use Altair\Cli\Exception\ValueCoercionException;
use BackedEnum;
use DateTimeImmutable;
use Exception;
use ReflectionEnum;
use ReflectionNamedType;

/**
 * Coerces raw string values coming out of Symfony Console's InputInterface
 * into the native PHP type declared by the target __invoke parameter.
 */
class ValueCoercer
{
    private const array BOOL_TRUE = ['1', 'true', 'yes', 'on', 'y'];

    private const array BOOL_FALSE = ['0', 'false', 'no', 'off', 'n', ''];

    /**
     * Coerce a raw input value to the type described by $type.
     *
     * @param mixed                    $value The raw value from Symfony Console (string, bool, array, or null)
     * @param ReflectionNamedType|null $type  The native parameter type; null means mixed / untyped
     */
    public function coerce(mixed $value, ?ReflectionNamedType $type, string $parameterName): mixed
    {
        if ($type === null) {
            return $value;
        }

        if ($value === null) {
            if ($type->allowsNull()) {
                return null;
            }

            throw new ValueCoercionException(
                \sprintf("Parameter '%s' does not allow null.", $parameterName),
            );
        }

        $typeName = $type->getName();

        return match (true) {
            $typeName === 'string' => $this->toString($value, $parameterName),
            $typeName === 'int' => $this->toInt($value, $parameterName),
            $typeName === 'float' => $this->toFloat($value, $parameterName),
            $typeName === 'bool' => $this->toBool($value, $parameterName),
            $typeName === 'array' => $this->toArray($value, $parameterName),
            $typeName === DateTimeImmutable::class => $this->toDateTimeImmutable($value, $parameterName),
            is_subclass_of($typeName, BackedEnum::class) => $this->toBackedEnum($typeName, $value, $parameterName),
            default => throw new ValueCoercionException(
                \sprintf("Unsupported type '%s' for parameter '%s'.", $typeName, $parameterName),
            ),
        };
    }

    private function toString(mixed $value, string $name): string
    {
        if (\is_string($value)) {
            return $value;
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        throw new ValueCoercionException(
            \sprintf("Cannot coerce value of type '%s' to string for parameter '%s'.", get_debug_type($value), $name),
        );
    }

    private function toInt(mixed $value, string $name): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new ValueCoercionException(
            \sprintf("Value '%s' is not a valid integer for parameter '%s'.", $this->describe($value), $name),
        );
    }

    private function toFloat(mixed $value, string $name): float
    {
        if (\is_float($value) || \is_int($value)) {
            return (float) $value;
        }

        if (\is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        throw new ValueCoercionException(
            \sprintf("Value '%s' is not a valid float for parameter '%s'.", $this->describe($value), $name),
        );
    }

    private function toBool(mixed $value, string $name): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_int($value)) {
            return $value !== 0;
        }

        if (\is_string($value)) {
            $normalized = strtolower($value);
            if (\in_array($normalized, self::BOOL_TRUE, true)) {
                return true;
            }
            if (\in_array($normalized, self::BOOL_FALSE, true)) {
                return false;
            }
        }

        throw new ValueCoercionException(
            \sprintf("Value '%s' is not a valid boolean for parameter '%s'.", $this->describe($value), $name),
        );
    }

    /**
     * @return list<string>
     */
    private function toArray(mixed $value, string $name): array
    {
        if (\is_array($value)) {
            return array_values(array_map(static fn(mixed $item): string => (string) $item, $value));
        }

        if (\is_string($value)) {
            if ($value === '') {
                return [];
            }

            return array_values(array_map('trim', explode(',', $value)));
        }

        throw new ValueCoercionException(
            \sprintf("Value '%s' cannot be coerced to array for parameter '%s'.", $this->describe($value), $name),
        );
    }

    private function toDateTimeImmutable(mixed $value, string $name): DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (!\is_string($value) || $value === '') {
            throw new ValueCoercionException(
                \sprintf("Value for parameter '%s' must be an ISO-8601 string.", $name),
            );
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception $e) {
            throw new ValueCoercionException(
                \sprintf("Value '%s' is not a valid ISO-8601 datetime for parameter '%s'.", $value, $name),
                previous: $e,
            );
        }
    }

    /**
     * @param class-string<BackedEnum> $enumClass
     */
    private function toBackedEnum(string $enumClass, mixed $value, string $name): BackedEnum
    {
        if ($value instanceof $enumClass) {
            return $value;
        }

        $reflection = new ReflectionEnum($enumClass);
        $backingType = $reflection->getBackingType()?->getName();

        $candidate = match ($backingType) {
            'int' => \is_int($value) ? $value : (\is_string($value) && preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : null),
            'string' => \is_string($value) ? $value : (\is_scalar($value) ? (string) $value : null),
            default => null,
        };

        if ($candidate === null) {
            throw new ValueCoercionException(
                \sprintf(
                    "Value '%s' is not a valid case of enum '%s' for parameter '%s'.",
                    $this->describe($value),
                    $enumClass,
                    $name,
                ),
            );
        }

        $case = $enumClass::tryFrom($candidate);
        if ($case === null) {
            throw new ValueCoercionException(
                \sprintf(
                    "Value '%s' is not a valid case of enum '%s' for parameter '%s'.",
                    $this->describe($value),
                    $enumClass,
                    $name,
                ),
            );
        }

        return $case;
    }

    private function describe(mixed $value): string
    {
        if (\is_scalar($value)) {
            return (string) $value;
        }

        return get_debug_type($value);
    }
}
