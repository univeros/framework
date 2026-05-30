<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Spec\Emitter;

use Altair\Scaffold\Sdk\Model\OpenApiDocument;
use Altair\Scaffold\Sdk\Model\OperationModel;
use Altair\Scaffold\Sdk\Model\ResponseModel;
use Altair\Scaffold\Sdk\Model\SchemaType;
use Altair\Scaffold\Spec\Emitter\Exception\UnmappableSchemaException;
use Altair\Scaffold\Spec\Emitter\SchemaMapper;
use PHPUnit\Framework\TestCase;

final class SchemaMapperTest extends TestCase
{
    public function testPathParametersBecomeRequiredStringInputs(): void
    {
        $operation = $this->operation('GET', '/users/{id}', pathParameters: ['id']);
        $fields = (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);

        self::assertSame([
            ['name' => 'id', 'type' => 'string', 'rules' => ['required']],
        ], $fields);
    }

    public function testRequestBodyObjectPropertiesBecomeInputs(): void
    {
        $body = SchemaType::object([
            'email' => ['schema' => SchemaType::scalar('string'), 'required' => true],
            'age' => ['schema' => SchemaType::scalar('integer'), 'required' => false],
        ]);
        $operation = $this->operation('POST', '/users', requestBody: $body);

        $fields = (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);

        self::assertSame([
            ['name' => 'email', 'type' => 'string', 'rules' => ['required']],
            ['name' => 'age', 'type' => 'int', 'rules' => []],
        ], $fields);
    }

    public function testEnumInputEmitsInRule(): void
    {
        $body = SchemaType::object([
            'role' => ['schema' => SchemaType::enum(['admin', 'member']), 'required' => true],
        ]);
        $operation = $this->operation('POST', '/users', requestBody: $body);

        $fields = (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);

        self::assertSame([
            ['name' => 'role', 'type' => 'string', 'rules' => ['required', 'in:admin,member']],
        ], $fields);
    }

    public function testArrayInputEmitsArrayType(): void
    {
        $body = SchemaType::object([
            'tags' => ['schema' => SchemaType::arrayOf(SchemaType::scalar('string')), 'required' => false],
        ]);
        $operation = $this->operation('POST', '/users', requestBody: $body);

        $fields = (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);

        self::assertSame([
            ['name' => 'tags', 'type' => 'array', 'rules' => []],
        ], $fields);
    }

    public function testNestedObjectInputRaises(): void
    {
        $body = SchemaType::object([
            'address' => ['schema' => SchemaType::object([]), 'required' => true],
        ]);
        $operation = $this->operation('POST', '/users', requestBody: $body);

        $this->expectException(UnmappableSchemaException::class);
        $this->expectExceptionMessage('nested objects in inputs are not yet supported');
        (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);
    }

    public function testArrayOfObjectsInputRaises(): void
    {
        $body = SchemaType::object([
            'items' => ['schema' => SchemaType::arrayOf(SchemaType::object([])), 'required' => true],
        ]);
        $operation = $this->operation('POST', '/users', requestBody: $body);

        $this->expectException(UnmappableSchemaException::class);
        $this->expectExceptionMessage('arrays of objects are not yet supported');
        (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);
    }

    public function testResponseObjectPropertiesBecomeBodyFields(): void
    {
        $schema = SchemaType::object([
            'id' => ['schema' => SchemaType::scalar('string'), 'required' => true],
            'email' => ['schema' => SchemaType::scalar('string'), 'required' => true],
        ]);
        $operation = $this->operation('GET', '/users/{id}', pathParameters: ['id'], responses: [
            new ResponseModel(status: '200', schema: $schema, description: 'OK'),
        ]);

        $outputs = (new SchemaMapper())->outputs($this->emptyDocument(), $operation);

        self::assertSame([
            200 => ['id' => 'string', 'email' => 'string'],
        ], $outputs);
    }

    public function testResponseTopLevelRefWrapsAsField(): void
    {
        $operation = $this->operation('GET', '/users/{id}', pathParameters: ['id'], responses: [
            new ResponseModel(status: '200', schema: SchemaType::ref('User')),
        ]);

        $outputs = (new SchemaMapper())->outputs($this->emptyDocument(), $operation);

        self::assertSame([200 => ['user' => 'App\\User\\User']], $outputs);
    }

