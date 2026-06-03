<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Module\Contracts;

use Altair\Module\Migration\MigrationSource;

/**
 * A module that ships database migrations.
 *
 * The migrate commands run each source against the shared `cycle_migrations`
 * tracking table after the host's own `database/migrations`, so a module's
 * migrations apply with a plain `bin/altair db:migrate` and already-applied
 * ones are skipped automatically.
 */
interface MigrationDirectoriesProviderInterface
{
    /**
     * @return list<MigrationSource>
     */
    public function migrationDirectories(): array;
}
