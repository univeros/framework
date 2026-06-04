<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Scaffold\Emitter\OpenApiEmitter;
use Altair\Scaffold\Spec\Parser;
use Altair\Tests\Scaffold\Support\SpecFixture;
use Altair\Tests\Scaffold\Support\Snapshots;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class OpenApiEmitterTest extends TestCase
{
    public function testValidationRulesBecomeSchemaConstraints(): void
    {
        $yaml = <<<'YAML'
            endpoint: { method: POST, path: /users, summary: Create }
            input:
              email: { type: string, rules: [required, email] }
              name: { type: string, rules: ['min:2', 'max:50', 'regex:^[a-z]+$'] }
              age: { type: int, rules: ['min:0', 'max:120'] }
              role: { type: string, rules: ['in:admin,user'] }
            domain: { class: App\User\CreateUser }
            YAML;
        $spec = (new Parser())->parseString($yaml);

        $parsed = Yaml::parse((new OpenApiEmitter())->emit($spec)->contents);
        self::assertIsArray($parsed);
        $props = $parsed['paths']['/users']['post']['requestBody']['content']['application/json']['schema']['properties'];

        self::assertSame('email', $props['email']['format']);
        self::assertSame(2, $props['name']['minLength']);
        self::assertSame(50, $props['name']['maxLength']);
        self::assertSame('^[a-z]+$', $props['name']['pattern']);
        self::assertSame(0, $props['age']['minimum']);
        self::assertSame(120, $props['age']['maximum']);
        self::assertSame(['admin', 'user'], $props['role']['enum']);
    }

    public function testEmitsNonBodyInputsAsParameters(): void
    {
        $yaml = <<<'YAML'
            endpoint: { method: GET, path: /pets, summary: List pets }
            input:
              status: { type: string, in: query, rules: [required] }
              tenant: { type: string, in: header }
            output:
              200: { body: { result: 'list<App\Pet\Pet>' } }
            domain: { class: App\Pet\ListPets }
            YAML;
        $spec = (new Parser())->parseString($yaml);

        $parsed = Yaml::parse((new OpenApiEmitter())->emit($spec)->contents);
        self::assertIsArray($parsed);
        $operation = $parsed['paths']['/pets']['get'];

        self::assertArrayHasKey('parameters', $operation);
        self::assertArrayNotHasKey('requestBody', $operation, 'all inputs are parameters, so there is no body');

        $byName = [];
        foreach ($operation['parameters'] as $parameter) {
            $byName[$parameter['name']] = $parameter;
        }

        self::assertSame('query', $byName['status']['in']);
        self::assertTrue($byName['status']['required']);
        self::assertSame(['type' => 'string'], $byName['status']['schema']);
        self::assertSame('header', $byName['tenant']['in']);
        self::assertFalse($byName['tenant']['required']);
    }

    public function testEmitsOpenApi3Fragment(): void
    {
        $file = (new OpenApiEmitter())->emit(SpecFixture::createUser());

        self::assertSame(EmittedFileKind::OpenApi, $file->kind);
        self::assertSame('docs/openapi/create-user.yaml', $file->relativePath);

        $parsed = Yaml::parse($file->contents);
        self::assertIsArray($parsed);
        self::assertSame('3.1.0', $parsed['openapi']);
        self::assertArrayHasKey('/users', $parsed['paths']);
        self::assertArrayHasKey('post', $parsed['paths']['/users']);
        self::assertArrayHasKey('201', $parsed['paths']['/users']['post']['responses']);
        self::assertArrayHasKey('422', $parsed['paths']['/users']['post']['responses']);
    }

    public function testMatchesGoldenSnapshot(): void
    {
        $file = (new OpenApiEmitter())->emit(SpecFixture::createUser());
        Snapshots::assertMatches($this, 'create-user.openapi.yaml', $file->contents);
    }
}
