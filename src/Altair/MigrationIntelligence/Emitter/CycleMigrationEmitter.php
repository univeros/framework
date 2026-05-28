<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Emitter;

use Altair\MigrationIntelligence\Intent\AddColumnIntent;
use Altair\MigrationIntelligence\Intent\AddForeignKeyIntent;
use Altair\MigrationIntelligence\Intent\AddIndexIntent;
use Altair\MigrationIntelligence\Intent\ChangeColumnIntent;
use Altair\MigrationIntelligence\Intent\DataMigrationIntent;
use Altair\MigrationIntelligence\Intent\DropColumnIntent;
use Altair\MigrationIntelligence\Intent\DropIndexIntent;
use Altair\MigrationIntelligence\Intent\IntentInterface;
use Altair\MigrationIntelligence\Intent\RenameColumnIntent;
use Altair\MigrationIntelligence\Plan\MigrationPlan;
use Altair\MigrationIntelligence\Schema\ColumnShape;
use Altair\MigrationIntelligence\Schema\ColumnType;
use Altair\Scaffold\Templating\PhpHeader;

/**
 * Renders a {@see MigrationPlan} into a `Cycle\Migrations\Migration` subclass.
 *
 * The dialect-agnostic Cycle table blueprint is the apply-time artifact — Cycle
 * produces the real per-driver DDL. Contiguous schema operations batch into one
 * `$this->table()->...->update()` chain; a data migration flushes the chain and
 * runs as raw `$this->database()->execute()` so column adds land before copies.
 */
