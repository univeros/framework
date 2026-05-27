<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Sdk;

use Altair\Scaffold\Sdk\EmitterRegistry;
use Altair\Scaffold\Sdk\Exception\SdkException;
use Altair\Scaffold\Sdk\Python\PythonEmitter;
use Altair\Scaffold\Sdk\TypeScript\TypeScriptEmitter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmitterRegistry::class)]
class EmitterRegistryTest extends TestCase
{
    public function testDefaultRegistersTypeScriptAndPython(): void
    {
        $registry = EmitterRegistry::default();
        $this->assertSame(['typescript', 'python'], $registry->available());
        $this->assertInstanceOf(TypeScriptEmitter::class, $registry->get('typescript'));
        $this->assertInstanceOf(PythonEmitter::class, $registry->get('python'));
    }

    public function testGetIsCaseInsensitive(): void
    {
        $this->assertInstanceOf(TypeScriptEmitter::class, EmitterRegistry::default()->get('TypeScript'));
    }

    public function testHasReportsAvailability(): void
    {
        $registry = EmitterRegistry::default();
        $this->assertTrue($registry->has('python'));
        $this->assertFalse($registry->has('go'));
    }

    public function testUnknownLanguageThrows(): void
    {
        $this->expectException(SdkException::class);
        EmitterRegistry::default()->get('go');
    }
}
