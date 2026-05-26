<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Cli;

use Altair\Scaffold\Cli\ScaffoldCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ScaffoldCommandIntegrationTest extends TestCase
{
    private string $tempRoot;

    private string $specPath;

    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'scaffold-cli-' . bin2hex(random_bytes(4));
        mkdir($this->tempRoot . '/api/users', 0o755, true);

        $this->specPath = $this->tempRoot . '/api/users/create.yaml';
        file_put_contents($this->specPath, $this->sampleSpec());
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempRoot)) {
            $this->removeDirectory($this->tempRoot);
        }
    }

    public function testEmitsAllSixFileTypes(): void
    {
        $command = new ScaffoldCommand();

        ob_start();
        $exit = ($command)(
            path: $this->specPath,
            dryRun: false,
            force: false,
            root: $this->tempRoot,
        );
        ob_end_clean();

        self::assertSame(0, $exit);
        self::assertFileExists($this->tempRoot . '/app/Http/Actions/CreateUserAction.php');
        self::assertFileExists($this->tempRoot . '/app/Http/Inputs/CreateUserInput.php');
        self::assertFileExists($this->tempRoot . '/app/Http/Responders/CreateUserResponder.php');
        self::assertFileExists($this->tempRoot . '/app/User/CreateUser.php');
        self::assertFileExists($this->tempRoot . '/tests/Http/Actions/CreateUserActionTest.php');
        self::assertFileExists($this->tempRoot . '/docs/openapi/create-user.yaml');
        self::assertFileExists($this->tempRoot . '/config/routes.php');
    }

    public function testDryRunDoesNotTouchDisk(): void
    {
        $command = new ScaffoldCommand();

        ob_start();
        $exit = ($command)(path: $this->specPath, dryRun: true, force: false, root: $this->tempRoot);
        $output = ob_get_clean();

        self::assertSame(0, $exit);
        self::assertStringContainsString('--- app/Http/Actions/CreateUserAction.php ---', (string) $output);
        self::assertFileDoesNotExist($this->tempRoot . '/app/Http/Actions/CreateUserAction.php');
    }

    public function testRerunSkipsExistingFilesWithoutForce(): void
    {
        $command = new ScaffoldCommand();

        ob_start();
        ($command)(path: $this->specPath, dryRun: false, force: false, root: $this->tempRoot);
        ob_end_clean();

        $actionPath = $this->tempRoot . '/app/Http/Actions/CreateUserAction.php';
        file_put_contents($actionPath, '// hand-edited');

        ob_start();
        $exit = ($command)(path: $this->specPath, dryRun: false, force: false, root: $this->tempRoot);
        $output = (string) ob_get_clean();

        self::assertSame(0, $exit);
        self::assertStringContainsString('skipped app/Http/Actions/CreateUserAction.php', $output);
        self::assertSame('// hand-edited', file_get_contents($actionPath));
    }

    public function testForceOverwritesExistingFiles(): void
    {
        $command = new ScaffoldCommand();

        ob_start();
        ($command)(path: $this->specPath, dryRun: false, force: false, root: $this->tempRoot);
        ob_end_clean();

        $actionPath = $this->tempRoot . '/app/Http/Actions/CreateUserAction.php';
        file_put_contents($actionPath, '// hand-edited');

        ob_start();
        ($command)(path: $this->specPath, dryRun: false, force: true, root: $this->tempRoot);
        ob_end_clean();

        self::assertStringContainsString('final class CreateUserAction', (string) file_get_contents($actionPath));
    }

    public function testEmittedOpenApiFragmentParsesAsValidYaml(): void
    {
        $command = new ScaffoldCommand();

        ob_start();
        ($command)(path: $this->specPath, dryRun: false, force: false, root: $this->tempRoot);
        ob_end_clean();

        $parsed = Yaml::parseFile($this->tempRoot . '/docs/openapi/create-user.yaml');

        self::assertIsArray($parsed);
        self::assertSame('3.1.0', $parsed['openapi']);
        self::assertArrayHasKey('/users', $parsed['paths']);
    }

    public function testEmittedFilesAreSyntacticallyValidPhp(): void
    {
        $command = new ScaffoldCommand();

        ob_start();
        ($command)(path: $this->specPath, dryRun: false, force: false, root: $this->tempRoot);
        ob_end_clean();

        $phpFiles = [
            '/app/Http/Actions/CreateUserAction.php',
            '/app/Http/Inputs/CreateUserInput.php',
            '/app/Http/Responders/CreateUserResponder.php',
            '/app/User/CreateUser.php',
            '/tests/Http/Actions/CreateUserActionTest.php',
            '/config/routes.php',
        ];

        foreach ($phpFiles as $file) {
            $output = [];
            $exitCode = 0;
            exec('php -l ' . escapeshellarg($this->tempRoot . $file) . ' 2>&1', $output, $exitCode);
            self::assertSame(0, $exitCode, \sprintf("Syntax check failed for %s:\n%s", $file, implode("\n", $output)));
        }
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
