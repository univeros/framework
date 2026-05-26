<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Scaffold\Emitter\ResponderEmitter;
use Altair\Tests\Scaffold\Support\SpecFixture;
use Altair\Tests\Scaffold\Support\Snapshots;
use PHPUnit\Framework\TestCase;

final class ResponderEmitterTest extends TestCase
{
    public function testEmitsResponderImplementingFrameworkContract(): void
    {
        $file = (new ResponderEmitter())->emit(SpecFixture::createUser());

        self::assertSame(EmittedFileKind::Responder, $file->kind);
        self::assertSame('app/Http/Responders/CreateUserResponder.php', $file->relativePath);
        self::assertStringContainsString('implements ResponderInterface', $file->contents);
        self::assertStringContainsString('return [201, 409, 422];', $file->contents);
    }

    public function testMatchesGoldenSnapshot(): void
    {
        $file = (new ResponderEmitter())->emit(SpecFixture::createUser());
        Snapshots::assertMatches($this, 'CreateUserResponder.php.txt', $file->contents);
    }
}
