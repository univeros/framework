<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Scaffold\Emitter\RepositoryEmitter;
use Altair\Tests\Scaffold\Support\Snapshots;
use Altair\Tests\Scaffold\Support\SpecFixture;
use PHPUnit\Framework\TestCase;

final class RepositoryEmitterTest extends TestCase
{
    public function testEmitsRepositoryExtendingCycleRepository(): void
    {
        $file = (new RepositoryEmitter())->emit(SpecFixture::createUserWithPersistence());

        self::assertSame(EmittedFileKind::Repository, $file->kind);
        self::assertSame('app/User/UserRepository.php', $file->relativePath);
        self::assertStringContainsString('final class UserRepository extends CycleRepository', $file->contents);
        self::assertStringContainsString('parent::__construct(User::class', $file->contents);
    }

    public function testMatchesGoldenSnapshot(): void
    {
        $file = (new RepositoryEmitter())->emit(SpecFixture::createUserWithPersistence());
        Snapshots::assertMatches($this, 'UserRepository.php.txt', $file->contents);
    }
}
