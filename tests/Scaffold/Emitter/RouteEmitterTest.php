<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Scaffold\Emitter\RouteEmitter;
use Altair\Tests\Scaffold\Support\SpecFixture;
use PHPUnit\Framework\TestCase;

final class RouteEmitterTest extends TestCase
{
    public function testEmitsSingleRouteEntry(): void
    {
        $file = (new RouteEmitter())->emit(SpecFixture::createUser());

        self::assertSame(EmittedFileKind::Route, $file->kind);
        self::assertSame('config/routes.php', $file->relativePath);
        self::assertStringContainsString("'POST'", $file->contents);
        self::assertStringContainsString("'/users'", $file->contents);
        self::assertStringContainsString('App\\Http\\Actions\\CreateUserAction::class', $file->contents);
    }
}
