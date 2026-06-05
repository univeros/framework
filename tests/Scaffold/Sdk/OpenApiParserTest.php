<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Sdk;

use Altair\Scaffold\Sdk\Model\OpenApiDocument;
use Altair\Scaffold\Sdk\Model\OperationModel;
use Altair\Scaffold\Sdk\Model\ResponseModel;
use Altair\Scaffold\Sdk\Exception\SdkException;
use Altair\Scaffold\Sdk\Model\OpenApiParser;
use Altair\Scaffold\Sdk\Model\SchemaType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpenApiParser::class)]
#[CoversClass(SchemaType::class)]
#[CoversClass(OpenApiDocument::class)]
#[CoversClass(OperationModel::class)]
#[CoversClass(ResponseModel::class)]
class OpenApiParserTest extends TestCase
{
    private function fixture(): string
    {
        return (string) file_get_contents(__DIR__ . '/Fixtures/users-api.yaml');
    }

    public function testParsesTitleVersionAndOperations(): void
    {
        $doc = (new OpenApiParser())->parseYaml($this->fixture());

        $this->assertSame('Users API', $doc->title);
        $this->assertSame('1.0.0', $doc->version);
        $this->assertCount(2, $doc->operations);
    }

    public function testParsesNamedSchemasIncludingEnum(): void
    {
        $doc = (new OpenApiParser())->parseYaml($this->fixture());

        $this->assertArrayHasKey('UserRole', $doc->namedSchemas);
        $this->assertArrayHasKey('User', $doc->namedSchemas);
        $this->assertSame(SchemaType::ENUM, $doc->namedSchemas['UserRole']->kind);
        $this->assertSame(['admin', 'member', 'viewer'], $doc->namedSchemas['UserRole']->enumValues);
        $this->assertTrue($doc->namedSchemas['User']->isObject());
    }

    public function testParsesRequestBodyAndPathParameters(): void
    {
        $doc = (new OpenApiParser())->parseYaml($this->fixture());

        $byId = [];
        foreach ($doc->operations as $op) {
            $byId[$op->operationId] = $op;
        }

        $this->assertTrue($byId['createUser']->hasRequestBody());
        $this->assertSame('POST', $byId['createUser']->method);
        $this->assertSame([], $byId['createUser']->pathParameters);

        $this->assertFalse($byId['getUser']->hasRequestBody());
        $this->assertSame(['id'], $byId['getUser']->pathParameters);
    }

    public function testParsesResponseUnion(): void
    {
        $doc = (new OpenApiParser())->parseYaml($this->fixture());
        $create = $doc->operations[0];

        $statuses = array_map(static fn(ResponseModel $r): string => $r->status, $create->responses);
        $this->assertContains('201', $statuses);
        $this->assertContains('422', $statuses);
        $this->assertCount(1, $create->successResponses());
    }

    public function testSynthesisesOperationIdWhenAbsent(): void
    {
        $doc = (new OpenApiParser())->parse([
            'info' => ['title' => 'X', 'version' => '1'],
            'paths' => [
                '/orders/{orderId}' => [
                    'delete' => ['responses' => ['204' => ['description' => 'gone']]],
                ],
            ],
        ]);

        $this->assertSame('deleteOrdersByOrderId', $doc->operations[0]->operationId);
    }

    public function testRejectsNonMapTopLevel(): void
    {
        $this->expectException(SdkException::class);
        // A bare scalar parses to a string, not a map → rejected.
        (new OpenApiParser())->parseYaml('just-a-scalar-string');
    }

    public function testMapsNonJsonObjectRequestBodyWhenJsonAbsent(): void
    {
        // multipart/form-data object body with no application/json: Phase 4a
        // reads its schema instead of dropping the whole body.
        $doc = (new OpenApiParser())->parse([
            'paths' => [
                '/upload' => [
                    'post' => [
                        'operationId' => 'upload',
                        'requestBody' => ['content' => ['multipart/form-data' => ['schema' => [
                            'type' => 'object',
                            'properties' => ['title' => ['type' => 'string']],
                            'required' => ['title'],
                        ]]]],
                        'responses' => ['200' => ['description' => 'ok']],
                    ],
                ],
            ],
        ]);

        $operation = $doc->operations[0];
        $this->assertTrue($operation->hasRequestBody());
        $this->assertNotNull($operation->requestBody);
        $this->assertTrue($operation->requestBody->isObject());
        $this->assertArrayHasKey('title', $operation->requestBody->properties);
    }

