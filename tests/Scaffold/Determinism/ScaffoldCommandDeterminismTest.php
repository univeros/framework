<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Scaffold\Determinism;

use Altair\Scaffold\Cli\ScaffoldCommand;
use Altair\Tests\Determinism\TwiceHarness;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * #74 acceptance: `bin/altair spec:scaffold` must produce byte-identical
 * output across runs. Drift here would mean an emitter is iterating an
 * unordered map (input field, output response, header collection) or
 * stamping a wall-clock value into the generated file. This test runs the
 * command twice into sibling temp roots and asserts every emitted file —
 * Action, Input, Responder, Domain stub, test, OpenAPI fragment, route
 * entry — is byte-equal.
 *
 * Persistence- and queue-block scaffolds get their own focused determinism
 * tests so a failure points at the offending emitter directly.
 */
#[CoversClass(ScaffoldCommand::class)]
final class ScaffoldCommandDeterminismTest extends TestCase
{
    private TwiceHarness $harness;

    private string $specPath;

    private string $specRoot;

    #[Override]
    protected function setUp(): void
    {
        $this->harness = new TwiceHarness('altair-determinism-scaffold');
        $this->specRoot = sys_get_temp_dir() . '/altair-determinism-scaffold-spec-' . bin2hex(random_bytes(5));
        mkdir($this->specRoot . '/api/users', 0o755, true);

        $this->specPath = $this->specRoot . '/api/users/create.yaml';
        file_put_contents($this->specPath, $this->sampleSpec());
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->harness->cleanup();
        $this->removeDirectory($this->specRoot);
    }

    public function testScaffoldCommandIsByteDeterministicAcrossRuns(): void
    {
        $command = new ScaffoldCommand();
        $specPath = $this->specPath;

        [$first, $second] = $this->harness->pair(function (string $out) use ($command, $specPath): void {
            ob_start();
            $command(path: $specPath, dryRun: false, force: true, root: $out);
            ob_end_clean();
        });

        $this->harness->assertTreesByteEqual($first, $second);
    }

    private function sampleSpec(): string
    {
        return <<<'YAML'
            endpoint:
              method: POST
              path: /users
              summary: Create a new user
              tags: [users]
            input:
              email:
                type: string
                rules: [email, required]
              password:
                type: string
                rules: [min:8, required]
                sensitive: true
            output:
              201:
                body:
                  user: App\User\User
              422:
                body:
                  errors: array<string, list<string>>
              409:
                body:
                  message: string
            domain:
              class: App\User\CreateUser
              invocation: __invoke
            YAML;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($path);
    }
}
