<?php

declare(strict_types=1);

namespace Altair\Tests\Persistence\Configuration;

use Altair\Persistence\Configuration\DatabaseConnectionFactory;
use Altair\Persistence\Configuration\DatabaseSettings;
use Cycle\Database\Driver\SQLite\SQLiteDriver;
use PHPUnit\Framework\TestCase;

final class DatabaseConnectionFactoryTest extends TestCase
{
    public function testCreatesSqliteMemoryConnection(): void
    {
        $factory = new DatabaseConnectionFactory();
        $manager = $factory->create(new DatabaseSettings(
            driver: DatabaseSettings::DRIVER_SQLITE,
            database: ':memory:',
        ));

        $db = $manager->database('default');

        self::assertInstanceOf(SQLiteDriver::class, $db->getDriver());
    }

    public function testCreatesSqliteFileConnection(): void
    {
        $tmp = sys_get_temp_dir() . '/altair-persistence-test.sqlite';
        @unlink($tmp);

        $manager = (new DatabaseConnectionFactory())->create(new DatabaseSettings(
            driver: DatabaseSettings::DRIVER_SQLITE,
            database: $tmp,
        ));

        $db = $manager->database('default');
        self::assertInstanceOf(SQLiteDriver::class, $db->getDriver());

        @unlink($tmp);
    }
}