    public function testPrefersJsonRequestBodyOverOtherContentTypes(): void
    {
        // application/json wins even when listed after another content type.
        $doc = (new OpenApiParser())->parse([
            'paths' => [
                '/things' => [
                    'post' => [
                        'operationId' => 'createThing',
                        'requestBody' => ['content' => [
                            'application/xml' => ['schema' => ['type' => 'object', 'properties' => ['fromXml' => ['type' => 'string']]]],
                            'application/json' => ['schema' => ['type' => 'object', 'properties' => ['fromJson' => ['type' => 'string']]]],
                        ]],
                        'responses' => ['200' => ['description' => 'ok']],
                    ],
                ],
            ],
        ]);

        $body = $doc->operations[0]->requestBody;
        $this->assertNotNull($body);
        $this->assertArrayHasKey('fromJson', $body->properties);
        $this->assertArrayNotHasKey('fromXml', $body->properties);
    }

    public function testSchemalessJsonFallsBackToMappableBody(): void
    {
        // application/json present but with no schema (a stub): the parser falls
        // back to the x-www-form-urlencoded object body instead of returning null.
        $doc = (new OpenApiParser())->parse([
            'paths' => [
                '/things' => [
                    'post' => [
                        'operationId' => 'createThing',
                        'requestBody' => ['content' => [
                            'application/json' => [],
                            'application/x-www-form-urlencoded' => ['schema' => ['type' => 'object', 'properties' => ['fromForm' => ['type' => 'string']]]],
                        ]],
                        'responses' => ['200' => ['description' => 'ok']],
                    ],
                ],
            ],
        ]);

        $body = $doc->operations[0]->requestBody;
        $this->assertNotNull($body);
        $this->assertArrayHasKey('fromForm', $body->properties);
    }

    public function testBinaryOctetStreamBodyStaysUnmapped(): void
    {
        // A scalar/binary octet-stream body has no named-field representation,
        // so it must NOT be picked up (it stays surfaced by the CoverageScanner).
        $doc = (new OpenApiParser())->parse([
            'paths' => [
                '/pet/{petId}/uploadImage' => [
                    'post' => [
                        'operationId' => 'uploadFile',
                        'requestBody' => ['content' => ['application/octet-stream' => ['schema' => ['type' => 'string', 'format' => 'binary']]]],
                        'responses' => ['200' => ['description' => 'ok']],
                    ],
                ],
            ],
        ]);

        $this->assertFalse($doc->operations[0]->hasRequestBody());
    }

    public function testMapsNonJsonResponseSchemaWhenJsonAbsent(): void
    {
        // A response carried only as application/xml is read rather than dropped.
        $doc = (new OpenApiParser())->parse([
            'paths' => [
                '/report' => [
                    'get' => [
                        'operationId' => 'getReport',
                        'responses' => ['200' => ['description' => 'ok', 'content' => ['application/xml' => ['schema' => [
                            'type' => 'object',
                            'properties' => ['id' => ['type' => 'integer']],
                        ]]]]],
                    ],
                ],
            ],
        ]);

        $response = $doc->operations[0]->responses[0];
        $this->assertInstanceOf(SchemaType::class, $response->schema);
        $this->assertTrue($response->schema->isObject());
    }

    public function testPrefersJsonResponseOverOtherContentTypes(): void
    {
        $doc = (new OpenApiParser())->parse([
            'paths' => [
                '/report' => [
                    'get' => [
                        'operationId' => 'getReport',
                        'responses' => ['200' => ['description' => 'ok', 'content' => [
                            'application/xml' => ['schema' => ['type' => 'object', 'properties' => ['fromXml' => ['type' => 'string']]]],
                            'application/json' => ['schema' => ['type' => 'object', 'properties' => ['fromJson' => ['type' => 'string']]]],
                        ]]],
                    ],
                ],
            ],
        ]);

        $schema = $doc->operations[0]->responses[0]->schema;
        $this->assertInstanceOf(SchemaType::class, $schema);
        $this->assertArrayHasKey('fromJson', $schema->properties);
        $this->assertArrayNotHasKey('fromXml', $schema->properties);
    }
}
