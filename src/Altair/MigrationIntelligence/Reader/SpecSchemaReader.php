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
use Altair\MigrationIntelligence\Schema\IndexShape;
use Altair\MigrationIntelligence\Schema\TableShape;
use Altair\Scaffold\Spec\Ast\PersistenceEntitySpec;
use Altair\Scaffold\Spec\Ast\PersistenceFieldSpec;
use Altair\Scaffold\Spec\Ast\PersistenceSpec;

/**
 * Reads a scaffolder `persistence:` block into the normalized table shape.
 *
 * Pure: no I/O, no DB. A `unique: true` field becomes a single-column unique
 * index, mirroring how the migration emitter renders it.
 */
final readonly class SpecSchemaReader
{
    public function fromSpec(PersistenceSpec $spec): TableShape
    {
        return $this->fromEntity($spec->entity);
    }

    public function fromEntity(PersistenceEntitySpec $entity): TableShape
    {
        $columns = [];
        $indexes = [];

        foreach ($entity->fields as $field) {
            $columns[] = new ColumnShape(
                name: $field->name,
                type: $this->canonicalType($field),
                nullable: $field->nullable,
                hasDefault: $field->hasDefault,
                default: $field->default,
                primary: $field->primary,
            );

            if ($field->unique) {
                $indexes[] = new IndexShape([$field->name], unique: true);
            }
        }

        return new TableShape($entity->table, $columns, $indexes);
    }

    private function canonicalType(PersistenceFieldSpec $field): string
    {
        $canonical = ColumnType::fromSpec($field->type);

        if (!$field->primary) {
            return $canonical;
        }

        return match ($canonical) {
            ColumnType::INTEGER => ColumnType::PRIMARY,
            ColumnType::BIG_INTEGER => ColumnType::BIG_PRIMARY,
            default => $canonical,
        };
    }
}
