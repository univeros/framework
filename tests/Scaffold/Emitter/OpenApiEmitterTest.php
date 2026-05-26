<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Scaffold\Emitter\OpenApiEmitter;
use Altair\Tests\Scaffold\Support\SpecFixture;
use Altair\Tests\Scaffold\Support\Snapshots;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class OpenApiEmitterTest extends TestCase
{
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
