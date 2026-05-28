<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\MigrationIntelligence\Support;

use Altair\Persistence\Configuration\DatabaseConnectionFactory;
use Altair\Persistence\Configuration\DatabaseSettings;
use Cycle\Database\DatabaseInterface;

/**
 * Builds a connected in-memory SQLite database for reader/safety tests.
 *
 * The same manager instance keeps a single PDO connection alive, so an
 * `:memory:` database survives across queries for the life of the test.
 */
final class SqliteDatabaseFactory
{
    public static function memory(): DatabaseInterface
    {
        $settings = new DatabaseSettings(
            driver: DatabaseSettings::DRIVER_SQLITE,
            database: ':memory:',
        );

        return (new DatabaseConnectionFactory())->create($settings)->database('default');
    }
}
