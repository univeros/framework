<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Intent;

use Altair\MigrationIntelligence\Schema\ColumnShape;
use Override;

final readonly class AddColumnIntent implements IntentInterface
{
    public function __construct(
        public string $table,
        public ColumnShape $column,
    ) {}

    #[Override]
    public function table(): string
    {
        return $this->table;
    }

    #[Override]
    public function kind(): IntentKind
    {
        return IntentKind::AddColumn;
    }

    #[Override]
    public function describe(): string
    {
        $null = $this->column->nullable ? 'NULL' : 'NOT NULL';

        return \sprintf('ADD COLUMN %s %s %s', $this->column->name, $this->column->type, $null);
    }

    #[Override]
    public function destructive(): bool
    {
        return false;
    }
}
