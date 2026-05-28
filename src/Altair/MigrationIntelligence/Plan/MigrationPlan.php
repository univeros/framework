<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Plan;

use Altair\MigrationIntelligence\Intent\IntentInterface;

/**
 * One emittable migration: its identity, the ordered intents it applies, and
 * the per-dialect SQL preview for forward and rollback.
 */
final readonly class MigrationPlan
{
    /**
     * @param list<IntentInterface> $operations
     * @param list<string>          $forwardSql
     * @param list<string>          $rollbackSql
     */
    public function __construct(
        public string $name,
        public string $className,
        public string $filename,
        public string $dialect,
        public array $operations,
        public array $forwardSql,
        public array $rollbackSql,
        public string $phase = '',
    ) {}

    public function isEmpty(): bool
    {
        return $this->operations === [];
    }
}
