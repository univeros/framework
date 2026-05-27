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

/**
 * Value object holding the env-driven database connection settings.
 *
 * Centralizes env parsing so the rest of the package does not reach into
 * `$_ENV` / `getenv()` directly.
 */
final readonly class DatabaseSettings
{
    public const string DRIVER_POSTGRES = 'postgres';

    public const string DRIVER_MYSQL = 'mysql';

    public const string DRIVER_SQLITE = 'sqlite';

    public const string DRIVER_SQLSERVER = 'sqlserver';

    private const array SUPPORTED_DRIVERS = [
        self::DRIVER_POSTGRES,
        self::DRIVER_MYSQL,
        self::DRIVER_SQLITE,
        self::DRIVER_SQLSERVER,
    ];

    /**
     * @param array<string, scalar|null> $options Driver-specific PDO options.
     */
    public function __construct(
        public string $driver,
        public string $database,
        public string $host = 'localhost',
        public int $port = 0,
        public string $user = '',
        public string $password = '',
        public string $charset = 'utf8',
        public array $options = [],
    ) {
        if (!\in_array($driver, self::SUPPORTED_DRIVERS, true)) {
            throw new InvalidConfigurationException(\sprintf(
                "Unsupported DB_CONNECTION '%s'. Supported drivers: %s.",
                $driver,
                implode(', ', self::SUPPORTED_DRIVERS),
            ));
        }

        if ($database === '') {
            throw new InvalidConfigurationException('DB_DATABASE must not be empty.');
        }
    }

    /**
     * Build settings from an environment-style array.
     *
     * @param array<string, string|null> $env
     */
    public static function fromEnv(array $env): self
    {
        $driver = strtolower(trim((string) ($env['DB_CONNECTION'] ?? '')));
        if ($driver === '') {
            throw new InvalidConfigurationException('DB_CONNECTION env var is required.');
        }

        $database = (string) ($env['DB_DATABASE'] ?? '');

        return new self(
            driver: $driver,
            database: $database,
            host: (string) ($env['DB_HOST'] ?? 'localhost'),
            port: (int) ($env['DB_PORT'] ?? self::defaultPort($driver)),
            user: (string) ($env['DB_USER'] ?? ''),
            password: (string) ($env['DB_PASSWORD'] ?? ''),
            charset: (string) ($env['DB_CHARSET'] ?? 'utf8'),
        );
    }

    public function isSqlite(): bool
    {
        return $this->driver === self::DRIVER_SQLITE;
    }

    private static function defaultPort(string $driver): int
    {
        return match ($driver) {
            self::DRIVER_POSTGRES => 5432,
            self::DRIVER_MYSQL => 3306,
            self::DRIVER_SQLSERVER => 1433,
            default => 0,
        };
    }
}
