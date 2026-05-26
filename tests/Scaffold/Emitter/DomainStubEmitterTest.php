<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\DomainStubEmitter;
use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Tests\Scaffold\Support\SpecFixture;
use Altair\Tests\Scaffold\Support\Snapshots;
use PHPUnit\Framework\TestCase;

final class DomainStubEmitterTest extends TestCase
{
    public function testEmitsDomainStubWithTodo(): void
    {
        $file = (new DomainStubEmitter())->emit(SpecFixture::createUser());

        self::assertSame(EmittedFileKind::DomainStub, $file->kind);
        self::assertSame('app/User/CreateUser.php', $file->relativePath);
        self::assertStringContainsString('final class CreateUser', $file->contents);
        self::assertStringContainsString('TODO: implement', $file->contents);
    }

    public function testMatchesGoldenSnapshot(): void
    {
        $file = (new DomainStubEmitter())->emit(SpecFixture::createUser());
        Snapshots::assertMatches($this, 'CreateUser.php.txt', $file->contents);
    }
}
