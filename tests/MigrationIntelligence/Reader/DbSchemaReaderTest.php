<?php

declare(strict_types=1);

namespace Altair\Tests\MigrationIntelligence\Reader;

use Altair\MigrationIntelligence\Reader\DbSchemaReader;
use Altair\MigrationIntelligence\Schema\ColumnType;
use Altair\Tests\MigrationIntelligence\Support\SqliteDatabaseFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DbSchemaReader::class)]
class DbSchemaReaderTest extends TestCase
{
    public function testReturnsNullForMissingTable(): void
    {
        $database = SqliteDatabaseFactory::memory();

        $this->assertNull((new DbSchemaReader())->read($database, 'ghosts'));
    }

    public function testReadsColumnsPrimaryKeyAndUniqueIndex(): void
    {
        $database = SqliteDatabaseFactory::memory();
        $database->execute(
            'CREATE TABLE users (id INTEGER PRIMARY KEY, email VARCHAR(255) NOT NULL, nickname VARCHAR(120))',
        );
        $database->execute('CREATE UNIQUE INDEX users_email ON users (email)');

        $shape = (new DbSchemaReader())->read($database, 'users');

        $this->assertNotNull($shape);
        $this->assertSame('users', $shape->name);
        $this->assertContains('id', $shape->columnNames());
        $this->assertContains('email', $shape->columnNames());

        $id = $shape->column('id');
        $this->assertNotNull($id);
        $this->assertTrue($id->primary);

        $email = $shape->column('email');
        $this->assertNotNull($email);
        $this->assertFalse($email->nullable);
        $this->assertSame(ColumnType::STRING, $email->type);

        $nickname = $shape->column('nickname');
        $this->assertNotNull($nickname);
        $this->assertTrue($nickname->nullable);

        $uniqueIndex = $shape->index('email');
        $this->assertNotNull($uniqueIndex);
        $this->assertTrue($uniqueIndex->unique);
    }
}