final readonly class CycleMigrationEmitter
{
    private const string STATEMENT_INDENT = '        ';

    private const string CHAIN_INDENT = '            ';

    public function emit(MigrationPlan $plan): string
    {
        $header = PhpHeader::render('Database\\Migrations');

        $body = \sprintf(
            <<<'PHP'
                use Cycle\Migrations\Migration;

                final class %s extends Migration
                {
                    protected const string DATABASE = 'default';

                    public function up(): void
                    {
                %s
                    }

                    public function down(): void
                    {
                %s
                    }
                }

                PHP,
            $plan->className,
            $this->renderUp($plan),
            $this->renderDown($plan),
        );

        return $header . $body;
    }

    private function renderUp(MigrationPlan $plan): string
    {
        return $this->renderBody($plan->operations, false);
    }

    private function renderDown(MigrationPlan $plan): string
    {
        return $this->renderBody(array_reverse($plan->operations), true);
    }

    /**
     * @param list<IntentInterface> $operations
     */
    private function renderBody(array $operations, bool $inverse): string
    {
        $table = $operations === [] ? '' : $operations[0]->table();
        $lines = [];
        $chain = [];

        foreach ($operations as $operation) {
            $dataSql = $this->dataMigrationSql($operation, $inverse);
            if ($dataSql !== null) {
                $this->flushChain($lines, $chain, $table);
                $chain = [];
                $lines[] = $dataSql;

                continue;
            }

            $call = $inverse ? $this->inverseCall($operation) : $this->call($operation);
            if ($call !== null) {
                $chain[] = $call;
            }
        }

        $this->flushChain($lines, $chain, $table);

        if ($lines === []) {
            $lines[] = self::STATEMENT_INDENT . '// no operations';
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $lines
     * @param list<string> $chain
     */
    private function flushChain(array &$lines, array $chain, string $table): void
    {
        if ($chain === []) {
            return;
        }

        $lines[] = self::STATEMENT_INDENT . \sprintf("\$this->table('%s')", $table);
        foreach ($chain as $call) {
            $lines[] = self::CHAIN_INDENT . $call;
        }

        $lines[] = self::CHAIN_INDENT . '->update();';
    }

    private function dataMigrationSql(IntentInterface $operation, bool $inverse): ?string
    {
        if (!$operation instanceof DataMigrationIntent) {
            return null;
        }

        if ($inverse) {
            return self::STATEMENT_INDENT . \sprintf('// data migration not reversed: %s', $operation->summary);
        }

        return self::STATEMENT_INDENT . \sprintf("\$this->database()->execute(%s);", $this->quote($operation->sql));
    }

    private function call(IntentInterface $operation): ?string
    {
        return match (true) {
            $operation instanceof AddColumnIntent => $this->addColumnCall($operation->column),
            $operation instanceof DropColumnIntent => \sprintf("->dropColumn('%s')", $operation->column->name),
            $operation instanceof RenameColumnIntent => \sprintf("->renameColumn('%s', '%s')", $operation->from, $operation->to),
            $operation instanceof ChangeColumnIntent => $this->alterColumnCall($operation->after),
            $operation instanceof AddIndexIntent => $this->addIndexCall($operation),
            $operation instanceof DropIndexIntent => \sprintf('->dropIndex(%s)', $this->columnArray($operation->index->columns)),
            $operation instanceof AddForeignKeyIntent => $this->addForeignKeyCall($operation),
            default => null,
        };
    }

    private function inverseCall(IntentInterface $operation): ?string
    {
        return match (true) {
            $operation instanceof AddColumnIntent => \sprintf("->dropColumn('%s')", $operation->column->name),
            $operation instanceof DropColumnIntent => $this->addColumnCall($operation->column),
            $operation instanceof RenameColumnIntent => \sprintf("->renameColumn('%s', '%s')", $operation->to, $operation->from),
            $operation instanceof ChangeColumnIntent => $this->alterColumnCall($operation->before),
            $operation instanceof AddIndexIntent => \sprintf('->dropIndex(%s)', $this->columnArray($operation->index->columns)),
            $operation instanceof DropIndexIntent => $this->addIndexCall(new AddIndexIntent($operation->table, $operation->index)),
            $operation instanceof AddForeignKeyIntent => \sprintf('->dropForeignKey(%s)', $this->columnArray($operation->foreignKey->columns)),
            default => null,
        };
    }

    private function addColumnCall(ColumnShape $column): string
    {
        return \sprintf(
            "->addColumn('%s', '%s', %s)",
            $column->name,
            ColumnType::toCycle($column->type),
            $this->columnOptions($column),
        );
    }

    private function alterColumnCall(ColumnShape $column): string
    {
        return \sprintf(
            "->alterColumn('%s', '%s', %s)",
            $column->name,
            ColumnType::toCycle($column->type),
            $this->columnOptions($column),
        );
    }

    private function addIndexCall(AddIndexIntent $intent): string
    {
        $options = ['unique' => $intent->index->unique ? 'true' : 'false'];

        return \sprintf(
            '->addIndex(%s, %s)',
            $this->columnArray($intent->index->columns),
            $this->optionsArray($options),
        );
    }

    private function addForeignKeyCall(AddForeignKeyIntent $intent): string
    {
        $foreignKey = $intent->foreignKey;
        $options = '[]';
        if ($foreignKey->onDelete !== null && $foreignKey->onDelete !== '') {
            $options = $this->optionsArray(['delete' => "'" . $foreignKey->onDelete . "'"]);
        }

        return \sprintf(
            "->addForeignKey(%s, '%s', %s, %s)",
            $this->columnArray($foreignKey->columns),
            $foreignKey->foreignTable,
            $this->columnArray($foreignKey->foreignColumns),
            $options,
        );
    }

    private function columnOptions(ColumnShape $column): string
    {
        $options = ['nullable' => $column->nullable ? 'true' : 'false'];

        if ($column->hasDefault) {
            $options['default'] = $this->defaultLiteral($column);
        }

        return $this->optionsArray($options);
    }

    private function defaultLiteral(ColumnShape $column): string
    {
        return match (true) {
            $column->default === null => 'null',
            \is_bool($column->default) => $column->default ? 'true' : 'false',
            \is_int($column->default) || \is_float($column->default) => (string) $column->default,
            $column->default === 'now' => "'CURRENT_TIMESTAMP'",
            default => $this->phpString((string) $column->default),
        };
    }

    /**
     * @param array<string, string> $options
     */
    private function optionsArray(array $options): string
    {
        $parts = [];
        foreach ($options as $key => $value) {
            $parts[] = \sprintf("'%s' => %s", $key, $value);
        }

        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * @param list<string> $columns
     */
    private function columnArray(array $columns): string
    {
        return '[' . implode(', ', array_map(static fn(string $column): string => "'" . $column . "'", $columns)) . ']';
    }

    private function quote(string $value): string
    {
        return $this->phpString($value);
    }

    /**
     * Render a value as a single-quoted PHP string literal — only backslash and
     * single quote need escaping (double quotes are literal here).
     */
    private function phpString(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }
}
