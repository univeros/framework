<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Tool;

use Altair\Mcp\Database\SqlReadGuard;
use Altair\Doctor\Process\ProcessResult;
use Altair\Mcp\Database\NullDatabaseGateway;
use Altair\Mcp\Exception\GuardrailException;
use Altair\Mcp\Exception\McpException;
use Altair\Mcp\Guard\ServerMode;
use Altair\Mcp\Support\EventLog;
use Altair\Mcp\Support\ProjectContext;
use Altair\Mcp\Tool\Database\DbMigrateTool;
use Altair\Mcp\Tool\Database\DbQueryTool;
use Altair\Mcp\Tool\Database\DbSchemaTool;
use Altair\Tests\Mcp\Fixtures\FakeDatabaseGateway;
use Altair\Tests\Mcp\Fixtures\FakeProcessRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DbQueryTool::class)]
#[CoversClass(DbSchemaTool::class)]
#[CoversClass(DbMigrateTool::class)]
#[CoversClass(NullDatabaseGateway::class)]
#[CoversClass(SqlReadGuard::class)]
final class DatabaseToolsTest extends TestCase
{
    private function context(): ProjectContext
    {
        return new ProjectContext('/tmp/mcp-db', ProjectContext::detect()->altairSrcDir);
    }

    public function testDbQueryReturnsRowsForSelect(): void
    {
        $gateway = new FakeDatabaseGateway(rows: [['id' => 1, 'email' => 'a@b.c']]);

        $result = (new DbQueryTool($gateway))->call(['sql' => 'SELECT * FROM users']);

        self::assertSame(1, $result['count']);
        self::assertSame('a@b.c', $result['rows'][0]['email']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function writeStatements(): iterable
    {
        yield 'insert' => ['INSERT INTO users (id) VALUES (1)'];
        yield 'update' => ['UPDATE users SET email = NULL'];
        yield 'delete' => ['DELETE FROM users'];
        yield 'drop' => ['DROP TABLE users'];
        yield 'chained' => ['SELECT 1; DROP TABLE users'];
        yield 'cte write' => ['WITH t AS (INSERT INTO users DEFAULT VALUES RETURNING id) SELECT * FROM t'];
        yield 'select into outfile' => ["SELECT * FROM users INTO OUTFILE '/tmp/x.csv'"];
        yield 'select into dumpfile' => ["SELECT data FROM blobs INTO DUMPFILE '/tmp/x.bin'"];
        yield 'select into table' => ['SELECT * INTO archived_users FROM users'];
    }

    #[DataProvider('writeStatements')]
    public function testDbQueryRejectsNonReadStatements(string $sql): void
    {
        $this->expectException(GuardrailException::class);
        (new DbQueryTool(new FakeDatabaseGateway()))->call(['sql' => $sql]);
    }

    public function testDbQueryWithoutDatabaseReportsUnconfigured(): void
    {
        $this->expectException(McpException::class);
        (new DbQueryTool(new NullDatabaseGateway()))->call(['sql' => 'SELECT 1']);
    }

    public function testDbSchemaReturnsTables(): void
    {
        $gateway = new FakeDatabaseGateway(tables: [
            ['table' => 'users', 'columns' => [['name' => 'id', 'type' => 'primary']]],
        ]);

        $result = (new DbSchemaTool($gateway))->call([]);

        self::assertSame(1, $result['count']);
        self::assertSame('users', $result['tables'][0]['table']);
    }

    public function testDbMigrateRequiresAllowWrites(): void
    {
        $tool = new DbMigrateTool($this->context(), new ServerMode(), new EventLog(), new FakeProcessRunner(new ProcessResult(0)));

        $this->expectException(GuardrailException::class);
        $tool->call([]);
    }

    public function testDbMigrateDryRunIsAllowedInReadonly(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(0, 'No pending migrations.', ''));
        $tool = new DbMigrateTool($this->context(), new ServerMode(readonly: true), new EventLog(), $runner);

        $result = $tool->call(['dry_run' => true]);

        self::assertTrue($result['dry_run']);
        self::assertContains('--dry-run', $runner->lastCommand ?? []);
    }

    public function testDbMigrateAppliesWhenWritesAllowed(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(0, 'Migrated.', ''));
        $tool = new DbMigrateTool($this->context(), new ServerMode(allowWrites: true), new EventLog(), $runner);

        $result = $tool->call([]);

        self::assertTrue($result['passed']);
        self::assertNotContains('--dry-run', $runner->lastCommand ?? []);
    }
}
