<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Contracts;

use Altair\Observatory\Panel\MigrationStatus;

/**
 * Narrow, fakeable seam yielding each known migration's applied/pending state.
 *
 * The framework's `db:migrate:status` reads this from a live Cycle
 * {@see \Cycle\Migrations\Migrator}, which needs a database connection. This
 * interface lets the Observatory project that state without coupling to Cycle
 * or to a live connection: hosts bind a Cycle-backed implementation, while
 * tests fake it. When the underlying state cannot be read (no database, an
 * unconfigured migrator, a connection failure), implementations return `null`
 * so the panel can degrade to an "unavailable" status rather than throw.
 *
 * @see \Altair\Observatory\Panel\MigrationsPanel
 */
interface MigrationStatusReaderInterface
{
    /**
     * Every known migration with its applied/pending state, or `null` when the
     * status cannot be read at all.
     *
     * An empty list is a valid, readable result (no migrations exist); `null`
     * specifically signals that no status could be determined.
     *
     * @return list<MigrationStatus>|null
     */
    public function read(): ?array;
}
