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
 * An index (optionally unique) over one or more columns.
 */
final readonly class IndexShape
{
    /**
     * @param list<string> $columns
     */
    public function __construct(
        public array $columns,
        public bool $unique = false,
        public ?string $name = null,
    ) {}

    /**
     * Stable identity for diffing: indexes match on their column set, not name
     * (names are driver-generated and noisy).
     */
    public function key(): string
    {
        return implode(',', $this->columns);
    }
}
