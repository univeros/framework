<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\ActionEmitter;
use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Tests\Scaffold\Support\SpecFixture;
use Altair\Tests\Scaffold\Support\Snapshots;
use PHPUnit\Framework\TestCase;

final class ActionEmitterTest extends TestCase
{
    public function testEmitsActionExtendingFrameworkBase(): void
    {
        $file = (new ActionEmitter())->emit(SpecFixture::createUser());

        self::assertSame(EmittedFileKind::Action, $file->kind);
        self::assertSame('app/Http/Actions/CreateUserAction.php', $file->relativePath);
        self::assertStringContainsString('final class CreateUserAction extends Action', $file->contents);
        self::assertStringContainsString('App\\User\\CreateUser::class', $file->contents);
    }

    public function testMatchesGoldenSnapshot(): void
    {
        $file = (new ActionEmitter())->emit(SpecFixture::createUser());
        Snapshots::assertMatches($this, 'CreateUserAction.php.txt', $file->contents);
    }
}
