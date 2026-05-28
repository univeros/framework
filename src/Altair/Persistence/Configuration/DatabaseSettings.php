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
     * @param non-empty-string            $database
     * @param array<string, scalar|null>  $options  Driver-specific PDO options.
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

        if ($driver !== self::DRIVER_SQLITE) {
            if ($host === '') {
                throw new InvalidConfigurationException('DB_HOST must not be empty.');
            }

            if ($port < 1) {
                throw new InvalidConfigurationException('DB_PORT must be a positive integer.');
            }
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
        if ($database === '') {
            throw new InvalidConfigurationException('DB_DATABASE must not be empty.');
        }

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

    /**
     * Host narrowed for TCP drivers (validated non-empty in the constructor).
     *
     * @return non-empty-string
     */
    public function tcpHost(): string
    {
        if ($this->host === '') {
            throw new InvalidConfigurationException('DB_HOST must not be empty.');
        }

        return $this->host;
    }

    /**
     * Port narrowed for TCP drivers (validated positive in the constructor).
     *
     * @return int<1, max>
     */
    public function tcpPort(): int
    {
        if ($this->port < 1) {
            throw new InvalidConfigurationException('DB_PORT must be a positive integer.');
        }

        return $this->port;
    }

    /**
     * @return non-empty-string|null
     */
    public function tcpUser(): ?string
    {
        return $this->user === '' ? null : $this->user;
    }

    /**
     * @return non-empty-string|null
     */
    public function tcpPassword(): ?string
    {
        return $this->password === '' ? null : $this->password;
    }

    /**
     * @return non-empty-string|null
     */
    public function tcpCharset(): ?string
    {
        return $this->charset === '' ? null : $this->charset;
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
