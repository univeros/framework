<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Tool;

use Altair\Doctor\CheckRegistry;
use Altair\Doctor\Doctor;
use Altair\Doctor\Process\ProcessResult;
use Altair\Mcp\Guard\PathGuard;
use Altair\Mcp\Support\ProjectContext;
use Altair\Mcp\Tool\Verification\CheckDriftTool;
use Altair\Mcp\Tool\Verification\DoctorTool;
use Altair\Mcp\Tool\Verification\PhpstanTool;
use Altair\Mcp\Tool\Verification\RunTestsTool;
use Altair\Tests\Mcp\Fixtures\FakeCheck;
use Altair\Tests\Mcp\Fixtures\FakeProcessRunner;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctorTool::class)]
#[CoversClass(RunTestsTool::class)]
#[CoversClass(CheckDriftTool::class)]
#[CoversClass(PhpstanTool::class)]
final class VerificationToolsTest extends TestCase
{
    private const string SAMPLE_SPEC = <<<'YAML'
        endpoint:
          method: POST
          path: /users
          summary: Create a new user
          tags: [users]
        input:
          email:
            type: string
            rules: [email, required]
        output:
          201:
            body:
              user: App\User\User
        domain:
          class: App\User\CreateUser
          invocation: __invoke
        YAML;

    private string $tempRoot;

    private ProjectContext $context;

    #[Override]
    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . '/mcp-verify-' . bin2hex(random_bytes(4));
        mkdir($this->tempRoot . '/api/users', 0o755, true);
        file_put_contents($this->tempRoot . '/api/users/create.yaml', self::SAMPLE_SPEC);

        $this->context = new ProjectContext($this->tempRoot, ProjectContext::detect()->altairSrcDir);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeDir($this->tempRoot);
    }

    public function testDoctorReturnsStructuredReport(): void
    {
        $doctor = new Doctor(new CheckRegistry([new FakeCheck('php_version'), new FakeCheck('extensions')]));

        $result = (new DoctorTool($doctor))->call([]);

        self::assertSame('ok', $result['status']);
        self::assertCount(2, $result['checks']);
    }

    public function testDoctorHonoursOnlyFilter(): void
    {
        $doctor = new Doctor(new CheckRegistry([new FakeCheck('php_version'), new FakeCheck('extensions')]));

        $result = (new DoctorTool($doctor))->call(['only' => ['php_version']]);

        $names = array_column($result['checks'], 'name');
        self::assertSame(['php_version'], $names);
    }

    public function testRunTestsMapsProcessResult(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(0, 'OK (5 tests)', ''));

        $result = (new RunTestsTool($this->context, new PathGuard($this->tempRoot), $runner))->call(['filter' => 'FooTest']);

        self::assertTrue($result['passed']);
        self::assertSame(0, $result['exit_code']);
        self::assertContains('--filter', $runner->lastCommand ?? []);
        self::assertContains('FooTest', $runner->lastCommand ?? []);
        self::assertSame($this->tempRoot, $runner->lastCwd);
    }

    public function testRunTestsReportsFailure(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(1, 'FAILURES!', ''));

        $result = (new RunTestsTool($this->context, new PathGuard($this->tempRoot), $runner))->call([]);

        self::assertFalse($result['passed']);
        self::assertSame(1, $result['exit_code']);
    }

    public function testPhpstanParsesErrorCount(): void
    {
        $json = (string) json_encode(['totals' => ['errors' => 0, 'file_errors' => 3]]);
        $runner = new FakeProcessRunner(new ProcessResult(1, $json, ''));

        $result = (new PhpstanTool($this->context, $runner))->call(['level' => 8]);

        self::assertFalse($result['passed']);
        self::assertSame(3, $result['errors']);
        self::assertContains('--level', $runner->lastCommand ?? []);
    }

    public function testCheckDriftReportsDriftForUnscaffoldedSpec(): void
    {
        $result = (new CheckDriftTool($this->context))->call([]);

        self::assertTrue($result['has_drift']);
        self::assertGreaterThan(0, $result['count']);
    }

    public function testCheckDriftNotesMissingApiDir(): void
    {
        $empty = new ProjectContext($this->tempRoot . '/nope', $this->context->altairSrcDir);

        self::assertFalse((new CheckDriftTool($empty))->call([])['has_drift']);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.') {
                continue;
            }

            if ($item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
