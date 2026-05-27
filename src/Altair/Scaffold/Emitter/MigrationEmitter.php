<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Emitter;

use Altair\Scaffold\Spec\Ast\PersistenceEntitySpec;
use Altair\Scaffold\Spec\Ast\PersistenceFieldSpec;
use Altair\Scaffold\Spec\Ast\PersistenceSpec;
use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Scaffold\Templating\PhpHeader;
use LogicException;

/**
 * Emits a Cycle migration that creates the entity's table.
 *
 * The migration is timestamped at emit time. To keep snapshots
 * deterministic, callers can pass a fixed timestamp in tests.
 */
final readonly class MigrationEmitter
{
    public function __construct(private Naming $naming = new Naming()) {}

    public function emit(Spec $spec, ?int $timestamp = null): EmittedFile
    {
        if (!$spec->persistence instanceof PersistenceSpec) {
            throw new LogicException('MigrationEmitter requires a persistence block.');
        }

        $entity = $spec->persistence->entity;
        $className = $this->naming->migrationClassName($spec, $timestamp);

        $header = PhpHeader::render('Database\\Migrations');
        $body = <<<PHP
            use Cycle\\Migrations\\Migration;

            final class {$className} extends Migration
            {
                protected const string DATABASE = 'default';

                public function up(): void
                {
                    \$this->table('{$entity->table}')
            {$this->columnLines($entity, '            ')}
                        ->create();
                }

                public function down(): void
                {
                    \$this->table('{$entity->table}')->drop();
                }
            }

            PHP;

        return new EmittedFile(
            relativePath: $this->naming->migrationPath($spec, $timestamp),
            contents: $header . $body,
            kind: EmittedFileKind::Migration,
        );
    }

    private function columnLines(PersistenceEntitySpec $entity, string $indent): string
    {
        $primary = $entity->primaryField();
        $lines = [];

        if ($primary instanceof PersistenceFieldSpec) {
            $lines[] = $indent . $this->primaryColumnLine($primary);
        }

        foreach ($entity->fields as $field) {
            if ($field->primary) {
                continue;
            }

            $lines[] = $indent . $this->columnLine($field);
        }

        return implode("\n", $lines);
    }

    private function primaryColumnLine(PersistenceFieldSpec $field): string
    {
        $type = match ($field->type) {
            'uuid' => 'string',
            'integer', 'int' => 'primary',
            'bigint' => 'bigPrimary',
            default => 'primary',
        };

        return \sprintf("->addColumn('%s', '%s', ['nullable' => false])", $field->name, $type);
    }

    private function columnLine(PersistenceFieldSpec $field): string
    {
        $opts = [
            'nullable' => $field->nullable ? 'true' : 'false',
        ];

        if ($field->hasDefault) {
            $opts['default'] = $this->renderDefaultForMigration($field);
        }

        if ($field->unique) {
            $opts['unique'] = 'true';
        }

        $optsString = $this->renderOptionsArray($opts);

        return \sprintf(
            "->addColumn('%s', '%s', %s)",
            $field->name,
            $this->cycleColumnType($field),
            $optsString,
        );
    }

    private function cycleColumnType(PersistenceFieldSpec $field): string
    {
        return match ($field->type) {
            'uuid' => 'string',
            'integer' => 'integer',
            'boolean' => 'boolean',
            'enum' => 'string',
            default => $field->type,
        };
    }

    private function renderDefaultForMigration(PersistenceFieldSpec $field): string
    {
        return match (true) {
            $field->default === null => 'null',
            $field->default === 'now' && $field->type === 'datetime' => "'CURRENT_TIMESTAMP'",
            \is_bool($field->default) => $field->default ? 'true' : 'false',
            \is_int($field->default) || \is_float($field->default) => (string) $field->default,
            default => "'" . addslashes((string) $field->default) . "'",
        };
    }

    /**
     * @param array<string, string> $options
     */
    private function renderOptionsArray(array $options): string
    {
        $parts = [];
        foreach ($options as $key => $value) {
            $parts[] = \sprintf("'%s' => %s", $key, $value);
        }

        return '[' . implode(', ', $parts) . ']';
    }
}
