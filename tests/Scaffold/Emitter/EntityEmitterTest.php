<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Scaffold\Emitter\EntityEmitter;
use Altair\Tests\Scaffold\Support\Snapshots;
use Altair\Tests\Scaffold\Support\SpecFixture;
use PHPUnit\Framework\TestCase;

final class EntityEmitterTest extends TestCase
{
    public function testEmitsCycleAnnotatedEntity(): void
    {
        $file = (new EntityEmitter())->emit(SpecFixture::createUserWithPersistence());

        self::assertSame(EmittedFileKind::Entity, $file->kind);
        self::assertSame('app/User/User.php', $file->relativePath);
        self::assertStringContainsString('final class User', $file->contents);
        self::assertStringContainsString("Entity(table: 'users')", $file->contents);
        self::assertStringContainsString('#[Column(', $file->contents);
    }

    public function testMatchesGoldenSnapshot(): void
    {
        $file = (new EntityEmitter())->emit(SpecFixture::createUserWithPersistence());
        Snapshots::assertMatches($this, 'User.php.txt', $file->contents);
    }
}
