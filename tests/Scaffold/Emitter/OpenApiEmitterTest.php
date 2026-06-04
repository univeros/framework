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
