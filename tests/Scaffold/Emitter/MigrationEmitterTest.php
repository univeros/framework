<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Scaffold\Emitter\MigrationEmitter;
use Altair\Tests\Scaffold\Support\Snapshots;
use Altair\Tests\Scaffold\Support\SpecFixture;
use PHPUnit\Framework\TestCase;

final class MigrationEmitterTest extends TestCase
{
    /**
     * Fixed Unix timestamp used in every test so snapshot output is stable
     * across runs (Tue, 28 Apr 2026 12:00:00 UTC).
     */
    private const int FIXED_TIMESTAMP = 1777334400;

    public function testEmitsCycleMigration(): void
    {
        $file = (new MigrationEmitter())->emit(
            SpecFixture::createUserWithPersistence(),
            self::FIXED_TIMESTAMP,
        );

        self::assertSame(EmittedFileKind::Migration, $file->kind);
        self::assertStringStartsWith('database/migrations/', $file->relativePath);
        self::assertStringContainsString('extends Migration', $file->contents);
        self::assertStringContainsString("->table('users')", $file->contents);
        self::assertStringContainsString("->addColumn('id'", $file->contents);
    }

    public function testMatchesGoldenSnapshot(): void
    {
        $file = (new MigrationEmitter())->emit(
            SpecFixture::createUserWithPersistence(),
            self::FIXED_TIMESTAMP,
        );
        Snapshots::assertMatches($this, 'CreateUsersTable.php.txt', $file->contents);
    }
}
