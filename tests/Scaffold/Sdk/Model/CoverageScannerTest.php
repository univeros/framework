<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Sdk\Model;

use Altair\Scaffold\Sdk\Model\CoverageScanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CoverageScanner::class)]
final class CoverageScannerTest extends TestCase
{
    public function testMinimalDocumentHasNoWarnings(): void
    {
        $warnings = (new CoverageScanner())->scan([
            'openapi' => '3.1.0',
            'paths' => [
                '/users' => [
                    'post' => [
                        'operationId' => 'createUser',
                        'requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object']]]],
                        'responses' => ['201' => ['description' => 'ok']],
                    ],
                ],
            ],
        ]);

        self::assertSame([], $warnings);
    }

    public function testParametersAreImportedSoOnlyRefParametersWarn(): void
    {
        // path/query/header/cookie parameters are now mapped (Phase 2); only an
        // unresolved parameter `$ref` is still dropped.
        $warnings = (new CoverageScanner())->scan([
            'paths' => [
                '/pets/{id}' => [
                    'get' => [
                        'parameters' => [
                            ['name' => 'id', 'in' => 'path', 'schema' => ['type' => 'string']],
                            ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'x-api', 'in' => 'header', 'schema' => ['type' => 'string']],
                            ['name' => 'sid', 'in' => 'cookie', 'schema' => ['type' => 'string']],
                            ['$ref' => '#/components/parameters/Tenant'],
                        ],
                        'responses' => ['200' => ['description' => 'ok']],
                    ],
                ],
            ],
        ]);

        self::assertSame([
            'parameter `$ref` on GET /pets/{id} is not imported.',
        ], $warnings);
    }

    public function testWarnsOnRequestBodyRefAndNormalizesNonJsonBody(): void
    {
        // Phase 4a: a non-JSON *object* body is now read (normalized), not dropped;
        // a binary/scalar-only body (octet-stream) and a schema-less body still are.
        $warnings = (new CoverageScanner())->scan([
            'paths' => [
                '/a' => ['post' => ['requestBody' => ['$ref' => '#/components/requestBodies/X'], 'responses' => []]],
                '/b' => ['post' => ['requestBody' => ['content' => ['multipart/form-data' => ['schema' => ['type' => 'object']]]], 'responses' => []]],
                '/c' => ['post' => ['requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object']], 'application/xml' => ['schema' => ['type' => 'object']]]], 'responses' => []]],
                '/d' => ['post' => ['requestBody' => ['content' => ['application/octet-stream' => ['schema' => ['type' => 'string', 'format' => 'binary']]]], 'responses' => []]],
                '/e' => ['post' => ['requestBody' => ['content' => ['text/plain' => []]], 'responses' => []]],
            ],
        ]);

        self::assertSame([
            'requestBody `$ref` on POST /a is not imported (body dropped).',
            'request body on POST /b has no application/json; its schema is read from multipart/form-data (normalized).',
            'request body content type(s) application/xml on POST /c are not imported (only application/json is read).',
            'request body on POST /d uses application/octet-stream with no mappable object schema; not imported.',
            'request body on POST /e uses text/plain with no mappable object schema; not imported.',
        ], $warnings);
    }

    public function testSchemalessJsonFallsBackToMappableNonJsonBody(): void
    {
        // A schema-less application/json stub alongside a multipart object body:
        // the parser reads the multipart schema, so the scanner reports the
        // normalization rather than claiming JSON was read.
        $warnings = (new CoverageScanner())->scan([
            'paths' => [
                '/b' => ['post' => ['requestBody' => ['content' => [
                    'application/json' => [],
                    'application/x-www-form-urlencoded' => ['schema' => ['type' => 'object']],
                ]], 'responses' => []]],
            ],
        ]);

        self::assertSame([
            'request body on POST /b has no application/json; its schema is read from application/x-www-form-urlencoded (normalized).',
        ], $warnings);
    }

    public function testWarnsWhenResponseSchemaNormalizedFromNonJson(): void
    {
        // A response carried only as application/xml is read (normalized), so
        // the importer surfaces it; a JSON response with extra representations
        // is not warned (the others are alternative views, not a loss).
        $warnings = (new CoverageScanner())->scan([
            'paths' => [
                '/x' => ['get' => ['responses' => [
                    '200' => ['content' => ['application/xml' => ['schema' => ['type' => 'object']]]],
                    '201' => ['content' => ['application/json' => ['schema' => ['type' => 'object']], 'application/xml' => ['schema' => ['type' => 'object']]]],
                    '204' => ['description' => 'no content'],
                ]]],
            ],
        ]);

        self::assertSame([
            'response 200 on GET /x has no application/json; its schema is read from application/xml (normalized).',
        ], $warnings);
    }

    public function testWarnsWhenComponentSchemaUsesAllOf(): void
    {
        // allOf is flattened to a merged object on import; the composition
        // relationship is not preserved, so it is surfaced.
        $warnings = (new CoverageScanner())->scan([
            'components' => ['schemas' => [
                'NewPet' => ['type' => 'object', 'properties' => ['name' => ['type' => 'string']]],
                'Pet' => ['allOf' => [
                    ['$ref' => '#/components/schemas/NewPet'],
                    ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']]],
                ]],
            ]],
            'paths' => [],
        ]);

        self::assertSame([
            '`components.schemas.Pet` uses allOf; its subschemas are merged into one object on import (composition not preserved).',
        ], $warnings);
    }

    public function testWarnsOnInlineAllOfRequestBody(): void
    {
        // An inline allOf body (not via $ref) is flattened too, so it is surfaced
        // at the operation rather than relying on a named-component warning.
        $warnings = (new CoverageScanner())->scan([
            'paths' => [
                '/things' => ['post' => ['requestBody' => ['content' => ['application/json' => ['schema' => [
                    'allOf' => [
                        ['type' => 'object', 'properties' => ['a' => ['type' => 'string']]],
                        ['type' => 'object', 'properties' => ['b' => ['type' => 'integer']]],
                    ],
                ]]]], 'responses' => []]],
            ],
        ]);

        self::assertSame([
            'request body on POST /things uses allOf; its subschemas are merged into one object on import (composition not preserved).',
        ], $warnings);
    }

    public function testWarnsOnDocumentLevelAndOperationLevelConstructs(): void
    {
        $warnings = (new CoverageScanner())->scan([
            'servers' => [['url' => 'https://api.example.com']],
            'security' => [['apiKey' => []]],
            'webhooks' => ['newPet' => ['post' => []]],
            'components' => ['securitySchemes' => ['apiKey' => ['type' => 'apiKey']]],
            'paths' => [
                '/things' => [
                    'get' => [
                        'security' => [['apiKey' => []]],
                        'callbacks' => ['onEvent' => []],
                        'responses' => ['200' => ['description' => 'ok']],
                    ],
                ],
            ],
        ]);

        self::assertSame([
            'document `servers` are not imported.',
            'global `security` requirements are not imported.',
            '`webhooks` are not imported (only `paths` are read).',
            '`components.securitySchemes` are not imported.',
            'operation `security` on GET /things is not imported.',
            '`callbacks` on GET /things are not imported.',
        ], $warnings);
    }
}
