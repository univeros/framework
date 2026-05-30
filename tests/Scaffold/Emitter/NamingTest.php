<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\Naming;
use Altair\Scaffold\Spec\Ast\PersistenceEntitySpec;
use Altair\Scaffold\Spec\Ast\PersistenceFieldSpec;
use Altair\Scaffold\Spec\Ast\PersistenceSpec;
use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Tests\Scaffold\Support\SpecFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Naming::class)]
final class NamingTest extends TestCase
{
    public function testMigrationPathIsDeterministicWhenTimestampNotProvided(): void
    {
        $naming = new Naming();
        $spec = SpecFixture::createUserWithPersistence();

        $first = $naming->migrationPath($spec);
        $second = $naming->migrationPath($spec);
        $third = $naming->migrationPath($spec);

        self::assertSame($first, $second);
        self::assertSame($first, $third);
        self::assertNotSame('', $first);
    }

    public function testMigrationClassNameIsDeterministicWhenTimestampNotProvided(): void
    {
        $naming = new Naming();
        $spec = SpecFixture::createUserWithPersistence();

        $first = $naming->migrationClassName($spec);
        $second = $naming->migrationClassName($spec);

        self::assertSame($first, $second);
        self::assertStringStartsWith('M', $first);
        self::assertStringContainsString('CreateUsersTable', $first);
    }

    public function testMigrationStampVariesAcrossEntityTables(): void
    {
        $naming = new Naming();
        $usersPath = $naming->migrationPath(SpecFixture::createUserWithPersistence());
        $ordersPath = $naming->migrationPath($this->orderSpec());

        self::assertNotSame($usersPath, $ordersPath, 'different entity tables must produce different migration stamps');
    }

    public function testMigrationStampFallsInsideTheFixedPreEpochWindow(): void
    {
        $naming = new Naming();
        $path = $naming->migrationPath(SpecFixture::createUserWithPersistence());

        // Path shape: database/migrations/<Ymd.His>_0_create_<table>.php
        preg_match('#/(\d{8})\.(\d{6})_#', $path, $matches);
        self::assertCount(3, $matches, 'migration filename did not match expected timestamp pattern');

        $year = (int) substr($matches[1], 0, 4);
        self::assertGreaterThanOrEqual(2000, $year);
        self::assertLessThan(2010, $year, 'deterministic stamps must stay inside the fixed 10y window so later wall-clock migrations sort after');
    }

    public function testExplicitTimestampStillWinsOverDeterministicDefault(): void
    {
        $naming = new Naming();
        $spec = SpecFixture::createUserWithPersistence();

        $derived = $naming->migrationPath($spec);
        $explicit = $naming->migrationPath($spec, 1777334400); // 2026-04-28 00:00:00 UTC

        self::assertNotSame($derived, $explicit);
        self::assertStringContainsString('20260428.000000', $explicit);
    }

    private function orderSpec(): Spec
    {
        $base = SpecFixture::createUserWithPersistence();

        return new Spec(
            endpoint: $base->endpoint,
            inputs: $base->inputs,
            outputs: $base->outputs,
            domain: $base->domain,
            sourcePath: $base->sourcePath,
            persistence: new PersistenceSpec(
                entity: new PersistenceEntitySpec(
                    class: 'App\\Order\\Order',
                    table: 'orders',
                    fields: [
                        new PersistenceFieldSpec(name: 'id', type: 'uuid', primary: true),
                        new PersistenceFieldSpec(name: 'total', type: 'integer'),
                    ],
                ),
                repository: 'App\\Order\\OrderRepository',
            ),
        );
    }
}
