<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Emitter;

use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Scaffold\Emitter\TestEmitter;
use Altair\Tests\Scaffold\Support\SpecFixture;
use Altair\Tests\Scaffold\Support\Snapshots;
use PHPUnit\Framework\TestCase;

final class TestEmitterTest extends TestCase
{
    public function testEmitsPhpUnitTestWithStatusAssertions(): void
    {
        $file = (new TestEmitter())->emit(SpecFixture::createUser());

        self::assertSame(EmittedFileKind::Test, $file->kind);
        self::assertSame('tests/Http/Actions/CreateUserActionTest.php', $file->relativePath);
        self::assertStringContainsString('extends TestCase', $file->contents);
        self::assertStringContainsString('self::assertContains(201, $declared)', $file->contents);
        self::assertStringContainsString('self::assertContains(422, $declared)', $file->contents);
        self::assertStringContainsString('self::assertContains(409, $declared)', $file->contents);
    }

    public function testMatchesGoldenSnapshot(): void
    {
        $file = (new TestEmitter())->emit(SpecFixture::createUser());
        Snapshots::assertMatches($this, 'CreateUserActionTest.php.txt', $file->contents);
    }
}
