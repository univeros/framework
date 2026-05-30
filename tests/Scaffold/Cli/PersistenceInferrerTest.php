<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Cli;

use Altair\Scaffold\Cli\PersistenceInferrer;
use Altair\Scaffold\Sdk\Model\OperationModel;
use PHPUnit\Framework\TestCase;

final class PersistenceInferrerTest extends TestCase
{
    public function testAppliesToCollectionPost(): void
    {
        $operation = $this->operation('POST', '/users');

        self::assertTrue((new PersistenceInferrer())->shouldApply($operation));
    }

    public function testSkipsItemEndpoint(): void
    {
        $operation = $this->operation('POST', '/users/{id}/archive', pathParameters: ['id']);

        self::assertFalse((new PersistenceInferrer())->shouldApply($operation));
    }

    public function testSkipsGet(): void
    {
        $operation = $this->operation('GET', '/users');

        self::assertFalse((new PersistenceInferrer())->shouldApply($operation));
    }

    public function testInjectsBlockWithEntityAndRepositoryFqcns(): void
    {
        $spec = [
            'endpoint' => ['method' => 'POST', 'path' => '/users', 'summary' => '', 'tags' => ['users']],
            'input' => [
                'email' => ['type' => 'string', 'rules' => ['required']],
                'age' => ['type' => 'int', 'rules' => []],
            ],
            'domain' => ['class' => 'App\\User\\CreateUser', 'invocation' => '__invoke'],
        ];
        $operation = $this->operation('POST', '/users');

        $augmented = (new PersistenceInferrer())->apply($operation, $spec);

        self::assertArrayHasKey('persistence', $augmented);
        self::assertSame('App\\User\\User', $augmented['persistence']['entity']['class']);
        self::assertSame('App\\User\\UserRepository', $augmented['persistence']['repository']);
        self::assertSame('users', $augmented['persistence']['entity']['table']);

        $fields = $augmented['persistence']['entity']['fields'];
        self::assertSame(['type' => 'uuid', 'primary' => true], $fields['id']);
        self::assertSame(['type' => 'string'], $fields['email']);
        self::assertSame(['type' => 'int'], $fields['age']);
    }

    public function testCustomAppNamespace(): void
    {
        $spec = [
            'endpoint' => ['method' => 'POST', 'path' => '/users', 'summary' => '', 'tags' => ['users']],
            'input' => [],
            'domain' => ['class' => 'Acme\\User\\CreateUser', 'invocation' => '__invoke'],
        ];
        $operation = $this->operation('POST', '/users');

        $augmented = (new PersistenceInferrer(appNamespace: 'Acme'))->apply($operation, $spec);

        self::assertSame('Acme\\User\\User', $augmented['persistence']['entity']['class']);
        self::assertSame('Acme\\User\\UserRepository', $augmented['persistence']['repository']);
    }

    public function testDropsUnsupportedInputTypes(): void
    {
        $spec = [
            'endpoint' => ['method' => 'POST', 'path' => '/users', 'summary' => '', 'tags' => ['users']],
            'input' => [
                'email' => ['type' => 'string', 'rules' => ['required']],
                'meta' => ['type' => 'enum', 'rules' => []],
            ],
            'domain' => ['class' => 'App\\User\\CreateUser', 'invocation' => '__invoke'],
        ];

        $augmented = (new PersistenceInferrer())->apply($this->operation('POST', '/users'), $spec);

        self::assertArrayHasKey('email', $augmented['persistence']['entity']['fields']);
        self::assertArrayNotHasKey('meta', $augmented['persistence']['entity']['fields']);
    }

    public function testNonApplicableOperationPassesThroughUnchanged(): void
    {
        $spec = ['endpoint' => ['method' => 'GET', 'path' => '/users', 'summary' => '', 'tags' => ['users']]];
        $operation = $this->operation('GET', '/users');

        $augmented = (new PersistenceInferrer())->apply($operation, $spec);

        self::assertSame($spec, $augmented);
    }

    /**
     * @param list<string> $pathParameters
     */
    private function operation(string $method, string $path, array $pathParameters = []): OperationModel
    {
        return new OperationModel(
            operationId: '',
            method: $method,
            path: $path,
            pathParameters: $pathParameters,
            requestBody: null,
            responses: [],
        );
    }
}
