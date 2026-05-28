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

final readonly class RenameColumnIntent implements IntentInterface
{
    public function __construct(
        public string $table,
        public string $from,
        public string $to,
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
        return IntentKind::RenameColumn;
    }

    #[Override]
    public function describe(): string
    {
        return \sprintf('RENAME COLUMN %s TO %s', $this->from, $this->to);
    }

    #[Override]
    public function destructive(): bool
    {
        return true;
    }
}
