<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Dto;

use Altair\Data\Contracts\DataObjectInterface;
use Altair\Persistence\Contracts\HydratorInterface;
use Altair\Persistence\Dto\Attribute\CollectionOf;
use Altair\Persistence\Exception\HydrationException;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;

use Override;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use Stringable;

/**
 * Reflection-driven hydrator that coerces a storage row into a typed,
 * immutable {@see DataObjectInterface}.
 *
 * Each incoming value is coerced to the declared type of the matching
 * property before construction, so the Data object's typed-property writes
 * never see a mismatched type. Nested {@see DataObjectInterface} properties
 * are hydrated recursively from an array, which is how composed read-models
 * ("relations") are expressed. Keys with no matching property are dropped.
 */
final class DataObjectHydrator implements HydratorInterface
{
    /**
     * @template T of DataObjectInterface
     *
     * @param class-string<T>      $dataObjectClass
     * @param array<string, mixed> $data
     *
     * @return T
     */
    #[Override]
    public function hydrate(string $dataObjectClass, array $data): DataObjectInterface
    {
        $reflection = new ReflectionClass($dataObjectClass);

        $coerced = [];
        foreach ($data as $key => $value) {
            if (!$reflection->hasProperty($key)) {
                continue;
            }

            $property = $reflection->getProperty($key);

            $elementType = $this->collectionElementType($property);
            if ($elementType !== null) {
                $coerced[$key] = $this->coerceCollection($value, $elementType, $dataObjectClass, $key);

                continue;
            }

            $coerced[$key] = $this->coerce($value, $property->getType(), $dataObjectClass, $key);
        }

        /** @var T $instance */
        $instance = new $dataObjectClass($coerced);

        return $instance;
    }

    /**
     * @template T of DataObjectInterface
     *
     * @param class-string<T>                $dataObjectClass
     * @param iterable<array<string, mixed>> $rows
     *
     * @return list<T>
     */
    #[Override]
    public function hydrateMany(string $dataObjectClass, iterable $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->hydrate($dataObjectClass, $row);
        }

        return $result;
    }

    /**
     * @return class-string<DataObjectInterface>|null
     */
    private function collectionElementType(ReflectionProperty $property): ?string
    {
        $attributes = $property->getAttributes(CollectionOf::class);

        return $attributes === [] ? null : $attributes[0]->newInstance()->type;
    }

    /**
     * @param class-string<DataObjectInterface> $elementClass
     *
     * @return list<DataObjectInterface>|null
     */
    private function coerceCollection(mixed $value, string $elementClass, string $class, string $field): ?array
    {
        if ($value === null) {
            return null;
        }

        if (!\is_array($value)) {
            throw HydrationException::uncoercible($class, $field, 'list<' . $elementClass . '>', $value);
        }

        $items = [];
        foreach ($value as $element) {
            if ($element instanceof DataObjectInterface) {
                $items[] = $element;

                continue;
            }

            if (\is_array($element)) {
                /** @var array<string, mixed> $element */
                $items[] = $this->hydrate($elementClass, $element);

                continue;
            }

            throw HydrationException::uncoercible($class, $field, $elementClass, $element);
        }

        return $items;
    }

    private function coerce(mixed $value, ?ReflectionType $type, string $class, string $field): mixed
    {
        // Null passes through untouched; union/intersection types are left for
        // PHP to enforce (coercion targets a single declared type only).
        if ($value === null || !$type instanceof ReflectionNamedType) {
            return $value;
        }

        $name = $type->getName();

        if ($type->isBuiltin()) {
            return $this->coerceBuiltin($value, $name, $class, $field);
        }

        if (is_a($name, DateTimeInterface::class, true)) {
            return $this->coerceDateTime($value, $name, $class, $field);
        }

        if (is_a($name, DataObjectInterface::class, true)) {
            if ($value instanceof DataObjectInterface) {
                return $value;
            }

            if (\is_array($value)) {
                /** @var array<string, mixed> $value */
                return $this->hydrate($name, $value);
            }

            throw HydrationException::uncoercible($class, $field, $name, $value);
        }

        if ($value instanceof $name) {
            return $value;
        }

        throw HydrationException::uncoercible($class, $field, $name, $value);
    }

    private function coerceBuiltin(mixed $value, string $type, string $class, string $field): mixed
    {
        switch ($type) {
            case 'int':
                if (\is_int($value)) {
                    return $value;
                }

                if (\is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
                    return (int) $value;
                }

                throw HydrationException::uncoercible($class, $field, 'int', $value);
            case 'float':
                if (\is_int($value) || \is_float($value)) {
                    return (float) $value;
                }

                if (\is_string($value) && is_numeric($value)) {
                    return (float) $value;
                }

                throw HydrationException::uncoercible($class, $field, 'float', $value);
            case 'bool':
                if (\is_bool($value)) {
                    return $value;
                }

                $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($bool === null) {
                    throw HydrationException::uncoercible($class, $field, 'bool', $value);
                }

                return $bool;
            case 'string':
                if (\is_string($value)) {
                    return $value;
                }

                if (\is_int($value) || \is_float($value) || \is_bool($value) || $value instanceof Stringable) {
                    return (string) $value;
                }

                throw HydrationException::uncoercible($class, $field, 'string', $value);
            case 'array':
                if (\is_array($value)) {
                    return $value;
                }

                throw HydrationException::uncoercible($class, $field, 'array', $value);
            default:
                // mixed / object / iterable / callable — nothing to coerce.
                return $value;
        }
    }

    private function coerceDateTime(mixed $value, string $name, string $class, string $field): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        try {
            if (\is_int($value)) {
                return new DateTimeImmutable('@' . $value);
            }

            if (\is_string($value) && $value !== '') {
                return $name === DateTime::class ? new DateTime($value) : new DateTimeImmutable($value);
            }
        } catch (Exception) {
            throw HydrationException::uncoercible($class, $field, $name, $value);
        }

        throw HydrationException::uncoercible($class, $field, $name, $value);
    }
}
