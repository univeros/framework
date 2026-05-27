<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Migrations;

use Cycle\Migrations\Config\MigrationConfig;

/**
 * Builds a {@see MigrationConfig} from simple, framework-friendly inputs.
 *
 * Defaults follow the project layout documented in the issue:
 *   - migrations live in `database/migrations`
 *   - tracking table is `cycle_migrations`
 *   - generated migration namespace is `Database\\Migrations`
 */
final class MigrationConfigFactory
{
    public function create(
        string $directory,
        string $namespace = 'Database\\Migrations',
        string $table = 'cycle_migrations',
        bool $safe = false,
    ): MigrationConfig {
        return new MigrationConfig([
            'directory' => $directory,
            'table' => $table,
            'namespace' => $namespace,
            'safe' => $safe,
        ]);
    }
}
