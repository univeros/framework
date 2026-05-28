<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Planner;

use Altair\MigrationIntelligence\Intent\IntentInterface;

/**
 * Renders human-readable *preview* DDL for an intent in a specific dialect.
 *
 * The canonical, apply-time artifact is always the emitted Cycle migration,
 * whose dialect layer produces the real DDL. These statements exist so the
 * pretty/JSON plan output can show what the change looks like per driver.
 */
interface DialectPlanner
{
    public function name(): string;

    /**
     * @return list<string>
     */
    public function forward(IntentInterface $intent): array;

    /**
     * @return list<string>
     */
    public function rollback(IntentInterface $intent): array;
}
