<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Sdk;

use Altair\Scaffold\Cli\EmitSdkCommand;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmitSdkCommand::class)]
class EmitSdkCommandTest extends TestCase
{
    private string $tmpDir;

    private string $openapiPath;

    #[Override]
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/altair-sdk-cli-' . bin2hex(random_bytes(4));
        @mkdir($this->tmpDir, 0o775, true);
        $this->openapiPath = $this->tmpDir . '/openapi.yaml';
        copy(__DIR__ . '/Fixtures/users-api.yaml', $this->openapiPath);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
            is_dir($f) ? $this->rrmdir($f) : @unlink($f);
        }

        @rmdir($this->tmpDir);
    }

    public function testListShowsAvailableLanguages(): void
    {
        ob_start();
        $exit = (new EmitSdkCommand())(language: null, list: true);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('typescript', $output);
        $this->assertStringContainsString('python', $output);
    }

    public function testUnknownLanguageExitsTwo(): void
    {
        ob_start();
        $exit = (new EmitSdkCommand())(language: 'cobol', openapi: $this->openapiPath);
        ob_get_clean();
        $this->assertSame(2, $exit);
    }

    public function testEmitsTypeScriptToStdout(): void
    {
        ob_start();
        $exit = (new EmitSdkCommand())(language: 'typescript', openapi: $this->openapiPath);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('export async function createUser', $output);
    }

    public function testEmitsToFileAndCheckPasses(): void
    {
        $out = $this->tmpDir . '/sdk.ts';

        ob_start();
        $writeExit = (new EmitSdkCommand())(language: 'typescript', openapi: $this->openapiPath, out: $out);
        ob_get_clean();

        $this->assertSame(0, $writeExit);
        $this->assertFileExists($out);

        // --check against the just-written file should report no drift.
        ob_start();
        $checkExit = (new EmitSdkCommand())(language: 'typescript', openapi: $this->openapiPath, out: $out, check: true);
        $checkOutput = (string) ob_get_clean();

        $this->assertSame(0, $checkExit);
        $this->assertStringContainsString('up to date', $checkOutput);
    }

    public function testCheckDetectsDrift(): void
    {
        $out = $this->tmpDir . '/sdk.ts';
        file_put_contents($out, "// stale hand-edited content\n");

        ob_start();
        $exit = (new EmitSdkCommand())(language: 'typescript', openapi: $this->openapiPath, out: $out, check: true);
        $output = (string) ob_get_clean();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('drift detected', $output);
    }

    public function testMultiFilePythonWritesTwoFiles(): void
    {
        $out = $this->tmpDir . '/py';

        ob_start();
        (new EmitSdkCommand())(language: 'python', openapi: $this->openapiPath, out: $out, multiFile: true);
        ob_get_clean();

        $this->assertFileExists($out . '/models.py');
        $this->assertFileExists($out . '/client.py');
    }

    private function rrmdir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            is_dir($f) ? $this->rrmdir($f) : @unlink($f);
        }

        @rmdir($dir);
    }
}
