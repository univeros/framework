<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Cli;

use Altair\Scaffold\Cli\OpenApiImportOptions;
use Altair\Scaffold\Cli\OpenApiImportRunner;
use PHPUnit\Framework\TestCase;

/**
 * --scaffold chaining is exercised separately so the test list reads as a
 * pipeline rather than one big blob in {@see OpenApiImportRunnerTest}.
 */
final class OpenApiImportScaffoldTest extends TestCase
{
    private string $sandbox = '';

    protected function setUp(): void
    {
        $this->sandbox = sys_get_temp_dir() . '/altair-openapi-import-scaffold-' . bin2hex(random_bytes(4));
        mkdir($this->sandbox, 0o755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->sandbox)) {
            $this->removeRecursively($this->sandbox);
        }
    }

    public function testScaffoldEmitsActionInputResponderForEachImportedSpec(): void
    {
        $documentPath = $this->writeOpenApi();

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
            scaffold: true,
        ));

        self::assertTrue($receipt->ok, 'receipt error: ' . $receipt->error);
        self::assertNotEmpty($receipt->scaffolded);

        // At minimum: action + input + responder per spec (POST + GET → 6 files).
        $actions = array_filter($receipt->scaffolded, static fn(string $p): bool => str_contains($p, 'Actions/'));
        self::assertGreaterThanOrEqual(2, \count($actions));

        // Both specs got written first.
        self::assertContains('api/users/create.yaml', $receipt->specsWritten);
        self::assertContains('api/users/get.yaml', $receipt->specsWritten);
    }

    public function testScaffoldFailureRollsBackImportedSpecs(): void
    {
        $documentPath = $this->sandbox . '/openapi.yaml';
        // Path parameter with empty operationId → spec passes import emitter
        // but validator rejects because of the bad path. Force scaffold to fail.
        // We get this by emitting a spec with an invalid domain class.
        // Simpler: write a doc whose emitted YAML the spec validator rejects.
        // The PathDeriver synthesizes `App\Endpoint\GetEndpoint` for `GET /`,
        // a well-formed FQCN — too forgiving. We instead pre-create a directory
        // where a spec file is expected so the FileWriter fails on the second
        // spec, simulating a partial scaffold failure that triggers rollback.
        file_put_contents($documentPath, <<<'YAML'
            openapi: 3.1.0
            info: { title: Bad, version: 1.0.0 }
            paths:
              /users:
                post:
                  operationId: createUser
                  responses: { '201': { description: ok } }
            YAML);

        // Pre-create `app/Http/Actions/CreateUserAction.php` as a DIRECTORY so
        // FileWriter's file_put_contents fails when scaffold tries to write it.
        mkdir($this->sandbox . '/app/Http/Actions/CreateUserAction.php', 0o755, true);

        // The forced failure inside FileWriter emits an E_WARNING that
        // phpunit.xml.dist's failOnWarning would otherwise convert into a
        // failed test — silence it for the duration of the call, then restore.
        set_error_handler(static fn(): bool => true);
        try {
            $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
                documentPath: $documentPath,
                projectRoot: $this->sandbox,
                scaffold: true,
            ));
        } finally {
            restore_error_handler();
        }

        self::assertFalse($receipt->ok);
        self::assertStringContainsString('Scaffold phase failed', (string) $receipt->error);
        self::assertContains('api/users/create.yaml', $receipt->rolledBack);
        self::assertFileDoesNotExist($this->sandbox . '/api/users/create.yaml');
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
