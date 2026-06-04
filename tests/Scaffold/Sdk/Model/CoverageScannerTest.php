<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Sdk\Model;

use Altair\Scaffold\Sdk\Model\CoverageScanner;
use PHPUnit\Framework\TestCase;

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

    public function testWarnsOnRequestBodyRefAndNonJsonBody(): void
    {
        $warnings = (new CoverageScanner())->scan([
            'paths' => [
                '/a' => ['post' => ['requestBody' => ['$ref' => '#/components/requestBodies/X'], 'responses' => []]],
                '/b' => ['post' => ['requestBody' => ['content' => ['multipart/form-data' => ['schema' => ['type' => 'object']]]], 'responses' => []]],
                '/c' => ['post' => ['requestBody' => ['content' => ['application/json' => ['schema' => ['type' => 'object']], 'application/xml' => ['schema' => ['type' => 'object']]]], 'responses' => []]],
            ],
        ]);

        self::assertSame([
            'requestBody `$ref` on POST /a is not imported (body dropped).',
            'request body on POST /b uses multipart/form-data; only application/json is imported, so the body is dropped.',
            'request body content type(s) application/xml on POST /c are not imported (only application/json is read).',
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
