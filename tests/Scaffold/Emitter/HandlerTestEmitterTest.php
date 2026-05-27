<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Scaffold\Emitter\HandlerTestEmitter;
use Altair\Tests\Scaffold\Support\SpecFixture;
use PHPUnit\Framework\TestCase;

final class HandlerTestEmitterTest extends TestCase
{
    public function testEmitsPhpUnitHandlerTest(): void
    {
        $queue = SpecFixture::createUserWithQueue()->queue[0];
        $file = (new HandlerTestEmitter())->emit($queue);

        self::assertSame(EmittedFileKind::HandlerTest, $file->kind);
        self::assertSame('tests/Messages/SendWelcomeEmailHandlerTest.php', $file->relativePath);
        self::assertStringContainsString('namespace Tests\\Messages;', $file->contents);
        self::assertStringContainsString('final class SendWelcomeEmailHandlerTest extends TestCase', $file->contents);
        self::assertStringContainsString('$this->expectException(LogicException::class);', $file->contents);
        self::assertStringContainsString("userId: ''", $file->contents);
    }
}
