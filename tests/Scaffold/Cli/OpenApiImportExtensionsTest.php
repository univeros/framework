<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Cli;

use Altair\Scaffold\Cli\OpenApiImportOptions;
use Altair\Scaffold\Cli\OpenApiImportRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * End-to-end coverage of the x-altair-* import path.
 *
 * Round-trip: build an Altair-shaped OpenAPI doc (matching what
 * `spec:emit-openapi` produces) and assert that the imported YAML
 * recovers the original domain / persistence / queue blocks rather than
 * the path-derived defaults.
 */
final class OpenApiImportExtensionsTest extends TestCase
{
    private string $sandbox = '';

    protected function setUp(): void
    {
        $this->sandbox = sys_get_temp_dir() . '/altair-extensions-' . bin2hex(random_bytes(4));
        mkdir($this->sandbox, 0o755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->sandbox)) {
            $this->removeRecursively($this->sandbox);
        }
    }

    public function testXAltairDomainSurvivesImport(): void
    {
        $documentPath = $this->writeDocWithExtensions();

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
        ));

        self::assertTrue($receipt->ok);
        $spec = Yaml::parseFile($this->sandbox . '/api/users/create.yaml');
        self::assertSame('Acme\\User\\HandleCreate', $spec['domain']['class']);
        self::assertSame('handle', $spec['domain']['invocation']);
    }

    public function testXAltairPersistenceSurvivesImport(): void
    {
        $documentPath = $this->writeDocWithExtensions();

        (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
        ));

        $spec = Yaml::parseFile($this->sandbox . '/api/users/create.yaml');
        self::assertArrayHasKey('persistence', $spec);
        self::assertSame('Acme\\User\\User', $spec['persistence']['entity']['class']);
        self::assertSame('user_records', $spec['persistence']['entity']['table']);
        self::assertSame('uuid', $spec['persistence']['entity']['fields']['id']['type']);
    }

    public function testXAltairQueueSurvivesImportAsKeyedMap(): void
    {
        $documentPath = $this->writeDocWithExtensions();

        (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
        ));

        $spec = Yaml::parseFile($this->sandbox . '/api/users/create.yaml');
        self::assertArrayHasKey('queue', $spec);
        self::assertArrayHasKey('on_create', $spec['queue']);
        self::assertSame('Acme\\Msg\\Welcome', $spec['queue']['on_create']['message']);
    }

    public function testUnknownExtensionSurfacesWarning(): void
    {
        $documentPath = $this->sandbox . '/openapi.yaml';
        file_put_contents($documentPath, <<<'YAML'
            openapi: 3.1.0
            info: { title: X, version: 1.0 }
            paths:
              /things:
                get:
                  operationId: listThings
                  x-altair-future-feature: { hint: yes }
                  responses:
                    '200':
                      description: OK
                      content:
                        application/json:
                          schema:
                            type: object
                            properties:
                              count: { type: integer }
            YAML);

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
        ));

        self::assertTrue($receipt->ok);
        self::assertNotEmpty($receipt->warnings);
        $matched = array_filter(
            $receipt->warnings,
            static fn(string $w): bool => str_contains($w, 'x-altair-future-feature'),
        );
        self::assertNotEmpty($matched, 'expected a warning for x-altair-future-feature, got: ' . implode('; ', $receipt->warnings));
    }

    public function testKnownExtensionsDoNotEmitUnknownWarning(): void
    {
        $documentPath = $this->writeDocWithExtensions();

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
        ));

        $unknownWarnings = array_filter(
            $receipt->warnings,
            static fn(string $w): bool => str_contains($w, 'unknown extension'),
        );

        self::assertSame([], $unknownWarnings, 'Known extensions should not trigger unknown warnings; got: ' . implode('; ', $receipt->warnings));
    }

    private function writeDocWithExtensions(): string
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
                  summary: Create a user with non-standard hand-edits preserved
                  x-altair-domain:
                    class: Acme\User\HandleCreate
                    invocation: handle
                  x-altair-persistence:
                    entity:
                      class: Acme\User\User
                      table: user_records
                      fields:
                        id: { type: uuid, primary: true }
                        email: { type: string, unique: true }
                    repository: Acme\User\UserRepository
                  x-altair-queue:
                    - name: on_create
                      message: Acme\Msg\Welcome
                      fields: { id: string, email: string }
                      transport: redis
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
                    '201':
                      description: Created
                      content:
                        application/json:
                          schema:
                            type: object
                            properties:
                              id: { type: string }
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
