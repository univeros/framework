<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Tool;

use Altair\Mcp\Exception\GuardrailException;
use Altair\Mcp\Exception\McpException;
use Altair\Mcp\Guard\PathGuard;
use Altair\Mcp\Guard\ServerMode;
use Altair\Mcp\Support\EventLog;
use Altair\Mcp\Support\OpenApiFragments;
use Altair\Mcp\Support\ProjectContext;
use Altair\Mcp\Tool\Generation\EmitOpenApiTool;
use Altair\Mcp\Tool\Generation\EmitSdkTool;
use Altair\Mcp\Tool\Generation\RewindSpecTool;
use Altair\Mcp\Tool\Generation\ScaffoldTool;
use Altair\Mcp\Tool\Generation\WriteSpecTool;
use Altair\Scaffold\Journal\Journal;
use Altair\Scaffold\Journal\Storage\FilesystemStorage;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScaffoldTool::class)]
#[CoversClass(WriteSpecTool::class)]
#[CoversClass(RewindSpecTool::class)]
#[CoversClass(EmitOpenApiTool::class)]
#[CoversClass(EmitSdkTool::class)]
#[CoversClass(EventLog::class)]
#[CoversClass(OpenApiFragments::class)]
final class GenerationToolsTest extends TestCase
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

    private Journal $journal;

    #[Override]
    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . '/mcp-gen-' . bin2hex(random_bytes(4));
        mkdir($this->tempRoot . '/api/users', 0o755, true);
        file_put_contents($this->tempRoot . '/api/users/create.yaml', self::SAMPLE_SPEC);

        $this->context = new ProjectContext($this->tempRoot, ProjectContext::detect()->altairSrcDir);
        $this->journal = new Journal(new FilesystemStorage($this->tempRoot . '/.altair/journal'), $this->tempRoot);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeDir($this->tempRoot);
    }

    private function scaffoldTool(ServerMode $mode = new ServerMode()): ScaffoldTool
    {
        return new ScaffoldTool(
            $this->context,
            new PathGuard($this->tempRoot),
            $mode,
            new EventLog(),
            journal: $this->journal,
        );
    }

    public function testScaffoldDryRunListsFilesWithoutWriting(): void
    {
        $result = $this->scaffoldTool()->call(['spec_path' => 'api/users/create.yaml', 'dry_run' => true]);

        self::assertTrue($result['dry_run']);
        self::assertNotEmpty($result['emitted']);
        self::assertFileDoesNotExist($this->tempRoot . '/app/Http/Actions/CreateUserAction.php');
    }

    public function testScaffoldWritesFiles(): void
    {
        $result = $this->scaffoldTool()->call(['spec_path' => 'api/users/create.yaml']);

        self::assertContains('app/Http/Actions/CreateUserAction.php', $result['emitted']);
        self::assertFileExists($this->tempRoot . '/app/Http/Actions/CreateUserAction.php');
    }

    public function testScaffoldInReadonlyModeIsBlocked(): void
    {
        $this->expectException(GuardrailException::class);
        $this->scaffoldTool(new ServerMode(readonly: true))->call(['spec_path' => 'api/users/create.yaml']);
    }

    private function writeSpecTool(ServerMode $mode = new ServerMode()): WriteSpecTool
    {
        return new WriteSpecTool($this->context, new PathGuard($this->tempRoot), $mode, new EventLog());
    }

    public function testWriteSpecCreatesValidatedFile(): void
    {
        $result = $this->writeSpecTool()->call(['path' => 'api/posts/create.yaml', 'content' => self::SAMPLE_SPEC]);

        self::assertSame('created', $result['action']);
        self::assertFileExists($this->tempRoot . '/api/posts/create.yaml');
    }

    public function testWriteSpecRejectsInvalidContent(): void
    {
        $this->expectException(McpException::class);
        $this->writeSpecTool()->call(['path' => 'api/bad.yaml', 'content' => "not: a valid spec\n"]);
    }

    public function testWriteSpecBlocksProtectedPath(): void
    {
        $this->expectException(GuardrailException::class);
        $this->writeSpecTool()->call(['path' => 'composer.json', 'content' => self::SAMPLE_SPEC]);
    }

    public function testWriteSpecReadonlyIsBlocked(): void
    {
        $this->expectException(GuardrailException::class);
        $this->writeSpecTool(new ServerMode(readonly: true))->call(['path' => 'api/x.yaml', 'content' => self::SAMPLE_SPEC]);
    }

    public function testRewindRemovesScaffoldedFiles(): void
    {
        $this->scaffoldTool()->call(['spec_path' => 'api/users/create.yaml']);
        self::assertFileExists($this->tempRoot . '/app/Http/Actions/CreateUserAction.php');

        $result = (new RewindSpecTool(new ServerMode(), new EventLog(), $this->journal))->call([]);

        self::assertNotEmpty($result['deleted']);
        self::assertFileDoesNotExist($this->tempRoot . '/app/Http/Actions/CreateUserAction.php');
    }

    public function testRewindWithoutJournalThrows(): void
    {
        $this->expectException(McpException::class);
        (new RewindSpecTool(new ServerMode(), new EventLog()))->call([]);
    }

    public function testRewindReadonlyIsBlocked(): void
    {
        $this->expectException(GuardrailException::class);
        (new RewindSpecTool(new ServerMode(readonly: true), new EventLog(), $this->journal))->call([]);
    }

    public function testEmitOpenApiReturnsMergedDocumentAfterScaffold(): void
    {
        $this->scaffoldTool()->call(['spec_path' => 'api/users/create.yaml']);

        $result = (new EmitOpenApiTool(new OpenApiFragments($this->context)))->call([]);

        self::assertSame('3.1.0', $result['document']['openapi']);
        self::assertArrayHasKey('/users', $result['document']['paths']);
    }

    public function testEmitOpenApiNotesMissingFragments(): void
    {
        $result = (new EmitOpenApiTool(new OpenApiFragments($this->context)))->call([]);

        self::assertNull($result['openapi']);
    }

    public function testEmitSdkReturnsTypescriptFilesAfterScaffold(): void
    {
        $this->scaffoldTool()->call(['spec_path' => 'api/users/create.yaml']);

        $result = (new EmitSdkTool(new OpenApiFragments($this->context)))->call(['language' => 'typescript']);

        self::assertSame('typescript', $result['language']);
        self::assertNotEmpty($result['files']);
    }

    public function testEmitSdkRejectsUnknownLanguage(): void
    {
        $this->expectException(McpException::class);
        (new EmitSdkTool(new OpenApiFragments($this->context)))->call(['language' => 'cobol']);
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
