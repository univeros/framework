<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Intent;

use Altair\MigrationIntelligence\Schema\ForeignKeyShape;
use Override;

final readonly class AddForeignKeyIntent implements IntentInterface
{
    public function __construct(
        public string $table,
        public ForeignKeyShape $foreignKey,
    ) {}

    #[Override]
    public function table(): string
    {
        return $this->table;
    }

    #[Override]
    public function kind(): IntentKind
    {
        return IntentKind::AddForeignKey;
    }

    #[Override]
    public function describe(): string
    {
        return \sprintf(
            'ADD FOREIGN KEY (%s) REFERENCES %s (%s)',
            implode(', ', $this->foreignKey->columns),
            $this->foreignKey->foreignTable,
            implode(', ', $this->foreignKey->foreignColumns),
        );
    }

    #[Override]
    public function destructive(): bool
    {
        return false;
    }
}
