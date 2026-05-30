<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Sdk;

use Altair\Scaffold\Sdk\Model\OpenApiParser;
use PHPUnit\Framework\TestCase;

final class OpenApiParserExtensionsTest extends TestCase
{
    public function testExtractsXAltairKeysOnOperation(): void
    {
        $document = (new OpenApiParser())->parseYaml(<<<'YAML'
            openapi: 3.1.0
            info: { title: X, version: 1.0 }
            paths:
              /users:
                post:
                  operationId: createUser
                  x-altair-domain:
                    class: App\User\CreateUser
                    invocation: __invoke
                  x-altair-persistence:
                    entity:
                      class: App\User\User
                      table: users
                      fields:
                        id: { type: uuid, primary: true }
                  x-other: ignored
                  responses:
                    '201': { description: ok }
            YAML);

        $operation = $document->operations[0];
        self::assertArrayHasKey('x-altair-domain', $operation->extensions);
        self::assertSame('App\\User\\CreateUser', $operation->extensions['x-altair-domain']['class']);
        self::assertArrayHasKey('x-altair-persistence', $operation->extensions);
        self::assertArrayNotHasKey('x-other', $operation->extensions);
    }

    public function testCarriesUnknownAltairExtensions(): void
    {
        $document = (new OpenApiParser())->parseYaml(<<<'YAML'
            openapi: 3.1.0
            info: { title: X, version: 1.0 }
            paths:
              /pings:
                get:
                  operationId: ping
                  x-altair-future: { foo: bar }
                  responses:
                    '200': { description: ok }
            YAML);

        self::assertArrayHasKey('x-altair-future', $document->operations[0]->extensions);
        self::assertSame(['foo' => 'bar'], $document->operations[0]->extensions['x-altair-future']);
    }
}
