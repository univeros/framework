<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Scaffold\Emitter\MessageEmitter;
use Altair\Tests\Scaffold\Support\SpecFixture;
use PHPUnit\Framework\TestCase;

final class MessageEmitterTest extends TestCase
{
    public function testEmitsReadonlyMessageDto(): void
    {
        $queue = SpecFixture::createUserWithQueue()->queue[0];
        $file = (new MessageEmitter())->emit($queue);

        self::assertSame(EmittedFileKind::Message, $file->kind);
        self::assertSame('app/Messages/SendWelcomeEmail.php', $file->relativePath);
        self::assertStringContainsString('namespace App\\Messages;', $file->contents);
        self::assertStringContainsString('final readonly class SendWelcomeEmail', $file->contents);
        self::assertStringContainsString('public string $userId', $file->contents);
        self::assertStringContainsString('public string $email', $file->contents);
    }
}
