<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Spec\Emitter;

use Altair\Scaffold\Sdk\Model\OperationModel;
use Altair\Scaffold\Spec\Emitter\PathDeriver;
use PHPUnit\Framework\TestCase;

final class PathDeriverTest extends TestCase
{
    public function testCollectionPostBecomesCreateFile(): void
    {
        $filename = (new PathDeriver())->filename($this->operation('POST', '/users'));

        self::assertSame('api/users/create.yaml', $filename);
    }

    public function testCollectionGetBecomesListFile(): void
    {
        $filename = (new PathDeriver())->filename($this->operation('GET', '/users'));

        self::assertSame('api/users/list.yaml', $filename);
    }

    public function testItemGetBecomesGetFile(): void
    {
        $filename = (new PathDeriver())->filename($this->operation('GET', '/users/{id}', pathParameters: ['id']));

        self::assertSame('api/users/get.yaml', $filename);
    }

    public function testItemPutBecomesUpdateFile(): void
    {
        $filename = (new PathDeriver())->filename($this->operation('PUT', '/users/{id}', pathParameters: ['id']));

        self::assertSame('api/users/update.yaml', $filename);
    }

    public function testItemPatchBecomesUpdateFile(): void
    {
        $filename = (new PathDeriver())->filename($this->operation('PATCH', '/users/{id}', pathParameters: ['id']));

        self::assertSame('api/users/update.yaml', $filename);
    }

    public function testItemDeleteBecomesDeleteFile(): void
    {
        $filename = (new PathDeriver())->filename($this->operation('DELETE', '/users/{id}', pathParameters: ['id']));

        self::assertSame('api/users/delete.yaml', $filename);
    }

    public function testOperationIdFirstWordOverridesVerb(): void
    {
        $filename = (new PathDeriver())->filename($this->operation('POST', '/posts/{id}', operationId: 'archivePost', pathParameters: ['id']));

        self::assertSame('api/posts/archive.yaml', $filename);
    }

    public function testRootPathFallsBackToEndpointDirectory(): void
    {
        $filename = (new PathDeriver())->filename($this->operation('GET', '/'));

        self::assertSame('api/endpoint/list.yaml', $filename);
    }

    public function testSynthesizedOperationIdProducesCleanFilename(): void
    {
        // Mirrors what OpenApiParser::synthesizeOperationId emits.
        $filename = (new PathDeriver())->filename($this->operation('GET', '/users/{id}', operationId: 'getUsersById', pathParameters: ['id']));

        self::assertSame('api/users/get.yaml', $filename);
    }

    public function testDomainFqcnUsesSingularResourceNamespace(): void
    {
        $fqcn = (new PathDeriver())->domainFqcn($this->operation('POST', '/users', operationId: 'createUser'));

        self::assertSame('App\\User\\CreateUser', $fqcn);
    }

    public function testDomainFqcnPascalCasesOperationId(): void
    {
        $fqcn = (new PathDeriver())->domainFqcn($this->operation('POST', '/posts/{id}', operationId: 'archivePost', pathParameters: ['id']));

        self::assertSame('App\\Post\\ArchivePost', $fqcn);
    }

    public function testDomainFqcnSynthesizesWhenOperationIdMissing(): void
    {
        $fqcn = (new PathDeriver())->domainFqcn($this->operation('GET', '/users'));

        self::assertSame('App\\User\\ListUsers', $fqcn);
    }

    public function testDomainFqcnUsesCustomAppNamespace(): void
    {
        $fqcn = (new PathDeriver(appNamespace: 'Acme'))->domainFqcn($this->operation('POST', '/users', operationId: 'createUser'));

        self::assertSame('Acme\\User\\CreateUser', $fqcn);
    }

    public function testCustomSpecRootDirectory(): void
    {
        $filename = (new PathDeriver(specRoot: 'specs'))->filename($this->operation('POST', '/users'));

        self::assertSame('specs/users/create.yaml', $filename);
    }

    /**
     * @param list<string> $pathParameters
     */
    private function operation(
        string $method,
        string $path,
        string $operationId = '',
        array $pathParameters = [],
    ): OperationModel {
        return new OperationModel(
            operationId: $operationId,
            method: $method,
            path: $path,
            pathParameters: $pathParameters,
            requestBody: null,
            responses: [],
        );
    }
}
