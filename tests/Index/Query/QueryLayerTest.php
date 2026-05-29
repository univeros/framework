<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Index\Query;

use Altair\Index\Model\ParsedFile;
use Altair\Index\Model\Symbol;
use Altair\Index\Model\SymbolKind;
use Altair\Index\Model\Usage;
use Altair\Index\Model\UsageKind;
use Altair\Index\Query\ImpactQuery;
use Altair\Index\Query\ImpactReport;
use Altair\Index\Query\OrphanQuery;
use Altair\Index\Query\UsageQuery;
use Altair\Index\Storage\Connection;
use Altair\Index\Storage\RowMapper;
use Altair\Index\Storage\SqliteStorage;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UsageQuery::class)]
#[CoversClass(ImpactQuery::class)]
#[CoversClass(ImpactReport::class)]
#[CoversClass(OrphanQuery::class)]
#[CoversClass(SqliteStorage::class)]
#[CoversClass(Connection::class)]
#[CoversClass(RowMapper::class)]
final class QueryLayerTest extends TestCase
{
    private PDO $pdo;

    private SqliteStorage $storage;

    protected function setUp(): void
    {
        $this->pdo = Connection::open(':memory:');
        $this->storage = new SqliteStorage($this->pdo);
        $this->storage->initialise();
        $this->seed();
    }

    public function testFindUsagesReturnsEveryReferenceToASymbol(): void
    {
        $usages = (new UsageQuery($this->pdo))->usages('App\A');

        $kinds = array_map(static fn(Usage $u): string => $u->kind->value, $usages);
        self::assertContains('new', $kinds);
        self::assertContains('spec_endpoint', $kinds);
    }

    public function testImplementersAndExtenders(): void
    {
        $query = new UsageQuery($this->pdo);

        self::assertSame(['App\A'], $query->implementers('App\Handler'));
        self::assertSame(['App\B'], $query->extenders('App\Base'));
    }

    public function testCallersReturnCallSitesWithCallingScope(): void
    {
        $callers = (new UsageQuery($this->pdo))->callers('App\A::run');

        self::assertCount(1, $callers);
        self::assertSame('tests/ATest.php', $callers[0]->file);
        self::assertSame('App\Tests\ATest::testRun', $callers[0]->context);
    }

    public function testUnusedFindsDeadCodeButNotReferencedSymbols(): void
    {
        $unused = array_map(static fn(Symbol $s): string => $s->fqn, (new UsageQuery($this->pdo))->unused());

        self::assertContains('App\Dead', $unused);
        self::assertNotContains('App\A', $unused);
        self::assertNotContains('App\A::run', $unused);
    }

    public function testImpactEnumeratesTestsAndSpecsForAClassAndItsMembers(): void
    {
        $report = (new ImpactQuery($this->pdo))->impact(['App\A']);

        self::assertSame(2, $report->files);
        self::assertSame(1, $report->tests);
        self::assertSame(1, $report->specs);
        self::assertSame(['tests/ATest.php'], $report->testsToRun);
        self::assertSame(['api/a.yaml'], $report->specsAffected);
    }

    public function testImpactWithNoSymbolsIsEmpty(): void
    {
        $report = (new ImpactQuery($this->pdo))->impact([]);

        self::assertSame(0, $report->files);
        self::assertSame([], $report->byFile);
    }

    public function testDanglingSpecTargetsFindReferencesWithoutADeclaration(): void
    {
        $orphans = (new OrphanQuery($this->pdo))->danglingSpecTargets();

        $fqns = array_column($orphans, 'fqn');
        self::assertContains('App\Missing', $fqns);
        self::assertNotContains('App\A', $fqns);
    }

    private function seed(): void
    {
        // Declarations.
        $this->storage->persistFile(new ParsedFile('src/Handler.php', 'h', [
            new Symbol('App\Handler', SymbolKind::Interface_, 'src/Handler.php', 5),
        ], []));

        $this->storage->persistFile(new ParsedFile('src/A.php', 'a', [
            new Symbol('App\A', SymbolKind::Class_, 'src/A.php', 10),
            new Symbol('App\A::run', SymbolKind::Method, 'src/A.php', 14, 'public'),
        ], [
            new Usage('App\Handler', 'src/A.php', 10, UsageKind::Implements_, 'App\A'),
        ]));

        $this->storage->persistFile(new ParsedFile('src/B.php', 'b', [
            new Symbol('App\B', SymbolKind::Class_, 'src/B.php', 8),
        ], [
            new Usage('App\Base', 'src/B.php', 8, UsageKind::Extends_, 'App\B'),
        ]));

        $this->storage->persistFile(new ParsedFile('src/Dead.php', 'd', [
            new Symbol('App\Dead', SymbolKind::Class_, 'src/Dead.php', 4),
        ], []));

        // A test referencing App\A and calling App\A::run.
        $this->storage->persistFile(new ParsedFile('tests/ATest.php', 't', [
            new Symbol('App\Tests\ATest', SymbolKind::Class_, 'tests/ATest.php', 6),
        ], [
            new Usage('App\A', 'tests/ATest.php', 12, UsageKind::New_, 'App\Tests\ATest::testRun'),
            new Usage('App\A::run', 'tests/ATest.php', 13, UsageKind::Call, 'App\Tests\ATest::testRun'),
        ]));

        // A spec referencing App\A (declared) and App\Missing (dangling).
        $this->storage->persistFile(new ParsedFile('api/a.yaml', 's', [], [
            new Usage('App\A', 'api/a.yaml', 0, UsageKind::SpecEndpoint, 'POST /a'),
            new Usage('App\Missing', 'api/a.yaml', 0, UsageKind::SpecEntity, 'POST /a'),
        ]));
    }
}
