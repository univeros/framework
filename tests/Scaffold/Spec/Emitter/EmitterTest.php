<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Spec\Emitter;

use Altair\Scaffold\Sdk\Model\OpenApiDocument;
use Altair\Scaffold\Sdk\Model\OpenApiParser;
use Altair\Scaffold\Sdk\Model\OperationModel;
use Altair\Scaffold\Sdk\Model\ResponseModel;
use Altair\Scaffold\Sdk\Model\SchemaType;
use Altair\Scaffold\Spec\Emitter\Emitter;
use Altair\Scaffold\Spec\Emitter\Exception\UnmappableSchemaException;
use Altair\Scaffold\Spec\Parser;
use Altair\Scaffold\Spec\Validator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class EmitterTest extends TestCase
{
    public function testEmitsOneSpecPerOperation(): void
    {
        $document = $this->petstoreDocument();

        $emitted = (new Emitter())->emit($document);

        self::assertCount(3, $emitted);
        self::assertSame('api/pets/create.yaml', $emitted[0]->relativePath);
        self::assertSame('api/pets/list.yaml', $emitted[1]->relativePath);
        self::assertSame('api/pets/get.yaml', $emitted[2]->relativePath);
    }

    public function testFilenameCollisionRaises(): void
    {
        $document = new OpenApiDocument(
            title: 'X',
            version: '1.0',
            operations: [
                new OperationModel(operationId: '', method: 'GET', path: '/users', pathParameters: [], requestBody: null, responses: []),
                new OperationModel(operationId: 'listUsers', method: 'POST', path: '/users', pathParameters: [], requestBody: null, responses: []),
            ],
        );

        $this->expectException(UnmappableSchemaException::class);
        $this->expectExceptionMessage('filename collision');
        (new Emitter())->emit($document);
    }

    public function testEmittedYamlIsParsedByExistingParser(): void
    {
        $document = $this->petstoreDocument();

        $emitted = (new Emitter())->emit($document);

        $parser = new Parser();
        $validator = new Validator();
        foreach ($emitted as $spec) {
            $parsed = $parser->parseString($spec->contents, $spec->relativePath);
            $errors = $validator->collectErrors($parsed);
            self::assertSame([], $errors, sprintf("Emitted spec '%s' should validate: ", $spec->relativePath) . implode('; ', $errors));
        }
    }

    public function testEmissionIsDeterministic(): void
    {
        $document = $this->petstoreDocument();
        $emitter = new Emitter();

        $first = $emitter->emit($document);
        $second = $emitter->emit($document);

        self::assertCount(\count($first), $second);
        foreach ($first as $index => $spec) {
            self::assertSame($spec->relativePath, $second[$index]->relativePath);
            self::assertSame($spec->contents, $second[$index]->contents);
        }
    }

    public function testUsersApiFixtureMapsCleanly(): void
    {
        $yaml = (string) file_get_contents(__DIR__ . '/../../Sdk/Fixtures/users-api.yaml');
        $document = (new OpenApiParser())->parseYaml($yaml);

        $emitted = (new Emitter())->emit($document);

        $byPath = [];
        foreach ($emitted as $spec) {
            $byPath[$spec->relativePath] = $spec->contents;
        }

        self::assertArrayHasKey('api/users/create.yaml', $byPath);
        self::assertArrayHasKey('api/users/get.yaml', $byPath);

        $created = Yaml::parse($byPath['api/users/create.yaml']);
        self::assertSame('POST', $created['endpoint']['method']);
        self::assertSame('/users', $created['endpoint']['path']);
        self::assertSame('App\\User\\CreateUser', $created['domain']['class']);
        self::assertArrayHasKey('email', $created['input']);
        self::assertSame(['required'], $created['input']['email']['rules']);
        self::assertArrayHasKey(201, $created['output']);

        $get = Yaml::parse($byPath['api/users/get.yaml']);
        self::assertSame('App\\User\\GetUser', $get['domain']['class']);
        self::assertArrayHasKey('id', $get['input']);
        self::assertSame('App\\User\\User', $get['output'][200]['body']['user']);
    }

    public function testReportsJsonPointerOnUnmappableSchema(): void
    {
        $document = new OpenApiDocument(
            title: 'X',
            version: '1.0',
            operations: [
                new OperationModel(
                    operationId: 'createUser',
                    method: 'POST',
                    path: '/users',
                    pathParameters: [],
                    requestBody: SchemaType::object([
                        'address' => ['schema' => SchemaType::object([]), 'required' => true],
                    ]),
                    responses: [],
                ),
            ],
        );

        try {
            (new Emitter())->emit($document);
            self::fail('Expected UnmappableSchemaException');
        } catch (UnmappableSchemaException $unmappableSchemaException) {
            self::assertStringContainsString('~1users/post', $unmappableSchemaException->jsonPointer);
            self::assertStringContainsString('address', $unmappableSchemaException->jsonPointer);
        }
    }

    private function petstoreDocument(): OpenApiDocument
    {
        $petSchema = SchemaType::object([
            'id' => ['schema' => SchemaType::scalar('string'), 'required' => true],
            'name' => ['schema' => SchemaType::scalar('string'), 'required' => true],
        ]);

        return new OpenApiDocument(
            title: 'Pets',
            version: '1.0',
            operations: [
                new OperationModel(
                    operationId: 'createPet',
                    method: 'POST',
                    path: '/pets',
                    pathParameters: [],
                    requestBody: SchemaType::object([
                        'name' => ['schema' => SchemaType::scalar('string'), 'required' => true],
                    ]),
                    responses: [new ResponseModel(status: '201', schema: SchemaType::ref('Pet'))],
                    summary: 'Add a new pet',
                ),
                new OperationModel(
                    operationId: 'listPets',
                    method: 'GET',
                    path: '/pets',
                    pathParameters: [],
                    requestBody: null,
                    responses: [new ResponseModel(status: '200', schema: SchemaType::object([
                        'pets' => ['schema' => SchemaType::arrayOf(SchemaType::ref('Pet')), 'required' => true],
                    ]))],
                ),
                new OperationModel(
                    operationId: 'getPet',
                    method: 'GET',
                    path: '/pets/{id}',
                    pathParameters: ['id'],
                    requestBody: null,
                    responses: [new ResponseModel(status: '200', schema: SchemaType::ref('Pet'))],
                ),
            ],
            namedSchemas: ['Pet' => $petSchema],
        );
    }
}
