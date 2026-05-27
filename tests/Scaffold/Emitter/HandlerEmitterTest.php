<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Scaffold\Emitter\HandlerEmitter;
use Altair\Tests\Scaffold\Support\SpecFixture;
use PHPUnit\Framework\TestCase;

final class HandlerEmitterTest extends TestCase
{
    public function testEmitsHandlerWithAsHandlerAttribute(): void
    {
        $queue = SpecFixture::createUserWithQueue()->queue[0];
        $file = (new HandlerEmitter())->emit($queue);

        self::assertSame(EmittedFileKind::Handler, $file->kind);
        self::assertSame('app/Messages/SendWelcomeEmailHandler.php', $file->relativePath);
        self::assertStringContainsString('namespace App\\Messages;', $file->contents);
        self::assertStringContainsString('use Altair\\Messaging\\Attribute\\AsHandler;', $file->contents);
        self::assertStringContainsString('#[AsHandler(SendWelcomeEmail::class)]', $file->contents);
        self::assertStringContainsString('public function __invoke(SendWelcomeEmail $message): void', $file->contents);
    }
}
