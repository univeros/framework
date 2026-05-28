<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Intent;

use Altair\MigrationIntelligence\Schema\IndexShape;
use Override;

final readonly class AddIndexIntent implements IntentInterface
{
    public function __construct(
        public string $table,
        public IndexShape $index,
    ) {}

    #[Override]
    public function table(): string
    {
        return $this->table;
    }

    #[Override]
    public function kind(): IntentKind
    {
        return IntentKind::AddIndex;
    }

    #[Override]
    public function describe(): string
    {
        return \sprintf(
            'ADD %sINDEX (%s)',
            $this->index->unique ? 'UNIQUE ' : '',
            implode(', ', $this->index->columns),
        );
    }

    #[Override]
    public function destructive(): bool
    {
        return false;
    }
}
