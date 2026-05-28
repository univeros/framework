<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Db;

use Altair\Persistence\Configuration\DatabaseConnectionFactory;
use Altair\Persistence\Configuration\DatabaseSettings;
use Cycle\Database\DatabaseInterface;
use Throwable;

/**
 * Builds a Cycle database connection from the same `DB_*` environment the rest
 * of the framework reads. Returns `null` on any failure (missing/invalid env,
 * unsupported driver) so the planner degrades to printing without safety
 * checks rather than crashing.
 */
final readonly class DatabaseProbe
{
    private const array ENV_KEYS = [
        'DB_CONNECTION',
        'DB_DATABASE',
        'DB_HOST',
        'DB_PORT',
        'DB_USER',
        'DB_PASSWORD',
        'DB_CHARSET',
    ];

    /**
     * @param array<string, string|null> $env
     */
    public function __construct(private array $env = []) {}

    public static function fromEnvironment(): self
    {
        $env = [];
        foreach (self::ENV_KEYS as $key) {
            $value = getenv($key);
            $env[$key] = $value === false ? null : $value;
        }

        return new self($env);
    }

    public function connect(): ?DatabaseInterface
    {
        try {
            $settings = DatabaseSettings::fromEnv($this->env);

            return (new DatabaseConnectionFactory())->create($settings)->database('default');
        } catch (Throwable) {
            return null;
        }
    }
}
