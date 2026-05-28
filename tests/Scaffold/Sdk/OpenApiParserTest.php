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
}
