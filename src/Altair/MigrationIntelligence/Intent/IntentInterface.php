<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Intent;

/**
 * A single, dialect-agnostic schema-evolution operation produced by the
 * differ. Planners render dialect SQL for it; the emitter renders Cycle DSL;
 * safety checks reason about it against the live DB.
 */
interface IntentInterface
{
    public function table(): string;

    public function kind(): IntentKind;

    /**
     * Human one-line description for the pretty renderer.
     */
    public function describe(): string;

    /**
     * Destructive operations (drop column, incompatible type change) gate
     * behind `--force` and inform two-phase planning.
     */
    public function destructive(): bool;
}
