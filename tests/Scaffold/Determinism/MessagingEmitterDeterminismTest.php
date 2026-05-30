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
use Altair\Scaffold\Emitter\EmissionPlan;
use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Tests\Determinism\TwiceHarness;
use Altair\Tests\Scaffold\Support\SpecFixture;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * #74 acceptance for the messaging emitters wired into `spec:scaffold` —
 * the DTO message class, the handler stub, and the handler PHPUnit test
 * must be byte-equal across runs of the same spec.
 *
 * Asserts at both the CLI surface (`spec:scaffold api/x.yaml`) and the
 * in-process emitter surface (`EmissionPlan::build`), so a future
 * regression in either entry point is caught directly.
 */
#[CoversClass(ScaffoldCommand::class)]
#[CoversClass(EmissionPlan::class)]
final class MessagingEmitterDeterminismTest extends TestCase
{
    private TwiceHarness $harness;

    private string $specPath;

    private string $specRoot;

    #[Override]
    protected function setUp(): void
    {
        $this->harness = new TwiceHarness('altair-determinism-messaging');
        $this->specRoot = sys_get_temp_dir() . '/altair-determinism-messaging-spec-' . bin2hex(random_bytes(5));
        mkdir($this->specRoot . '/api/users', 0o755, true);

        $this->specPath = $this->specRoot . '/api/users/create.yaml';
        file_put_contents($this->specPath, $this->sampleSpecWithQueue());
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->harness->cleanup();
        $this->removeDirectory($this->specRoot);
    }

    public function testScaffoldEmitsByteIdenticalMessagingFilesAcrossRuns(): void
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

    public function testEmissionPlanProducesByteIdenticalMessagingFilesAcrossInvocations(): void
    {
        $spec = SpecFixture::createUserWithQueue();
        $plan = new EmissionPlan();

        $firstRun = $plan->build($spec);
        $secondRun = $plan->build($spec);

        self::assertSameSize($firstRun, $secondRun);

        $messagingKinds = [
            EmittedFileKind::Message,
            EmittedFileKind::Handler,
            EmittedFileKind::HandlerTest,
        ];
        $messagingFilesChecked = 0;

        foreach ($firstRun as $index => $file) {
            $other = $secondRun[$index];
            self::assertSame($file->relativePath, $other->relativePath, 'emitter path drift');
            self::assertSame($file->contents, $other->contents, 'emitter content drift for ' . $file->relativePath);

            if (\in_array($file->kind, $messagingKinds, true)) {
                $messagingFilesChecked++;
            }
        }

        self::assertGreaterThanOrEqual(3, $messagingFilesChecked, 'expected message DTO + handler + handler test');
    }

    private function sampleSpecWithQueue(): string
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
            queue:
              on_create:
                message: App\Messages\SendWelcomeEmail
                transport: default
                fields:
                  userId: string
                  email:  string
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
