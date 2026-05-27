<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Sdk;

use Altair\Scaffold\Sdk\Model\OpenApiParser;
use Altair\Scaffold\Sdk\Python\PythonEmitter;
use Altair\Scaffold\Sdk\TypeScript\TypeScriptEmitter;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end toolchain checks: emit an SDK, then run the real `tsc` /
 * `mypy` against it. Skipped when the toolchain isn't installed (the
 * framework's CI doesn't ship Node/Python by default — same pattern as
 * the ext-redis / ext-mongodb storage tests).
 */
#[CoversNothing]
class CompileIntegrationTest extends TestCase
{
    private string $tmpDir;

    #[Override]
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/altair-sdk-compile-' . bin2hex(random_bytes(4));
        @mkdir($this->tmpDir, 0o775, true);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($this->tmpDir);
    }

    public function testEmittedTypeScriptCompilesUnderTsc(): void
    {
        $tsc = $this->locateBinary('tsc');
        if ($tsc === null) {
            $this->markTestSkipped('tsc is not installed.');
        }

        $file = $this->tmpDir . '/sdk.ts';
        file_put_contents($file, $this->emitTypeScript());

        exec(\sprintf('%s --strict --noEmit --skipLibCheck %s 2>&1', escapeshellarg($tsc), escapeshellarg($file)), $output, $exitCode);
        $this->assertSame(0, $exitCode, "tsc errors:\n" . implode("\n", $output));
    }

    public function testEmittedPythonPassesMypy(): void
    {
        $mypy = $this->locateBinary('mypy');
        if ($mypy === null) {
            $this->markTestSkipped('mypy is not installed.');
        }

        $file = $this->tmpDir . '/client.py';
        file_put_contents($file, $this->emitPython());

        exec(\sprintf('%s --strict --ignore-missing-imports %s 2>&1', escapeshellarg($mypy), escapeshellarg($file)), $output, $exitCode);
        $this->assertSame(0, $exitCode, "mypy errors:\n" . implode("\n", $output));
    }

    private function emitTypeScript(): string
    {
        $doc = (new OpenApiParser())->parseYaml((string) file_get_contents(__DIR__ . '/Fixtures/users-api.yaml'));

        return (new TypeScriptEmitter())->emit($doc)->single();
    }

    private function emitPython(): string
    {
        $doc = (new OpenApiParser())->parseYaml((string) file_get_contents(__DIR__ . '/Fixtures/users-api.yaml'));

        return (new PythonEmitter())->emit($doc)->single();
    }

    private function locateBinary(string $name): ?string
    {
        $which = \PHP_OS_FAMILY === 'Windows' ? 'where' : 'command -v';
        $path = trim((string) shell_exec(\sprintf('%s %s 2>/dev/null', $which, escapeshellarg($name))));

        return $path === '' ? null : $name;
    }
}
