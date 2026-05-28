<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Database;

use Altair\Mcp\Exception\McpException;
use Cycle\Database\DatabaseProviderInterface;
use Override;

/**
 * Adapts Cycle's DBAL to the read-only {@see DatabaseGatewayInterface}. Bound
 * only when the persistence package has wired a {@see DatabaseProviderInterface}.
 */
final readonly class CycleDatabaseGateway implements DatabaseGatewayInterface
{
    public function __construct(private DatabaseProviderInterface $databases) {}

    #[Override]
    public function isConfigured(): bool
    {
        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Override]
    public function select(string $sql): array
    {
        if ($sql === '') {
            throw new McpException('Empty query.');
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $this->databases->database()->query($sql)->fetchAll();

        return $rows;
    }

    /**
     * @return list<array{table: string, columns: list<array{name: string, type: string}>}>
     */
    #[Override]
    public function schema(): array
    {
        $tables = [];
        foreach ($this->databases->database()->getTables() as $table) {
            $columns = [];
            foreach ($table->getColumns() as $column) {
                $columns[] = ['name' => $column->getName(), 'type' => $column->getAbstractType()];
            }

            $tables[] = ['table' => $table->getName(), 'columns' => $columns];
        }

        return $tables;
    }
}
