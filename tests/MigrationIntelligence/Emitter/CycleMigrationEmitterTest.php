<?php

declare(strict_types=1);

namespace Altair\Tests\MigrationIntelligence\Emitter;

use Altair\MigrationIntelligence\Emitter\CycleMigrationEmitter;
use Altair\MigrationIntelligence\Plan\PlanBuilder;
use Altair\MigrationIntelligence\Plan\PlanRequest;
use Altair\MigrationIntelligence\Schema\ColumnShape;
use Altair\MigrationIntelligence\Schema\ColumnType;
use Altair\MigrationIntelligence\Schema\TableShape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CycleMigrationEmitter::class)]
class CycleMigrationEmitterTest extends TestCase
{
    private const int FIXED_TS = 1_748_000_000;

    public function testEmitsValidCycleMigrationForAddColumn(): void
    {
        $from = new TableShape('users', [new ColumnShape('id', ColumnType::PRIMARY, primary: true)]);
        $to = new TableShape('users', [
            new ColumnShape('id', ColumnType::PRIMARY, primary: true),
            new ColumnShape('display_name', ColumnType::STRING, nullable: true),
        ]);

        $plan = (new PlanBuilder())->build(new PlanRequest($from, $to, timestamp: self::FIXED_TS));
        $php = (new CycleMigrationEmitter())->emit($plan->migrations[0]);

        $this->assertStringContainsString('declare(strict_types=1);', $php);
        $this->assertStringContainsString('namespace Database\\Migrations;', $php);
        $this->assertStringContainsString('use Cycle\\Migrations\\Migration;', $php);
        $this->assertStringContainsString('extends Migration', $php);
        $this->assertStringContainsString("protected const string DATABASE = 'default';", $php);
        $this->assertStringContainsString("\$this->table('users')", $php);
        $this->assertStringContainsString("->addColumn('display_name', 'string', ['nullable' => true])", $php);
        $this->assertStringContainsString('->update();', $php);
        $this->assertStringContainsString("->dropColumn('display_name')", $php);

        $this->assertTrue($this->isValidPhp($php), 'emitted migration is not syntactically valid PHP');
    }

    public function testEmitsRawSqlForDataMigrationInTwoPhaseRename(): void
    {
        $from = new TableShape('users', [new ColumnShape('password', ColumnType::STRING, nullable: false)]);
        $to = new TableShape('users', [new ColumnShape('password_hash', ColumnType::STRING, nullable: false)]);

        $plan = (new PlanBuilder())->build(new PlanRequest(
            $from,
            $to,
            renames: ['password' => 'password_hash'],
            timestamp: self::FIXED_TS,
        ));

        $phaseOne = (new CycleMigrationEmitter())->emit($plan->migrations[0]);

        $this->assertStringContainsString("->addColumn('password_hash', 'string', ['nullable' => true])", $phaseOne);
        $this->assertStringContainsString(
            '$this->database()->execute(\'UPDATE "users" SET "password_hash" = "password"\');',
            $phaseOne,
        );
        $this->assertTrue($this->isValidPhp($phaseOne));
    }

    private function isValidPhp(string $code): bool
    {
        $file = tempnam(sys_get_temp_dir(), 'mig_') ?: '';
        file_put_contents($file, $code);
        $output = [];
        $status = 0;
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $status);
        unlink($file);

        return $status === 0;
    }
}
