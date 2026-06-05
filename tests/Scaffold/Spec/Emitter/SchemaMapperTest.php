<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Spec\Emitter;

use Altair\Scaffold\Sdk\Model\OpenApiDocument;
use Altair\Scaffold\Sdk\Model\OperationModel;
use Altair\Scaffold\Sdk\Model\ParameterModel;
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
            ['name' => 'id', 'type' => 'string', 'in' => 'path', 'rules' => ['required']],
        ], $fields);
    }

    public function testQueryHeaderAndEnumParametersBecomeInputs(): void
    {
        $operation = $this->operation('GET', '/pets', parameters: [
            new ParameterModel('status', ParameterModel::IN_QUERY, true, SchemaType::enum(['available', 'sold'])),
            new ParameterModel('limit', ParameterModel::IN_QUERY, false, SchemaType::scalar('integer')),
            new ParameterModel('x-tenant', ParameterModel::IN_HEADER, false, SchemaType::scalar('string')),
        ]);

        $fields = (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);

        self::assertSame([
            ['name' => 'status', 'type' => 'string', 'in' => 'query', 'rules' => ['required', 'in:available,sold']],
            ['name' => 'limit', 'type' => 'int', 'in' => 'query', 'rules' => []],
            ['name' => 'x-tenant', 'type' => 'string', 'in' => 'header', 'rules' => []],
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

    public function testFormatAndConstraintsBecomeRules(): void
    {
        $body = SchemaType::object([
            'email' => ['schema' => SchemaType::scalar('string', 'email', false, ['minLength' => 3, 'maxLength' => 80, 'pattern' => '.+@.+']), 'required' => true],
            'age' => ['schema' => SchemaType::scalar('integer', null, false, ['minimum' => 0, 'maximum' => 120]), 'required' => false],
        ]);
        $operation = $this->operation('POST', '/users', requestBody: $body);

        $fields = (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);

        self::assertSame([
            ['name' => 'email', 'type' => 'string', 'rules' => ['required', 'email', 'min:3', 'max:80', 'regex:.+@.+']],
            ['name' => 'age', 'type' => 'int', 'rules' => ['min:0', 'max:120']],
        ], $fields);
    }

    public function testNestedObjectInputMapsToRecursiveFields(): void
    {
        $body = SchemaType::object([
            'category' => ['schema' => SchemaType::object([
                'id' => ['schema' => SchemaType::scalar('integer'), 'required' => false],
                'name' => ['schema' => SchemaType::scalar('string'), 'required' => true],
            ]), 'required' => true],
        ]);
        $operation = $this->operation('POST', '/pets', requestBody: $body);

        $fields = (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);

        self::assertSame([
            [
                'name' => 'category',
                'type' => 'object',
                'rules' => ['required'],
                'fields' => [
                    ['name' => 'id', 'type' => 'int', 'rules' => []],
                    ['name' => 'name', 'type' => 'string', 'rules' => ['required']],
                ],
            ],
        ], $fields);
    }

    public function testArrayOfObjectsInputMapsToItemFields(): void
    {
        $body = SchemaType::object([
            'tags' => ['schema' => SchemaType::arrayOf(SchemaType::object([
                'id' => ['schema' => SchemaType::scalar('integer'), 'required' => false],
            ])), 'required' => false],
        ]);
        $operation = $this->operation('POST', '/pets', requestBody: $body);

        $fields = (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);

        self::assertSame([
            [
                'name' => 'tags',
                'type' => 'array',
                'rules' => [],
                'fields' => [
                    ['name' => 'id', 'type' => 'int', 'rules' => []],
                ],
            ],
        ], $fields);
    }

    public function testArrayOfObjectsResolvesItemRef(): void
    {
        $body = SchemaType::object([
            'tags' => ['schema' => SchemaType::arrayOf(SchemaType::ref('Tag')), 'required' => false],
        ]);
        $operation = $this->operation('POST', '/pets', requestBody: $body);
        $document = new OpenApiDocument(
            title: 'X',
            version: '1.0',
            operations: [],
            namedSchemas: ['Tag' => SchemaType::object([
                'id' => ['schema' => SchemaType::scalar('integer'), 'required' => false],
            ])],
        );

        $fields = (new SchemaMapper())->inputFields($document, $operation);

        self::assertSame([
            [
                'name' => 'tags',
                'type' => 'array',
                'rules' => [],
                'fields' => [
                    ['name' => 'id', 'type' => 'int', 'rules' => []],
                ],
            ],
        ], $fields);
    }

    public function testTopLevelArrayRequestBodyBecomesItemsField(): void
    {
        // The real Petstore's POST /user/createWithList: a bare array of User.
        // Altair inputs are a named field list, so the array becomes one `items`
        // field carrying the element shape.
        $body = SchemaType::arrayOf(SchemaType::object([
            'id' => ['schema' => SchemaType::scalar('integer'), 'required' => false],
            'name' => ['schema' => SchemaType::scalar('string'), 'required' => true],
        ]));
        $operation = $this->operation('POST', '/users/createWithList', requestBody: $body);

        $fields = (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);

        self::assertSame([
            [
                'name' => 'items',
                'type' => 'array',
                'rules' => [],
                'fields' => [
                    ['name' => 'id', 'type' => 'int', 'rules' => []],
                    ['name' => 'name', 'type' => 'string', 'rules' => ['required']],
                ],
            ],
        ], $fields);
    }

    public function testTopLevelArrayOfScalarsBecomesItemsField(): void
    {
        $body = SchemaType::arrayOf(SchemaType::scalar('string'));
        $operation = $this->operation('POST', '/tags/bulk', requestBody: $body);

        $fields = (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);

        self::assertSame([
            ['name' => 'items', 'type' => 'array', 'rules' => []],
        ], $fields);
    }

    public function testScalarRequestBodyIsUnmappable(): void
    {
        $operation = $this->operation('POST', '/ping', requestBody: SchemaType::scalar('string'));

        $this->expectException(UnmappableSchemaException::class);
        $this->expectExceptionMessage('request body must be an object or array');
        (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);
    }

    public function testDeeplyNestedInlineObjectRaisesInsteadOfStackOverflow(): void
    {
        // Build an inline object nested far deeper than MAX_NESTING_DEPTH so the
        // mapper raises a clean exception rather than exhausting the call stack.
        $leaf = SchemaType::scalar('string');
        $schema = SchemaType::object(['leaf' => ['schema' => $leaf, 'required' => false]]);
        for ($i = 0; $i < 64; $i++) {
            $schema = SchemaType::object(['child' => ['schema' => $schema, 'required' => false]]);
        }

        $operation = $this->operation('POST', '/deep', requestBody: $schema);

        $this->expectException(UnmappableSchemaException::class);
        $this->expectExceptionMessage('nesting exceeds the maximum depth');
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
        // A dangling $ref nested in a property is still unmappable; the pointer
        // must locate the offending property inside the request body.
        $body = SchemaType::object([
            'address' => ['schema' => SchemaType::ref('Missing'), 'required' => true],
        ]);
        $operation = $this->operation('POST', '/users', requestBody: $body);

        try {
            (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);
            self::fail('Expected UnmappableSchemaException');
        } catch (UnmappableSchemaException $unmappableSchemaException) {
            self::assertStringContainsString('#/paths/~1users/post/requestBody/content/application~1json/schema/properties/address', $unmappableSchemaException->jsonPointer);
        }
    }

    public function testMergesAllOfBodyIntoSingleObject(): void
    {
        // allOf composition has no Altair representation, so the subschema
        // properties are merged into one flat object (composition flattened).
        $body = SchemaType::allOf([
            SchemaType::object(['a' => ['schema' => SchemaType::scalar('string'), 'required' => true]]),
            SchemaType::object(['b' => ['schema' => SchemaType::scalar('integer'), 'required' => false]]),
        ]);
        $operation = $this->operation('POST', '/things', requestBody: $body);

        $fields = (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);

        self::assertSame([
            ['name' => 'a', 'type' => 'string', 'rules' => ['required']],
            ['name' => 'b', 'type' => 'int', 'rules' => []],
        ], $fields);
    }

    public function testMergesAllOfWithRefSubschemaResolvingComponents(): void
    {
        // The classic inheritance shape: allOf [ $ref Base, inline {id} ].
        $body = SchemaType::allOf([
            SchemaType::ref('Base'),
            SchemaType::object(['id' => ['schema' => SchemaType::scalar('integer'), 'required' => true]]),
        ]);
        $operation = $this->operation('POST', '/pets', requestBody: $body);
        $document = new OpenApiDocument(
            title: 'X',
            version: '1.0',
            operations: [],
            namedSchemas: [
                'Base' => SchemaType::object(['name' => ['schema' => SchemaType::scalar('string'), 'required' => true]]),
            ],
        );

        $fields = (new SchemaMapper())->inputFields($document, $operation);

        self::assertSame([
            ['name' => 'name', 'type' => 'string', 'rules' => ['required']],
            ['name' => 'id', 'type' => 'int', 'rules' => ['required']],
        ], $fields);
    }

    public function testAllOfRequiredIsUnionedAcrossSubschemas(): void
    {
        // The same property required in any subschema is required in the merge.
        $body = SchemaType::allOf([
            SchemaType::object(['x' => ['schema' => SchemaType::scalar('string'), 'required' => false]]),
            SchemaType::object(['x' => ['schema' => SchemaType::scalar('string'), 'required' => true]]),
        ]);
        $operation = $this->operation('POST', '/things', requestBody: $body);

        $fields = (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);

        self::assertSame([
            ['name' => 'x', 'type' => 'string', 'rules' => ['required']],
        ], $fields);
    }

    public function testAllOfWithNonObjectSubschemaRaises(): void
    {
        $body = SchemaType::allOf([
            SchemaType::object(['a' => ['schema' => SchemaType::scalar('string'), 'required' => true]]),
            SchemaType::scalar('string'),
        ]);
        $operation = $this->operation('POST', '/things', requestBody: $body);

        $this->expectException(UnmappableSchemaException::class);
        $this->expectExceptionMessage('allOf');
        (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);
    }

    public function testCyclicAllOfViaRefTerminatesWithException(): void
    {
        // A self-referential allOf (Pet: allOf [$ref Pet]) must terminate via the
        // resolution-depth guard, not recurse forever / overflow the stack.
        $document = new OpenApiDocument(
            title: 'X',
            version: '1.0',
            operations: [],
            namedSchemas: ['Pet' => SchemaType::allOf([SchemaType::ref('Pet')])],
        );
        $operation = $this->operation('POST', '/pets', requestBody: SchemaType::ref('Pet'));

        $this->expectException(UnmappableSchemaException::class);
        $this->expectExceptionMessage('resolution depth');
        (new SchemaMapper())->inputFields($document, $operation);
    }

    public function testDeeplyNestedInlineAllOfRaises(): void
    {
        // A pathological inline allOf nesting bomb is bounded by the same guard.
        $schema = SchemaType::object(['a' => ['schema' => SchemaType::scalar('string'), 'required' => false]]);
        for ($i = 0; $i < 9; ++$i) {
            $schema = SchemaType::allOf([$schema]);
        }

        $operation = $this->operation('POST', '/things', requestBody: $schema);

        $this->expectException(UnmappableSchemaException::class);
        $this->expectExceptionMessage('allOf composition exceeded resolution depth');
        (new SchemaMapper())->inputFields($this->emptyDocument(), $operation);
    }

    public function testNestedAllOfPropertyInResponseMapsToLooseMap(): void
    {
        // A response object whose property is itself an allOf exercises the
        // ALLOF arm of outputType (property schemas are not pre-resolved).
        $operation = $this->operation('GET', '/things', responses: [
            new ResponseModel(status: '200', schema: SchemaType::object([
                'meta' => ['schema' => SchemaType::allOf([
                    SchemaType::object(['a' => ['schema' => SchemaType::scalar('string'), 'required' => false]]),
                ]), 'required' => false],
            ])),
        ]);

        $outputs = (new SchemaMapper())->outputs($this->emptyDocument(), $operation);

        self::assertSame([200 => ['meta' => 'array<string, mixed>']], $outputs);
    }

    /**
     * @param list<string>          $pathParameters
     * @param list<ResponseModel>   $responses
     * @param list<ParameterModel>  $parameters
     */
    private function operation(
        string $method,
        string $path,
        string $operationId = '',
        array $pathParameters = [],
        ?SchemaType $requestBody = null,
        array $responses = [],
        array $parameters = [],
    ): OperationModel {
        return new OperationModel(
            operationId: $operationId,
            method: $method,
            path: $path,
            pathParameters: $pathParameters,
            requestBody: $requestBody,
            responses: $responses,
            parameters: $parameters,
        );
    }

    private function emptyDocument(): OpenApiDocument
    {
        return new OpenApiDocument(title: 'X', version: '1.0', operations: []);
    }
}
