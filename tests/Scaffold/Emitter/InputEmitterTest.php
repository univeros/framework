<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Scaffold\Emitter\InputEmitter;
use Altair\Scaffold\Spec\Parser;
use Altair\Tests\Scaffold\Support\SpecFixture;
use Altair\Tests\Scaffold\Support\Snapshots;
use PHPUnit\Framework\TestCase;

final class InputEmitterTest extends TestCase
{
    public function testEmitsArrayWithShapePhpdocForNestedObject(): void
    {
        $yaml = <<<'YAML'
            endpoint: { method: POST, path: /pets, summary: Create pet }
            input:
              name: { type: string, rules: [required] }
              category:
                type: object
                rules: [required]
                fields:
                  id: { type: int }
                  name: { type: string, rules: [required] }
            domain: { class: App\Pet\CreatePet }
            YAML;
        $spec = (new Parser())->parseString($yaml);

        $file = (new InputEmitter())->emit($spec);

        self::assertStringContainsString('public array $category', $file->contents);
        self::assertStringContainsString('@param array{id: int, name: string} $category', $file->contents);
        // Scalar siblings keep their native type, no shape doc.
        self::assertStringContainsString('public string $name', $file->contents);
    }

    public function testEmitsListShapePhpdocForArrayOfObjects(): void
    {
        $yaml = <<<'YAML'
            endpoint: { method: POST, path: /pets, summary: Create pet }
            input:
              tags:
                type: array
                fields:
                  id: { type: int }
            domain: { class: App\Pet\CreatePet }
            YAML;
        $spec = (new Parser())->parseString($yaml);

        $file = (new InputEmitter())->emit($spec);

        self::assertStringContainsString('public ?array $tags = null', $file->contents);
        self::assertStringContainsString('@param list<array{id: int}> $tags', $file->contents);
    }


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
