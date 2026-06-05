<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Cli;

use Altair\Scaffold\Cli\OpenApiImportOptions;
use Altair\Scaffold\Cli\OpenApiImportRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class OpenApiImportRunnerTest extends TestCase
{
    private string $sandbox = '';

    protected function setUp(): void
    {
        $this->sandbox = sys_get_temp_dir() . '/altair-openapi-import-' . bin2hex(random_bytes(4));
        mkdir($this->sandbox, 0o755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->sandbox)) {
            $this->removeRecursively($this->sandbox);
        }
    }

    public function testDryRunProducesPlanWithoutWriting(): void
    {
        $documentPath = $this->writeOpenApi();

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
            dryRun: true,
        ));

        self::assertTrue($receipt->ok);
        self::assertSame(['api/users/create.yaml', 'api/users/get.yaml'], $receipt->specsWritten);
        self::assertFileDoesNotExist($this->sandbox . '/api/users/create.yaml');
    }

    public function testWritesSpecsToDiskAndCanRoundTripThroughParser(): void
    {
        $documentPath = $this->writeOpenApi();

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
        ));

        self::assertTrue($receipt->ok);
        self::assertFileExists($this->sandbox . '/api/users/create.yaml');
        self::assertFileExists($this->sandbox . '/api/users/get.yaml');

        $createSpec = Yaml::parseFile($this->sandbox . '/api/users/create.yaml');
        self::assertSame('POST', $createSpec['endpoint']['method']);
        self::assertSame('App\\User\\CreateUser', $createSpec['domain']['class']);
        self::assertArrayHasKey('email', $createSpec['input']);
    }

    public function testMapsMultipartOnlyObjectBodyAndWarnsNormalized(): void
    {
        // Phase 4a: a request body declared only as multipart/form-data (no
        // application/json) is normalized into spec inputs rather than dropped,
        // and the receipt surfaces the normalization.
        $path = $this->sandbox . '/openapi.yaml';
        file_put_contents($path, <<<'YAML'
            openapi: 3.1.0
            info: { title: Upload API, version: 1.0.0 }
            paths:
              /uploads:
                post:
                  operationId: createUpload
                  summary: Create an upload
                  requestBody:
                    required: true
                    content:
                      multipart/form-data:
                        schema:
                          type: object
                          required: [title]
                          properties:
                            title: { type: string }
                            size: { type: integer }
                  responses:
                    '201': { description: Created }
            YAML);

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $path,
            projectRoot: $this->sandbox,
        ));

        self::assertTrue($receipt->ok);
        $spec = Yaml::parseFile($this->sandbox . '/api/uploads/create.yaml');
        self::assertArrayHasKey('title', $spec['input']);
        self::assertArrayHasKey('size', $spec['input']);
        self::assertContains('required', $spec['input']['title']['rules']);
        self::assertStringContainsString(
            'normalized',
            implode("\n", $receipt->warnings),
        );
    }

    public function testBundlesExternalFileRefIntoSpecInputs(): void
    {
        // Phase 4e: a body whose schema lives in a sibling file is bundled and
        // imported instead of failing with "ref not defined".
        mkdir($this->sandbox . '/schemas', 0o755, true);
        file_put_contents($this->sandbox . '/schemas/pet.yaml', <<<'YAML'
            Pet:
              type: object
              required: [name]
              properties:
                name: { type: string }
                tag: { type: string }
            YAML);
        $path = $this->sandbox . '/openapi.yaml';
        file_put_contents($path, <<<'YAML'
            openapi: 3.1.0
            info: { title: Pets API, version: 1.0.0 }
            paths:
              /pets:
                post:
                  operationId: createPet
                  summary: Create a pet
                  requestBody:
                    required: true
                    content:
                      application/json:
                        schema: { $ref: './schemas/pet.yaml#/Pet' }
                  responses:
                    '201': { description: Created }
            YAML);

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $path,
            projectRoot: $this->sandbox,
        ));

        self::assertTrue($receipt->ok);
        $spec = Yaml::parseFile($this->sandbox . '/api/pets/create.yaml');
        self::assertSame(['name', 'tag'], array_keys($spec['input']));
        self::assertContains('required', $spec['input']['name']['rules']);
    }

    public function testRejectsRemoteExternalRefWithWarning(): void
    {
        $path = $this->sandbox . '/openapi.yaml';
        file_put_contents($path, <<<'YAML'
            openapi: 3.1.0
            info: { title: API, version: 1.0.0 }
            paths:
              /pets:
                post:
                  operationId: createPet
                  requestBody:
                    content:
                      application/json:
                        schema: { $ref: 'https://evil.example/pet.yaml#/Pet' }
                  responses:
                    '201': { description: Created }
            YAML);

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $path,
            projectRoot: $this->sandbox,
            skipUnmappable: true,
        ));

        self::assertTrue($receipt->ok);
        self::assertStringContainsString('remote', implode("\n", $receipt->warnings));
    }

    public function testFlattensAllOfBodyIntoSpecInputs(): void
    {
        // Phase 4b: a request body composed with allOf (inheritance via $ref +
        // an inline object) is flattened into a single merged input set.
        $path = $this->sandbox . '/openapi.yaml';
        file_put_contents($path, <<<'YAML'
            openapi: 3.1.0
            info: { title: Pets API, version: 1.0.0 }
            paths:
              /pets:
                post:
                  operationId: createPet
                  summary: Create a pet
                  requestBody:
                    required: true
                    content:
                      application/json:
                        schema:
                          $ref: '#/components/schemas/Pet'
                  responses:
                    '201': { description: Created }
            components:
              schemas:
                NewPet:
                  type: object
                  required: [name]
                  properties:
                    name: { type: string }
                    tag: { type: string }
                Pet:
                  allOf:
                    - $ref: '#/components/schemas/NewPet'
                    - type: object
                      required: [id]
                      properties:
                        id: { type: integer }
            YAML);

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $path,
            projectRoot: $this->sandbox,
        ));

        self::assertTrue($receipt->ok);
        $spec = Yaml::parseFile($this->sandbox . '/api/pets/create.yaml');
        self::assertSame(['name', 'tag', 'id'], array_keys($spec['input']));
        self::assertContains('required', $spec['input']['name']['rules']);
        self::assertContains('required', $spec['input']['id']['rules']);
        self::assertStringContainsString('allOf', implode("\n", $receipt->warnings));
    }

    public function testRespectsCustomOutDir(): void
    {
        $documentPath = $this->writeOpenApi();

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
            outDir: 'specs',
        ));

        self::assertTrue($receipt->ok);
        self::assertSame('specs/users/create.yaml', $receipt->specsWritten[0]);
        self::assertFileExists($this->sandbox . '/specs/users/create.yaml');
    }

    public function testSkipsExistingFilesWithoutForce(): void
    {
        $documentPath = $this->writeOpenApi();

        mkdir($this->sandbox . '/api/users', 0o755, true);
        file_put_contents($this->sandbox . '/api/users/create.yaml', 'pre-existing');

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
        ));

        self::assertSame('pre-existing', (string) file_get_contents($this->sandbox . '/api/users/create.yaml'));
        // create.yaml was skipped → only get.yaml shows as written
        self::assertSame(['api/users/get.yaml'], $receipt->specsWritten);
    }

    public function testForceOverwritesExistingFiles(): void
    {
        $documentPath = $this->writeOpenApi();

        mkdir($this->sandbox . '/api/users', 0o755, true);
        file_put_contents($this->sandbox . '/api/users/create.yaml', 'pre-existing');

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
            force: true,
        ));

        self::assertTrue($receipt->ok);
        self::assertNotSame('pre-existing', (string) file_get_contents($this->sandbox . '/api/users/create.yaml'));
    }

    public function testPersistenceCycleInjectsBlockOnCreate(): void
    {
        $documentPath = $this->writeOpenApi();

        (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
            persistence: 'cycle',
        ));

        $createSpec = Yaml::parseFile($this->sandbox . '/api/users/create.yaml');
        self::assertArrayHasKey('persistence', $createSpec);
        self::assertSame('App\\User\\User', $createSpec['persistence']['entity']['class']);

        $getSpec = Yaml::parseFile($this->sandbox . '/api/users/get.yaml');
        self::assertArrayNotHasKey('persistence', $getSpec);
    }

    public function testQueueFlagSurfacesWarningButDoesNotBlock(): void
    {
        $documentPath = $this->writeOpenApi();

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
            queue: 'redis',
        ));

        self::assertTrue($receipt->ok);
        self::assertNotEmpty($receipt->warnings);
        self::assertStringContainsString('x-altair-queue', $receipt->warnings[0]);
    }

    public function testReportsUnmappableSchemaWithPointer(): void
    {
        $documentPath = $this->sandbox . '/openapi.yaml';
        file_put_contents($documentPath, <<<'YAML'
            openapi: 3.1.0
            info: { title: Bad, version: 1.0.0 }
            paths:
              /users:
                post:
                  operationId: createUser
                  requestBody:
                    required: true
                    content:
                      application/json:
                        schema:
                          type: object
                          properties:
                            address:
                              $ref: '#/components/schemas/Missing'
                          required: [address]
                  responses:
                    '201': { description: ok }
            YAML);

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
        ));

        self::assertFalse($receipt->ok);
        self::assertNotEmpty($receipt->unmapped);
        self::assertStringContainsString('address', $receipt->unmapped[0]['pointer']);
    }

    public function testSkipUnmappableImportsMappableOperationsAndSkipsRest(): void
    {
        $documentPath = $this->writeMixedOpenApi();

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
            skipUnmappable: true,
        ));

        self::assertTrue($receipt->ok);
        self::assertSame(['api/users/create.yaml'], $receipt->specsWritten);
        self::assertFileExists($this->sandbox . '/api/users/create.yaml');
        self::assertFileDoesNotExist($this->sandbox . '/api/pets/create.yaml');

        self::assertNotEmpty($receipt->unmapped);
        self::assertStringContainsString('category', $receipt->unmapped[0]['pointer']);
        // The skipped operation is surfaced as a human-readable warning naming the method+path.
        self::assertNotEmpty($receipt->warnings);
        self::assertStringContainsString('POST /pets', implode("\n", $receipt->warnings));
    }

    public function testWithoutSkipUnmappableMixedDocumentFailsAndWritesNothing(): void
    {
        $documentPath = $this->writeMixedOpenApi();

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
        ));

        self::assertFalse($receipt->ok);
        self::assertSame([], $receipt->specsWritten);
        // Fail-fast: the mappable spec must not be written when an unmappable one aborts the run.
        self::assertFileDoesNotExist($this->sandbox . '/api/users/create.yaml');
        self::assertStringContainsString('category', $receipt->unmapped[0]['pointer']);
    }

    public function testSkipUnmappableDryRunReportsPlannedAndUnmappedWithoutWriting(): void
    {
        $documentPath = $this->writeMixedOpenApi();

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
            dryRun: true,
            skipUnmappable: true,
        ));

        self::assertTrue($receipt->ok);
        self::assertSame(['api/users/create.yaml'], $receipt->specsWritten);
        self::assertNotEmpty($receipt->unmapped);
        self::assertFileDoesNotExist($this->sandbox . '/api/users/create.yaml');
    }

    public function testNestedObjectBodyImportsToRecursiveFieldsSpec(): void
    {
        $documentPath = $this->sandbox . '/openapi.yaml';
        file_put_contents($documentPath, <<<'YAML'
            openapi: 3.1.0
            info: { title: Pets, version: 1.0.0 }
            paths:
              /pets:
                post:
                  operationId: createPet
                  summary: Create a pet
                  requestBody:
                    required: true
                    content:
                      application/json:
                        schema:
                          type: object
                          required: [name]
                          properties:
                            name: { type: string }
                            category:
                              type: object
                              properties:
                                id: { type: integer }
                                name: { type: string }
                  responses:
                    '201': { description: Created }
            YAML);

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
        ));

        self::assertTrue($receipt->ok);
        self::assertSame([], $receipt->unmapped);

        $spec = Yaml::parseFile($this->sandbox . '/api/pets/create.yaml');
        self::assertSame('object', $spec['input']['category']['type']);
        self::assertArrayHasKey('id', $spec['input']['category']['fields']);
        self::assertArrayHasKey('name', $spec['input']['category']['fields']);
    }

    public function testSkipUnmappableWarnsWhenEveryOperationIsUnmappable(): void
    {
        $documentPath = $this->sandbox . '/openapi.yaml';
        file_put_contents($documentPath, <<<'YAML'
            openapi: 3.1.0
            info: { title: AllBad, version: 1.0.0 }
            paths:
              /pets:
                post:
                  operationId: createPet
                  requestBody:
                    required: true
                    content:
                      application/json:
                        schema:
                          type: object
                          required: [category]
                          properties:
                            category: { $ref: '#/components/schemas/Missing' }
                  responses:
                    '201': { description: ok }
            YAML);

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
            skipUnmappable: true,
        ));

        self::assertTrue($receipt->ok);
        self::assertSame([], $receipt->specsWritten);
        self::assertNotEmpty($receipt->unmapped);
        self::assertStringContainsString('every operation was unmappable', implode("\n", $receipt->warnings));
    }

    public function testSurfacesDroppedConstructsAsWarnings(): void
    {
        // The import succeeds, but must warn about constructs it drops (here:
        // operation security) rather than losing them silently.
        $documentPath = $this->sandbox . '/openapi.yaml';
        file_put_contents($documentPath, <<<'YAML'
            openapi: 3.1.0
            info: { title: Pets, version: 1.0.0 }
            paths:
              /pets:
                get:
                  operationId: listPets
                  security: [{ apiKey: [] }]
                  responses: { '200': { description: ok } }
            YAML);

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
            dryRun: true,
        ));

        self::assertTrue($receipt->ok);
        self::assertStringContainsString(
            'operation `security` on GET /pets is not imported.',
            implode("\n", $receipt->warnings),
        );
    }

    public function testQueryParametersAreImportedAsInputs(): void
    {
        $documentPath = $this->sandbox . '/openapi.yaml';
        file_put_contents($documentPath, <<<'YAML'
            openapi: 3.1.0
            info: { title: Pets, version: 1.0.0 }
            paths:
              /pets:
                get:
                  operationId: listPets
                  parameters:
                    - { name: status, in: query, required: true, schema: { type: string } }
                  responses: { '200': { description: ok } }
            YAML);

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
        ));

        self::assertTrue($receipt->ok, (string) $receipt->error);
        $spec = Yaml::parseFile($this->sandbox . '/api/pets/list.yaml');
        self::assertSame('query', $spec['input']['status']['in']);
        self::assertSame('string', $spec['input']['status']['type']);
        self::assertContains('required', $spec['input']['status']['rules']);
    }

    public function testReportsMissingDocument(): void
    {
        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $this->sandbox . '/nope.yaml',
            projectRoot: $this->sandbox,
        ));

        self::assertFalse($receipt->ok);
        self::assertStringContainsString('not readable', (string) $receipt->error);
    }

    public function testReceiptIsByteStableForSameInput(): void
    {
        $documentPath = $this->writeOpenApi();
        $options = new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
            dryRun: true,
        );

        $first = (new OpenApiImportRunner())->run($options)->toJson();
        $second = (new OpenApiImportRunner())->run($options)->toJson();

        self::assertSame($first, $second);
    }

    public function testDuplicateOperationIdFails(): void
    {
        // Two operations sharing one operationId is an invalid spec — their
        // generated domain classes would collide — so it is rejected outright.
        $documentPath = $this->sandbox . '/openapi.yaml';
        file_put_contents($documentPath, <<<'YAML'
            openapi: 3.1.0
            info: { title: Dup, version: 1.0.0 }
            paths:
              /users:
                get:
                  operationId: listUsers
                  responses: { '200': { description: ok } }
                post:
                  operationId: listUsers
                  responses: { '201': { description: ok } }
            YAML);

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
        ));

        self::assertFalse($receipt->ok);
        self::assertStringContainsString('Duplicate operationId', (string) $receipt->error);
        self::assertStringContainsString('listUsers', (string) $receipt->error);
    }

    public function testCollidingShortNamesAreDisambiguated(): void
    {
        // The real Swagger Petstore case: PUT /pet (updatePet) and
        // POST /pet/{petId} (updatePetWithForm) both derive api/pet/update.yaml.
        // Distinct operationIds => disambiguate to unique files, not a collision.
        $documentPath = $this->sandbox . '/openapi.yaml';
        file_put_contents($documentPath, <<<'YAML'
            openapi: 3.1.0
            info: { title: Pets, version: 1.0.0 }
            paths:
              /pet:
                put:
                  operationId: updatePet
                  summary: Update an existing pet
                  responses: { '200': { description: ok } }
              /pet/{petId}:
                post:
                  operationId: updatePetWithForm
                  summary: Update a pet with form data
                  responses: { '200': { description: ok } }
            YAML);

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
        ));

        self::assertTrue($receipt->ok, (string) $receipt->error);
        self::assertCount(2, $receipt->specsWritten);
        self::assertCount(2, array_unique($receipt->specsWritten));
        self::assertContains('api/pet/update-pet.yaml', $receipt->specsWritten);
        self::assertContains('api/pet/update-pet-with-form.yaml', $receipt->specsWritten);
    }

    private function writeOpenApi(): string
    {
        $path = $this->sandbox . '/openapi.yaml';
        file_put_contents($path, <<<'YAML'
            openapi: 3.1.0
            info:
              title: Users API
              version: 1.0.0
            paths:
              /users:
                post:
                  operationId: createUser
                  summary: Create a new user
                  requestBody:
                    required: true
                    content:
                      application/json:
                        schema:
                          type: object
                          required: [email]
                          properties:
                            email: { type: string }
                            age: { type: integer }
                  responses:
                    '201':
                      description: Created
                      content:
                        application/json:
                          schema:
                            type: object
                            properties:
                              id: { type: string }
                              email: { type: string }
              /users/{id}:
                get:
                  operationId: getUser
                  responses:
                    '200':
                      description: OK
                      content:
                        application/json:
                          schema:
                            type: object
                            properties:
                              id: { type: string }
                              email: { type: string }
            YAML);

        return $path;
    }

    /**
     * A document mixing one fully-mappable operation (POST /users, scalar body)
     * with one that cannot be expressed as an Altair spec (POST /pets, whose
     * body carries a dangling `$ref` to an undefined schema).
     */
    private function writeMixedOpenApi(): string
    {
        $path = $this->sandbox . '/openapi.yaml';
        file_put_contents($path, <<<'YAML'
            openapi: 3.1.0
            info:
              title: Mixed API
              version: 1.0.0
            paths:
              /users:
                post:
                  operationId: createUser
                  summary: Create a new user
                  requestBody:
                    required: true
                    content:
                      application/json:
                        schema:
                          type: object
                          required: [email]
                          properties:
                            email: { type: string }
                  responses:
                    '201': { description: Created }
              /pets:
                post:
                  operationId: createPet
                  summary: Create a new pet
                  requestBody:
                    required: true
                    content:
                      application/json:
                        schema:
                          type: object
                          required: [category]
                          properties:
                            name: { type: string }
                            category:
                              $ref: '#/components/schemas/Missing'
                  responses:
                    '201': { description: Created }
            YAML);

        return $path;
    }

    private function removeRecursively(string $path): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $node) {
            if ($node->isDir()) {
                @rmdir($node->getPathname());
            } else {
                @unlink($node->getPathname());
            }
        }

        @rmdir($path);
    }
}