    public function testResponseRefPropertyResolvesToFqcn(): void
    {
        $schema = SchemaType::object([
            'owner' => ['schema' => SchemaType::ref('User'), 'required' => true],
        ]);
        $operation = $this->operation('GET', '/posts/{id}', pathParameters: ['id'], responses: [
            new ResponseModel(status: '200', schema: $schema),
        ]);
        $document = new OpenApiDocument(
            title: 'X',
            version: '1.0',
            operations: [],
            namedSchemas: ['User' => SchemaType::object([])],
        );

        $outputs = (new SchemaMapper())->outputs($document, $operation);

        self::assertSame([200 => ['owner' => 'App\\User\\User']], $outputs);
    }

    public function testResponseArrayRendersListType(): void
    {
        $schema = SchemaType::object([
            'tags' => ['schema' => SchemaType::arrayOf(SchemaType::scalar('string')), 'required' => true],
        ]);
        $operation = $this->operation('GET', '/users/{id}', pathParameters: ['id'], responses: [
            new ResponseModel(status: '200', schema: $schema),
        ]);

        $outputs = (new SchemaMapper())->outputs($this->emptyDocument(), $operation);

        self::assertSame([200 => ['tags' => 'list<string>']], $outputs);
    }

    public function testResponseSkippedWhenNoSchema(): void
    {
        $operation = $this->operation('DELETE', '/users/{id}', pathParameters: ['id'], responses: [
            new ResponseModel(status: '204', schema: null, description: 'No Content'),
            new ResponseModel(status: 'default', schema: null, description: 'Error'),
        ]);

        $outputs = (new SchemaMapper())->outputs($this->emptyDocument(), $operation);

        self::assertSame([], $outputs);
    }

    public function testResolvesRefInRequestBody(): void
    {
        $body = SchemaType::ref('NewUser');
        $operation = $this->operation('POST', '/users', requestBody: $body);
        $document = new OpenApiDocument(
            title: 'X',
            version: '1.0',
            operations: [],
            namedSchemas: [
                'NewUser' => SchemaType::object([
                    'email' => ['schema' => SchemaType::scalar('string'), 'required' => true],
                ]),
            ],
        );

        $fields = (new SchemaMapper())->inputFields($document, $operation);

        self::assertSame([
            ['name' => 'email', 'type' => 'string', 'rules' => ['required']],
        ], $fields);
    }

    public function testDanglingRefRaises(): void
    {
        $operation = $this->operation('POST', '/users', requestBody: SchemaType::ref('Missing'));

        $this->expectException(UnmappableSchemaException::class);
        $this->expectExceptionMessage("ref 'Missing' is not defined in components/schemas");
        (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);
    }

    public function testRefCycleRaises(): void
    {
        $document = new OpenApiDocument(
            title: 'X',
            version: '1.0',
            operations: [],
            namedSchemas: ['Loop' => SchemaType::ref('Loop')],
        );
        $operation = $this->operation('POST', '/users', requestBody: SchemaType::ref('Loop'));

        $this->expectException(UnmappableSchemaException::class);
        $this->expectExceptionMessage('ref cycle');
        (new SchemaMapper())->inputFields($document, $operation);
    }

    public function testJsonPointerLocatesOffendingProperty(): void
    {
        $body = SchemaType::object([
            'address' => ['schema' => SchemaType::object([]), 'required' => true],
        ]);
        $operation = $this->operation('POST', '/users', requestBody: $body);

        try {
            (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);
            self::fail('Expected UnmappableSchemaException');
        } catch (UnmappableSchemaException $unmappableSchemaException) {
            self::assertStringContainsString('#/paths/~1users/post/requestBody/content/application~1json/schema/properties/address', $unmappableSchemaException->jsonPointer);
        }
    }

    /**
     * @param list<string>        $pathParameters
     * @param list<ResponseModel> $responses
     */
    private function operation(
        string $method,
        string $path,
        string $operationId = '',
        array $pathParameters = [],
        ?SchemaType $requestBody = null,
        array $responses = [],
    ): OperationModel {
        return new OperationModel(
            operationId: $operationId,
            method: $method,
            path: $path,
            pathParameters: $pathParameters,
            requestBody: $requestBody,
            responses: $responses,
        );
    }

    private function emptyDocument(): OpenApiDocument
    {
        return new OpenApiDocument(title: 'X', version: '1.0', operations: []);
    }
}
