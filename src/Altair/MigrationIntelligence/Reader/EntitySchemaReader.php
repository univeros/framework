<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Reader;

use Altair\MigrationIntelligence\Schema\ColumnShape;
use Altair\MigrationIntelligence\Schema\ColumnType;
use Altair\MigrationIntelligence\Schema\TableShape;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use ReflectionClass;

/**
 * Reads the *desired* table shape from a Cycle-annotated entity class by
 * reflecting its `#[Entity]` and property-level `#[Column]` attributes.
 *
 * Returns `null` for a class that does not exist or carries no `#[Entity]`.
 * Class-level columns, indexes, and relations are out of scope for v1 — the
 * scaffolder emits property-level columns, which is what this covers.
 */
final readonly class EntitySchemaReader
{
    public function read(string $class): ?TableShape
    {
        if (!class_exists($class)) {
            return null;
        }

        $reflection = new ReflectionClass($class);
        $entityAttributes = $reflection->getAttributes(Entity::class);
        if ($entityAttributes === []) {
            return null;
        }

        $entity = $entityAttributes[0]->newInstance();
        $table = $entity->getTable() ?? strtolower($reflection->getShortName());

        $columns = [];
        foreach ($reflection->getProperties() as $property) {
            foreach ($property->getAttributes(Column::class) as $attribute) {
                $column = $attribute->newInstance();
                [$type, $size] = $this->parseType($column->getType());
                $canonical = ColumnType::fromCycleAbstract($type);
                $primary = $column->isPrimary()
                    || $canonical === ColumnType::PRIMARY
                    || $canonical === ColumnType::BIG_PRIMARY;

                $columns[] = new ColumnShape(
                    name: $column->getColumn() ?? $property->getName(),
                    type: $canonical,
                    nullable: $column->isNullable(),
                    hasDefault: $column->hasDefault(),
                    default: $column->getDefault(),
                    primary: $primary,
                    size: $size,
                );
            }
        }

        return new TableShape($table, $columns);
    }

    /**
     * Splits a Cycle column type like `string(255)` or `decimal(8,2)` into the
     * base type and its first size argument.
     *
     * @return array{string, int|null}
     */
    private function parseType(string $type): array
    {
        if (preg_match('/^([a-zA-Z]+)\((\d+)/', $type, $matches) === 1) {
            return [$matches[1], (int) $matches[2]];
        }

        return [$type, null];
    }
}
