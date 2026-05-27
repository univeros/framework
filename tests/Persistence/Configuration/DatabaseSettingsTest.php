<?php

declare(strict_types=1);

namespace Altair\Tests\Persistence\Configuration;

use Altair\Persistence\Configuration\DatabaseSettings;
use Altair\Persistence\Exception\InvalidConfigurationException;
use PHPUnit\Framework\TestCase;

final class DatabaseSettingsTest extends TestCase
{
    public function testFromEnvBuildsSettingsForPostgres(): void
    {
        $settings = DatabaseSettings::fromEnv([
            'DB_CONNECTION' => 'postgres',
            'DB_DATABASE' => 'app',
            'DB_HOST' => 'db.local',
            'DB_PORT' => '5433',
            'DB_USER' => 'app',
            'DB_PASSWORD' => 'secret',
        ]);

        self::assertSame('postgres', $settings->driver);
        self::assertSame('app', $settings->database);
        self::assertSame('db.local', $settings->host);
        self::assertSame(5433, $settings->port);
        self::assertSame('app', $settings->user);
        self::assertSame('secret', $settings->password);
        self::assertFalse($settings->isSqlite());
    }

    public function testFromEnvDefaultsPortByDriver(): void
    {
        $settings = DatabaseSettings::fromEnv([
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => 'app',
        ]);

        self::assertSame(3306, $settings->port);
    }

    public function testRejectsMissingConnection(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        DatabaseSettings::fromEnv(['DB_DATABASE' => 'app']);
    }

    public function testRejectsUnknownDriver(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        DatabaseSettings::fromEnv([
            'DB_CONNECTION' => 'oracle',
            'DB_DATABASE' => 'app',
        ]);
    }

    public function testRejectsEmptyDatabase(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        DatabaseSettings::fromEnv([
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => '',
        ]);
    }

    public function testSqliteDetected(): void
    {
        $settings = DatabaseSettings::fromEnv([
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
        ]);

        self::assertTrue($settings->isSqlite());
    }
}
