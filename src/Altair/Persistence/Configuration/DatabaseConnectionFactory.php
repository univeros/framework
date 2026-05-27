<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Configuration;

use Altair\Persistence\Exception\InvalidConfigurationException;
use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\Config\MySQL\TcpConnectionConfig as MySQLTcpConnection;
use Cycle\Database\Config\MySQLDriverConfig;
use Cycle\Database\Config\Postgres\TcpConnectionConfig as PostgresTcpConnection;
use Cycle\Database\Config\PostgresDriverConfig;
use Cycle\Database\Config\SQLite\FileConnectionConfig;
use Cycle\Database\Config\SQLite\MemoryConnectionConfig;
use Cycle\Database\Config\SQLiteDriverConfig;
use Cycle\Database\Config\SQLServer\TcpConnectionConfig as SQLServerTcpConnection;
use Cycle\Database\Config\SQLServerDriverConfig;
use Cycle\Database\DatabaseManager;

/**
 * Builds a {@see DatabaseManager} from a {@see DatabaseSettings} value object.
 *
 * Translates the framework's flat env-driven config into the driver-specific
 * Cycle config objects without bleeding those types out to callers.
 */
final class DatabaseConnectionFactory
{
    public function create(DatabaseSettings $settings): DatabaseManager
    {
        $driver = $this->buildDriverConfig($settings);

        return new DatabaseManager(new DatabaseConfig([
            'default' => 'default',
            'databases' => [
                'default' => ['connection' => 'default'],
            ],
            'connections' => [
                'default' => $driver,
            ],
        ]));
    }

    private function buildDriverConfig(DatabaseSettings $settings): object
    {
        return match ($settings->driver) {
            DatabaseSettings::DRIVER_POSTGRES => new PostgresDriverConfig(
                connection: new PostgresTcpConnection(
                    database: $settings->database,
                    host: $settings->host,
                    port: $settings->port,
                    user: $settings->user,
                    password: $settings->password,
                ),
                queryCache: true,
            ),
            DatabaseSettings::DRIVER_MYSQL => new MySQLDriverConfig(
                connection: new MySQLTcpConnection(
                    database: $settings->database,
                    host: $settings->host,
                    port: $settings->port,
                    user: $settings->user,
                    password: $settings->password,
                    charset: $settings->charset,
                ),
                queryCache: true,
            ),
            DatabaseSettings::DRIVER_SQLITE => new SQLiteDriverConfig(
                connection: $settings->database === ':memory:'
                    ? new MemoryConnectionConfig()
                    : new FileConnectionConfig(database: $settings->database),
                queryCache: true,
            ),
            DatabaseSettings::DRIVER_SQLSERVER => new SQLServerDriverConfig(
                connection: new SQLServerTcpConnection(
                    database: $settings->database,
                    host: $settings->host,
                    port: $settings->port,
                    user: $settings->user,
                    password: $settings->password,
                ),
                queryCache: true,
            ),
            default => throw new InvalidConfigurationException(\sprintf(
                "Unsupported driver '%s'.",
                $settings->driver,
            )),
        };
    }
}
