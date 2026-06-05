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

    public function testActionStyleSegmentIsNotTreatedAsResource(): void
    {
        $deriver = new PathDeriver();

        // findByStatus / uploadImage / login are RPC-style actions, not resources:
        // the resource is the noun segment before them, never singularized into junk.
        self::assertSame(
            'App\\Pet\\FindPetsByStatus',
            $deriver->domainFqcn($this->operation('GET', '/pet/findByStatus', operationId: 'findPetsByStatus')),
        );
        self::assertSame(
            'App\\Pet\\UploadFile',
            $deriver->domainFqcn($this->operation('POST', '/pet/{petId}/uploadImage', operationId: 'uploadFile', pathParameters: ['petId'])),
        );
        self::assertSame(
            'App\\User\\LoginUser',
            $deriver->domainFqcn($this->operation('POST', '/user/login', operationId: 'loginUser')),
        );
    }

    public function testActionSegmentResourceDirUsesTheNoun(): void
    {
        $deriver = new PathDeriver();

        self::assertSame('pet', $deriver->resourceDir($this->operation('GET', '/pet/findByStatus', operationId: 'findPetsByStatus')));
        self::assertSame('pet', $deriver->resourceSingular($this->operation('GET', '/pet/findByStatus', operationId: 'findPetsByStatus')));
        // A genuine noun leaf (store/inventory) is still the resource.
        self::assertSame('inventory', $deriver->resourceDir($this->operation('GET', '/store/inventory', operationId: 'getInventory')));
    }

    public function testCamelCaseSegmentIsNotMangledBySingularize(): void
    {
        // Even when every segment is action-style, the trailing 's' of
        // "findByStatus" must not be stripped to "findByStatu".
        $deriver = new PathDeriver();

        self::assertSame('findByStatus', $deriver->resourceSingular($this->operation('GET', '/findByStatus', operationId: 'findByStatus')));
    }

    public function testCamelCaseNounResourceIsStillSingularized(): void
    {
        // A camelCase *noun* resource (first word `user`, not a verb) is a
        // resource, so it is singularized normally — not mistaken for an action.
        $deriver = new PathDeriver();

        self::assertSame(
            'App\\UserProfile\\ListUserProfiles',
            $deriver->domainFqcn($this->operation('GET', '/userProfiles', operationId: 'listUserProfiles')),
        );
    }

    public function testFilenameForActionPathUsesNounResource(): void
    {
        $filename = (new PathDeriver())->filename($this->operation('GET', '/pet/findByStatus', operationId: 'findPetsByStatus'));

        self::assertSame('api/pet/find.yaml', $filename);
    }

    public function testFindActionsOnSameResourceDisambiguateToDistinctFiles(): void
    {
        $deriver = new PathDeriver();
        $byStatus = $this->operation('GET', '/pet/findByStatus', operationId: 'findPetsByStatus');
        $byTags = $this->operation('GET', '/pet/findByTags', operationId: 'findPetsByTags');

        $files = $deriver->resolveFilenames([$byStatus, $byTags]);

        self::assertSame('api/pet/find-pets-by-status.yaml', $files[$deriver->operationKey($byStatus)]);
        self::assertSame('api/pet/find-pets-by-tags.yaml', $files[$deriver->operationKey($byTags)]);
    }

    public function testBareActionVerbSingleSegmentFallsBackToItself(): void
    {
        $deriver = new PathDeriver();

        self::assertSame('search', $deriver->resourceDir($this->operation('GET', '/search', operationId: 'searchItems')));
        self::assertSame('login', $deriver->resourceSingular($this->operation('POST', '/login', operationId: 'loginUser')));
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
