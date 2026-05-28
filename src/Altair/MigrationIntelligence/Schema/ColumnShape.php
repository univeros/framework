<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Schema;

/**
 * A single column in the normalized, dialect-agnostic schema model.
 *
 * `type` is a canonical type name shared by every reader (spec / DB / entity)
 * so the differ compares like with like. See {@see ColumnType} for the
 * vocabulary and the mappings each reader applies.
 */
final readonly class ColumnShape
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable = false,
        public bool $hasDefault = false,
        public mixed $default = null,
        public bool $primary = false,
        public ?int $size = null,
    ) {}

    public function withName(string $name): self
    {
        return new self(
            name: $name,
            type: $this->type,
            nullable: $this->nullable,
            hasDefault: $this->hasDefault,
            default: $this->default,
            primary: $this->primary,
            size: $this->size,
        );
    }

    public function asNullable(): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            nullable: true,
            hasDefault: $this->hasDefault,
            default: $this->default,
            primary: $this->primary,
            size: $this->size,
        );
    }

    /**
     * True when the column's data shape (type family + size) is equivalent,
     * ignoring its name.
     *
     * Sizes only count when both are known: specs carry no size, while DB
     * introspection does, so comparing a known size against an unknown one
     * would otherwise look like a change.
     */
    public function sameTypeAs(self $other): bool
    {
        if (ColumnType::family($this->type) !== ColumnType::family($other->type)) {
            return false;
        }

        if ($this->size === null || $other->size === null) {
            return true;
        }

        return $this->size === $other->size;
    }

    /**
     * True when anything about the column definition (other than its name)
     * changed: type, size, nullability, or default.
     */
    public function definitionDiffersFrom(self $other): bool
    {
        if (!$this->sameTypeAs($other)) {
            return true;
        }

        if ($this->nullable !== $other->nullable) {
            return true;
        }

        if ($this->hasDefault !== $other->hasDefault) {
            return true;
        }

        return $this->default !== $other->default;
    }
}
