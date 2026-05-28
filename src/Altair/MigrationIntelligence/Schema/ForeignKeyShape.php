<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Schema;

/**
 * A foreign-key constraint from local column(s) to a foreign table's column(s).
 */
final readonly class ForeignKeyShape
{
    /**
     * @param list<string> $columns
     * @param list<string> $foreignColumns
     */
    public function __construct(
        public array $columns,
        public string $foreignTable,
        public array $foreignColumns,
        public ?string $onDelete = null,
        public ?string $onUpdate = null,
        public ?string $name = null,
    ) {}

    /**
     * Stable identity for diffing: foreign keys match on their local column set.
     */
    public function key(): string
    {
        return implode(',', $this->columns);
    }
}
