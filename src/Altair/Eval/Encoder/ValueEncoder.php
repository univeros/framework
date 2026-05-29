<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Eval\Encoder;

use Countable;
use Traversable;

/**
 * Turns a snippet's return value into a typed, JSON-serialisable shape an agent
 * can branch on.
 *
 * Bounded by construction: nested objects/arrays stop at {@see MAX_DEPTH}; only
 * the first {@see ITERABLE_PREVIEW} items of a `Traversable` are realised;
 * strings longer than {@see STRING_MAX} are tail-truncated; object cycles are
 * detected via a `spl_object_id()`-keyed seen-set and emitted as a `reference`
 * shape rather than recursed into. So a pathological return value (a deep
 * graph, an infinite generator) still produces a small, bounded payload.
 */
final class ValueEncoder
{
    public const int MAX_DEPTH = 3;

    public const int ITERABLE_PREVIEW = 50;

    public const int STRING_MAX = 10_000;

    /**
     * @param array<int, true> $seen object ids already visited in this encode tree
     *
     * @return array<string, mixed>
     */
    public static function encode(mixed $value, array $seen = [], int $depth = 0): array
    {
        return match (true) {
            $value === null => ['type' => 'null', 'value' => null],
            \is_bool($value) => ['type' => 'bool', 'value' => $value],
            \is_int($value) => ['type' => 'int', 'value' => $value],
            \is_float($value) => self::encodeFloat($value),
            \is_string($value) => self::encodeString($value),
            \is_resource($value) => ['type' => 'resource', 'value' => get_resource_type($value)],
            \is_array($value) => self::encodeArray($value, $seen, $depth),
            \is_object($value) => self::encodeObject($value, $seen, $depth),
            default => ['type' => 'unknown', 'value' => null],
        };
    }

    /**
     * @return array{type: string, value: float|string}
     */
    private static function encodeFloat(float $value): array
    {
        if (is_nan($value)) {
            return ['type' => 'float', 'value' => 'NaN'];
        }

        if (!is_finite($value)) {
            return ['type' => 'float', 'value' => $value > 0 ? 'Infinity' : '-Infinity'];
        }

        return ['type' => 'float', 'value' => $value];
    }

    /**
     * @return array<string, mixed>
     */
    private static function encodeString(string $value): array
    {
        if (\strlen($value) <= self::STRING_MAX) {
            return ['type' => 'string', 'value' => $value];
        }

        return [
            'type' => 'string',
            'value' => substr($value, 0, self::STRING_MAX) . '...(truncated)',
            'truncated' => true,
            'length' => \strlen($value),
        ];
    }

    /**
     * @param array<int|string, mixed> $value
     * @param array<int, true>         $seen
     *
     * @return array<string, mixed>
     */
    private static function encodeArray(array $value, array $seen, int $depth): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return ['type' => 'array', 'count' => \count($value), 'truncated' => true];
        }

        $isList = array_is_list($value);
        $encoded = [];
        foreach ($value as $key => $item) {
            $encoded[$key] = self::encode($item, $seen, $depth + 1);
        }

        return ['type' => 'array', 'is_list' => $isList, 'count' => \count($value), 'value' => $encoded];
    }

    /**
     * @param array<int, true> $seen
     *
     * @return array<string, mixed>
     */
    private static function encodeObject(object $value, array $seen, int $depth): array
    {
        $class = $value::class;
        $id = spl_object_id($value);

        if (isset($seen[$id])) {
            return ['type' => 'reference', 'class' => $class, 'id' => $id];
        }

        $seen[$id] = true;

        if ($value instanceof Traversable) {
            return self::encodeIterable($value, $class, $seen, $depth);
        }

        if ($depth >= self::MAX_DEPTH) {
            return ['type' => 'object', 'class' => $class, 'id' => $id, 'truncated' => true];
        }

        return [
            'type' => 'object',
            'class' => $class,
            'id' => $id,
            'properties' => self::objectProperties($value, $seen, $depth),
        ];
    }

    /**
     * @param array<int, true> $seen
     *
     * @return array<string, mixed>
     */
    private static function objectProperties(object $value, array $seen, int $depth): array
    {
        $snapshot = method_exists($value, '__debugInfo')
            ? (array) $value->__debugInfo()
            : get_object_vars($value);

        $properties = [];
        foreach ($snapshot as $name => $item) {
            $properties[(string) $name] = self::encode($item, $seen, $depth + 1);
        }

        return $properties;
    }

    /**
     * @param Traversable<mixed, mixed> $value
     * @param array<int, true>          $seen
     *
     * @return array<string, mixed>
     */
    private static function encodeIterable(Traversable $value, string $class, array $seen, int $depth): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return ['type' => 'iterable', 'class' => $class, 'truncated' => true];
        }

        $preview = [];
        $consumed = 0;
        $exhausted = true;
        foreach ($value as $key => $item) {
            if ($consumed >= self::ITERABLE_PREVIEW) {
                $exhausted = false;

                break;
            }

            $preview[] = [
                'key' => self::encode($key, $seen, $depth + 1),
                'value' => self::encode($item, $seen, $depth + 1),
            ];
            ++$consumed;
        }

        $shape = [
            'type' => 'iterable',
            'class' => $class,
            'preview' => $preview,
            'exhausted' => $exhausted,
        ];

        if ($value instanceof Countable) {
            $shape['size_hint'] = $value->count();
        }

        return $shape;
    }
}
