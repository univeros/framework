<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Fixtures;

use Altair\Mcp\Database\DatabaseGatewayInterface;
use Override;

final readonly class FakeDatabaseGateway implements DatabaseGatewayInterface
{
    /**
     * @param list<array<string, mixed>>                                                $rows
     * @param list<array{table: string, columns: list<array{name: string, type: string}>}> $tables
     */
    public function __construct(
        private array $rows = [],
        private array $tables = [],
    ) {}

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
        return $this->rows;
    }

    /**
     * @return list<array{table: string, columns: list<array{name: string, type: string}>}>
     */
    #[Override]
    public function schema(): array
    {
        return $this->tables;
    }
}
