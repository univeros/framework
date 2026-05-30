<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Spec\Emitter;

use Altair\Scaffold\Sdk\Model\OpenApiDocument;
use Altair\Scaffold\Sdk\Model\OperationModel;
use Altair\Scaffold\Sdk\Model\ResponseModel;
use Altair\Scaffold\Sdk\Model\SchemaType;
use Altair\Scaffold\Spec\Emitter\OperationMapper;
use PHPUnit\Framework\TestCase;

final class OperationMapperTest extends TestCase
{
    public function testAssemblesEndpointInputOutputAndDomain(): void
    {
        $requestBody = SchemaType::object([
            'email' => ['schema' => SchemaType::scalar('string'), 'required' => true],
        ]);
        $responseSchema = SchemaType::object([
            'id' => ['schema' => SchemaType::scalar('string'), 'required' => true],
        ]);
        $operation = new OperationModel(
            operationId: 'createUser',
            method: 'POST',
            path: '/users',
            pathParameters: [],
            requestBody: $requestBody,
            responses: [new ResponseModel(status: '201', schema: $responseSchema)],
            summary: 'Create a new user',
        );

        $spec = (new OperationMapper())->map($this->emptyDocument(), $operation);

        self::assertSame('POST', $spec['endpoint']['method']);
        self::assertSame('/users', $spec['endpoint']['path']);
        self::assertSame('Create a new user', $spec['endpoint']['summary']);
        self::assertSame(['users'], $spec['endpoint']['tags']);

        self::assertArrayHasKey('email', $spec['input']);
        self::assertSame('string', $spec['input']['email']['type']);
        self::assertSame(['required'], $spec['input']['email']['rules']);

        self::assertSame(['id' => 'string'], $spec['output'][201]['body']);

        self::assertSame('App\\User\\CreateUser', $spec['domain']['class']);
        self::assertSame('__invoke', $spec['domain']['invocation']);
    }

    public function testOmitsInputBlockWhenNoInputs(): void
    {
        $operation = new OperationModel(
            operationId: 'listUsers',
            method: 'GET',
            path: '/users',
            pathParameters: [],
            requestBody: null,
            responses: [],
        );

        $spec = (new OperationMapper())->map($this->emptyDocument(), $operation);

        self::assertArrayNotHasKey('input', $spec);
        self::assertArrayNotHasKey('output', $spec);
        self::assertArrayHasKey('endpoint', $spec);
        self::assertArrayHasKey('domain', $spec);
    }

    public function testTagsDerivedFromResource(): void
    {
        $operation = new OperationModel(
            operationId: '',
            method: 'GET',
            path: '/posts/{id}',
            pathParameters: ['id'],
            requestBody: null,
            responses: [],
        );

        $spec = (new OperationMapper())->map($this->emptyDocument(), $operation);

        self::assertSame(['posts'], $spec['endpoint']['tags']);
    }

    private function emptyDocument(): OpenApiDocument
    {
        return new OpenApiDocument(title: 'X', version: '1.0', operations: []);
    }
}
