<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Spec\Emitter;

use Altair\Scaffold\Sdk\Model\OpenApiDocument;
use Altair\Scaffold\Sdk\Model\OperationModel;
use Altair\Scaffold\Spec\Emitter\OperationMapper;
use PHPUnit\Framework\TestCase;

final class OperationMapperExtensionsTest extends TestCase
{
    public function testXAltairDomainOverridesPathDerivedFqcn(): void
    {
        $operation = new OperationModel(
            operationId: '',
            method: 'POST',
            path: '/users',
            pathParameters: [],
            requestBody: null,
            responses: [],
            extensions: [
                'x-altair-domain' => ['class' => 'Acme\\Custom\\HandleCreate', 'invocation' => 'handle'],
            ],
        );

        $spec = (new OperationMapper())->map($this->emptyDocument(), $operation);

        self::assertSame('Acme\\Custom\\HandleCreate', $spec['domain']['class']);
        self::assertSame('handle', $spec['domain']['invocation']);
    }

    public function testXAltairDomainInvocationDefaults(): void
    {
        $operation = new OperationModel(
            operationId: '',
            method: 'POST',
            path: '/users',
            pathParameters: [],
            requestBody: null,
            responses: [],
            extensions: [
                'x-altair-domain' => ['class' => 'Acme\\Custom\\X'],
            ],
        );

        $spec = (new OperationMapper())->map($this->emptyDocument(), $operation);

        self::assertSame('__invoke', $spec['domain']['invocation']);
    }

    public function testXAltairPersistenceBlockIsCopiedIntoSpec(): void
    {
        $persistence = [
            'entity' => [
                'class' => 'App\\User\\User',
                'table' => 'users',
                'fields' => ['id' => ['type' => 'uuid', 'primary' => true]],
            ],
            'repository' => 'App\\User\\UserRepository',
        ];

        $operation = new OperationModel(
            operationId: '',
            method: 'POST',
            path: '/users',
            pathParameters: [],
            requestBody: null,
            responses: [],
            extensions: ['x-altair-persistence' => $persistence],
        );

        $spec = (new OperationMapper())->map($this->emptyDocument(), $operation);

        self::assertSame($persistence, $spec['persistence']);
    }

    public function testXAltairQueueListIsRekeyedByName(): void
    {
        $queue = [
            ['name' => 'on_create', 'message' => 'App\\Msg\\Foo', 'fields' => ['id' => 'string'], 'transport' => 'default'],
            ['name' => 'on_create_email', 'message' => 'App\\Msg\\Bar', 'fields' => []],
        ];

        $operation = new OperationModel(
            operationId: '',
            method: 'POST',
            path: '/users',
            pathParameters: [],
            requestBody: null,
            responses: [],
            extensions: ['x-altair-queue' => $queue],
        );

        $spec = (new OperationMapper())->map($this->emptyDocument(), $operation);

        self::assertArrayHasKey('on_create', $spec['queue']);
        self::assertSame('App\\Msg\\Foo', $spec['queue']['on_create']['message']);
        self::assertSame('default', $spec['queue']['on_create']['transport']);
        self::assertArrayHasKey('on_create_email', $spec['queue']);
        self::assertArrayNotHasKey('name', $spec['queue']['on_create'], 'name should be a key, not a duplicated field');
    }

    public function testFallsBackToPathDeriverWhenExtensionAbsent(): void
    {
        $operation = new OperationModel(
            operationId: 'createUser',
            method: 'POST',
            path: '/users',
            pathParameters: [],
            requestBody: null,
            responses: [],
        );

        $spec = (new OperationMapper())->map($this->emptyDocument(), $operation);

        self::assertSame('App\\User\\CreateUser', $spec['domain']['class']);
    }

    private function emptyDocument(): OpenApiDocument
    {
        return new OpenApiDocument(title: 'X', version: '1.0', operations: []);
    }
}
