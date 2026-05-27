<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Sdk;

use Altair\Scaffold\Sdk\Model\OpenApiParser;
use Altair\Scaffold\Sdk\Python\PythonEmitter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PythonEmitter::class)]
class PythonEmitterTest extends TestCase
{
    private function emit(bool $multiFile = false): string
    {
        $doc = (new OpenApiParser())->parseYaml((string) file_get_contents(__DIR__ . '/Fixtures/users-api.yaml'));

        return (new PythonEmitter())->emit($doc, $multiFile)->single();
    }

    public function testEmitsEnumClass(): void
    {
        $py = $this->emit();
        $this->assertStringContainsString('class UserRole(str, Enum):', $py);
        $this->assertStringContainsString('ADMIN = "admin"', $py);
        $this->assertStringContainsString('VIEWER = "viewer"', $py);
    }

    public function testEmitsPydanticModel(): void
    {
        $py = $this->emit();
        $this->assertStringContainsString('class User(BaseModel):', $py);
        $this->assertStringContainsString('id: str', $py);
        $this->assertStringContainsString('role: UserRole', $py);
        // Optional field gets `| None`.
        $this->assertStringContainsString('created_at: str | None', $py);
    }

    public function testEmitsSyncAndAsyncClients(): void
    {
        $py = $this->emit();
        $this->assertStringContainsString('class Client:', $py);
        $this->assertStringContainsString('class AsyncClient:', $py);
        $this->assertStringContainsString('def create_user(self', $py);
        $this->assertStringContainsString('async def create_user(self', $py);
    }

    public function testPathParamMethodUsesFstring(): void
    {
        $py = $this->emit();
        $this->assertStringContainsString('def get_user(self, id: str)', $py);
        $this->assertStringContainsString('f"/users/{id}"', $py);
    }

    public function testImportsHttpxAndPydantic(): void
    {
        $py = $this->emit();
        $this->assertStringContainsString('import httpx', $py);
        $this->assertStringContainsString('from pydantic import BaseModel, Field', $py);
    }

    public function testOutputIsDeterministic(): void
    {
        $this->assertSame($this->emit(), $this->emit());
    }

    public function testMultiFileSplitsModelsAndClient(): void
    {
        $doc = (new OpenApiParser())->parseYaml((string) file_get_contents(__DIR__ . '/Fixtures/users-api.yaml'));
        $emitted = (new PythonEmitter())->emit($doc, multiFile: true);

        $this->assertArrayHasKey('models.py', $emitted->files);
        $this->assertArrayHasKey('client.py', $emitted->files);
        $this->assertStringContainsString('class User(BaseModel):', $emitted->files['models.py']);
        $this->assertStringContainsString('class AsyncClient:', $emitted->files['client.py']);
    }
}
