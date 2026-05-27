<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Sdk\Model;

/**
 * Resolved, language-neutral view of one OpenAPI schema node.
 *
 * The parser flattens OpenAPI's `type` / `$ref` / `items` / `properties`
 * into this single recursive value object so the language emitters never
 * touch raw OpenAPI maps — they walk a typed tree instead.
 *
 * @phpstan-type PropertyShape array{ schema: SchemaType, required: bool }
 */
final readonly class SchemaType
{
    public const string OBJECT = 'object';

    public const string ARRAY = 'array';

    public const string SCALAR = 'scalar';

    public const string ENUM = 'enum';

    public const string REF = 'ref';

    public const string MIXED = 'mixed';

    /**
     * @param array<string, PropertyShape> $properties Object properties (OBJECT kind).
     * @param list<string>                  $enumValues Allowed values (ENUM kind).
     */
    public function __construct(
        public string $kind,
        public ?string $scalarType = null,
        public ?SchemaType $items = null,
        public array $properties = [],
        public ?string $ref = null,
        public array $enumValues = [],
        public ?string $format = null,
        public bool $nullable = false,
    ) {}

    public static function scalar(string $scalarType, ?string $format = null, bool $nullable = false): self
    {
        return new self(kind: self::SCALAR, scalarType: $scalarType, format: $format, nullable: $nullable);
    }

    public static function mixed(): self
    {
        return new self(kind: self::MIXED);
    }

    public static function arrayOf(self $items, bool $nullable = false): self
    {
        return new self(kind: self::ARRAY, items: $items, nullable: $nullable);
    }

    public static function ref(string $ref, bool $nullable = false): self
    {
        return new self(kind: self::REF, ref: $ref, nullable: $nullable);
    }

    /**
     * @param array<string, PropertyShape> $properties
     */
    public static function object(array $properties, bool $nullable = false): self
    {
        return new self(kind: self::OBJECT, properties: $properties, nullable: $nullable);
    }

    /**
     * @param list<string> $values
     */
    public static function enum(array $values, bool $nullable = false): self
    {
        return new self(kind: self::ENUM, scalarType: 'string', enumValues: $values, nullable: $nullable);
    }

    public function isObject(): bool
    {
        return $this->kind === self::OBJECT;
    }
}
