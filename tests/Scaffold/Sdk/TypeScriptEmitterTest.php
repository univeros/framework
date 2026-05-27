<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Sdk;

use Altair\Scaffold\Sdk\EmittedSdk;
use Altair\Scaffold\Sdk\Model\OpenApiParser;
use Altair\Scaffold\Sdk\TypeScript\TypeScriptEmitter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypeScriptEmitter::class)]
#[CoversClass(EmittedSdk::class)]
class TypeScriptEmitterTest extends TestCase
{
    private function emit(bool $multiFile = false): string
    {
        $doc = (new OpenApiParser())->parseYaml((string) file_get_contents(__DIR__ . '/Fixtures/users-api.yaml'));

        return (new TypeScriptEmitter())->emit($doc, $multiFile)->single();
    }

    public function testEmitsEnumAsUnionType(): void
    {
        $ts = $this->emit();
        $this->assertStringContainsString("export type UserRole = 'admin' | 'member' | 'viewer';", $ts);
    }

    public function testEmitsObjectAsInterface(): void
    {
        $ts = $this->emit();
        $this->assertStringContainsString('export interface User {', $ts);
        $this->assertStringContainsString('id: string;', $ts);
        $this->assertStringContainsString('role: UserRole;', $ts);
        // Optional field uses `?`.
        $this->assertStringContainsString('created_at?: string;', $ts);
    }

    public function testEmitsStatusDiscriminatedUnion(): void
    {
        $ts = $this->emit();
        $this->assertStringContainsString('export type CreateUserResponse =', $ts);
        $this->assertStringContainsString('{ status: 201; data:', $ts);
        $this->assertStringContainsString('{ status: 422; data:', $ts);
    }

    public function testEmitsTreeShakeableFunctionPerOperation(): void
    {
        $ts = $this->emit();
        $this->assertStringContainsString('export async function createUser(', $ts);
        $this->assertStringContainsString('export async function getUser(', $ts);
        // Path parameter becomes a function arg + template literal.
        $this->assertStringContainsString('id: string', $ts);
        $this->assertStringContainsString('`/users/${id}`', $ts);
    }

    public function testEmitsRuntimeHelpers(): void
    {
        $ts = $this->emit();
        $this->assertStringContainsString('export interface ApiOptions', $ts);
        $this->assertStringContainsString('export class ApiError extends Error', $ts);
        $this->assertStringContainsString('async function request(', $ts);
        $this->assertStringContainsString('do not edit by hand', $ts);
    }

    public function testOutputIsDeterministic(): void
    {
        $this->assertSame($this->emit(), $this->emit());
    }

    public function testMultiFileSplitsTypesAndClient(): void
    {
        $doc = (new OpenApiParser())->parseYaml((string) file_get_contents(__DIR__ . '/Fixtures/users-api.yaml'));
        $emitted = (new TypeScriptEmitter())->emit($doc, multiFile: true);

        $this->assertTrue($emitted->isMultiFile());
        $this->assertArrayHasKey('types.ts', $emitted->files);
        $this->assertArrayHasKey('client.ts', $emitted->files);
        $this->assertStringContainsString('export interface User', $emitted->files['types.ts']);
        $this->assertStringContainsString('export async function createUser', $emitted->files['client.ts']);
    }
}
