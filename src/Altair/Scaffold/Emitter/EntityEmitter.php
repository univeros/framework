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
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use LogicException;

/**
 * Emits a Cycle-annotated entity class derived from the spec's
 * `persistence.entity` block.
 *
 * The emitted class is intentionally minimal: typed properties only,
 * column attributes only, no behavior. Domain logic belongs in a
 * separate service or value object, not on the entity itself.
 */
final readonly class EntityEmitter
{
    public function __construct(private Naming $naming = new Naming()) {}

    public function emit(Spec $spec): EmittedFile
    {
        if (!$spec->persistence instanceof PersistenceSpec) {
            throw new LogicException('EntityEmitter requires a persistence block.');
        }

        $entity = $spec->persistence->entity;
        $namespace = $this->namespaceOf($entity->class);
        $shortName = $this->shortNameOf($entity->class);

        $header = PhpHeader::render($namespace);
        $useClauses = $this->buildUseClauses($entity);
        $entityAttribute = $this->buildEntityAttribute($entity);
        $properties = $this->buildProperties($entity);
        $constructor = $this->buildConstructor($entity);

        $constructorBlock = $constructor === '' ? '' : "\n" . $constructor;
        $body = <<<PHP
            {$useClauses}

            #[{$entityAttribute}]
            final class {$shortName}
            {
            {$properties}{$constructorBlock}
            }

            PHP;

        return new EmittedFile(
            relativePath: $this->naming->entityPath($spec),
            contents: $header . $body,
            kind: EmittedFileKind::Entity,
        );
    }

    private function buildUseClauses(PersistenceEntitySpec $entity): string
    {
        $imports = [
            Column::class,
            Entity::class,
        ];

        foreach ($entity->fields as $field) {
            if ($field->isEnum() && $field->of !== null) {
                $imports[] = $field->of;
            }

            if ($field->type === 'datetime') {
                $imports[] = 'DateTimeImmutable';
            }
        }

        sort($imports);
        $imports = array_unique($imports);

        return implode("\n", array_map(static fn(string $fqcn): string => \sprintf('use %s;', $fqcn), $imports));
    }

    private function buildEntityAttribute(PersistenceEntitySpec $entity): string
    {
        return \sprintf("Entity(table: '%s')", addslashes($entity->table));
    }

    private function buildProperties(PersistenceEntitySpec $entity): string
    {
        $lines = [];
        foreach ($entity->fields as $field) {
            $columnAttr = $this->buildColumnAttribute($field);
            $type = $this->phpType($field);
            $nullable = $field->nullable ? '?' : '';
            $default = $this->renderPropertyDefault($field);

            $lines[] = \sprintf('    #[%s]', $columnAttr);
            $lines[] = \sprintf('    public %s%s $%s%s;', $nullable, $type, $field->name, $default);
            $lines[] = '';
        }

        return rtrim(implode("\n", $lines));
    }

    /**
     * Property-level defaults are only emitted for scalar fields. Datetime
     * defaults (e.g. `now` -> CURRENT_TIMESTAMP) live in the column attribute
     * and are set by the database, not as a PHP literal — assigning a string
     * to a `DateTimeImmutable` property would be a type error.
     */
    private function renderPropertyDefault(PersistenceFieldSpec $field): string
    {
        if ($field->primary) {
            return '';
        }

        if ($field->hasDefault && $this->isScalarPhpType($field) && $field->default !== 'now') {
            return ' = ' . $this->renderDefault($field);
        }

        if ($field->nullable) {
            return ' = null';
        }

        return '';
    }

    private function isScalarPhpType(PersistenceFieldSpec $field): bool
    {
        return \in_array($this->phpType($field), ['string', 'int', 'float', 'bool'], true);
    }

    private function buildConstructor(PersistenceEntitySpec $entity): string
    {
        $required = array_values(array_filter(
            $entity->fields,
            static fn(PersistenceFieldSpec $f): bool => !$f->primary && !$f->nullable && !$f->hasDefault,
        ));

        if ($required === []) {
            return '';
        }

        $params = array_map(
            fn(PersistenceFieldSpec $f): string => \sprintf('        %s $%s,', $this->phpType($f), $f->name),
            $required,
        );

        $assigns = array_map(
            static fn(PersistenceFieldSpec $f): string => \sprintf('        $this->%1$s = $%1$s;', $f->name),
            $required,
        );

        return "\n    public function __construct(\n"
            . implode("\n", $params)
            . "\n    ) {\n"
            . implode("\n", $assigns)
            . "\n    }\n";
    }

    private function buildColumnAttribute(PersistenceFieldSpec $field): string
    {
        $args = ["type: '" . addslashes($this->cycleColumnType($field)) . "'"];

        if ($field->primary) {
            $args[] = 'primary: true';
        }

        if ($field->nullable) {
            $args[] = 'nullable: true';
        }

        if ($field->unique) {
            $args[] = 'unique: true';
        }

        if ($field->hasDefault && !$field->primary) {
            $args[] = 'default: ' . $this->renderDefault($field);
        }

        return 'Column(' . implode(', ', $args) . ')';
    }

    private function cycleColumnType(PersistenceFieldSpec $field): string
    {
        return match ($field->type) {
            'uuid' => 'string(36)',
            'integer' => 'integer',
            'boolean' => 'boolean',
            'enum' => 'string',
            default => $field->type,
        };
    }

    private function phpType(PersistenceFieldSpec $field): string
    {
        if ($field->isEnum() && $field->of !== null) {
            $parts = explode('\\', $field->of);

            return end($parts) ?: 'string';
        }

        return match ($field->type) {
            'uuid', 'string', 'text', 'json' => 'string',
            'int', 'integer', 'bigint', 'smallint' => 'int',
            'float', 'decimal' => 'float',
            'bool', 'boolean' => 'bool',
            'datetime', 'date', 'time' => 'DateTimeImmutable',
            default => 'mixed',
        };
    }

    private function renderDefault(PersistenceFieldSpec $field): string
    {
        return match (true) {
            $field->default === null => 'null',
            $field->default === 'now' && $field->type === 'datetime' => "'CURRENT_TIMESTAMP'",
            \is_bool($field->default) => $field->default ? 'true' : 'false',
            \is_int($field->default) || \is_float($field->default) => (string) $field->default,
            default => "'" . addslashes((string) $field->default) . "'",
        };
    }

    private function namespaceOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? '' : substr($fqcn, 0, $pos);
    }

    private function shortNameOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
