<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Scaffold\Emitter\InputEmitter;
use Altair\Tests\Scaffold\Support\SpecFixture;
use Altair\Tests\Scaffold\Support\Snapshots;
use PHPUnit\Framework\TestCase;

final class InputEmitterTest extends TestCase
{
    public function testEmitsReadonlyDtoWithFields(): void
    {
        $file = (new InputEmitter())->emit(SpecFixture::createUser());

        self::assertSame(EmittedFileKind::Input, $file->kind);
        self::assertSame('app/Http/Inputs/CreateUserInput.php', $file->relativePath);
        self::assertStringContainsString('final readonly class CreateUserInput', $file->contents);
        self::assertStringContainsString('public string $email', $file->contents);
        self::assertStringContainsString('public string $password', $file->contents);
        self::assertStringContainsString("public static function rules(): array", $file->contents);
        self::assertStringContainsString("'email' => ['email', 'required']", $file->contents);
    }

    public function testMatchesGoldenSnapshot(): void
    {
        $file = (new InputEmitter())->emit(SpecFixture::createUser());
        Snapshots::assertMatches($this, 'CreateUserInput.php.txt', $file->contents);
    }
}
