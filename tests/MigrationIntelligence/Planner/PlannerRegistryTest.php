<?php

declare(strict_types=1);

namespace Altair\Tests\MigrationIntelligence\Planner;

use Altair\MigrationIntelligence\Exception\MigrationIntelligenceException;
use Altair\MigrationIntelligence\Planner\MySqlPlanner;
use Altair\MigrationIntelligence\Planner\PlannerRegistry;
use Altair\MigrationIntelligence\Planner\PostgresPlanner;
use Altair\MigrationIntelligence\Planner\SqlitePlanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlannerRegistry::class)]
class PlannerRegistryTest extends TestCase
{
    public function testDefaultsShipThreeDialects(): void
    {
        $registry = new PlannerRegistry();

        $this->assertInstanceOf(PostgresPlanner::class, $registry->get('postgres'));
        $this->assertInstanceOf(MySqlPlanner::class, $registry->get('mysql'));
        $this->assertInstanceOf(SqlitePlanner::class, $registry->get('sqlite'));
    }

    public function testNormalizesDriverAliases(): void
    {
        $registry = new PlannerRegistry();

        $this->assertTrue($registry->has('postgresql'));
        $this->assertTrue($registry->has('PGSQL'));
        $this->assertTrue($registry->has('mariadb'));
        $this->assertTrue($registry->has('sqlite3'));
        $this->assertInstanceOf(PostgresPlanner::class, $registry->get('postgresql'));
    }

    public function testUnknownDriverThrows(): void
    {
        $this->expectException(MigrationIntelligenceException::class);
        (new PlannerRegistry())->get('oracle');
    }
}
