<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Tool;

use Altair\Doctor\Process\ProcessResult;
use Altair\Mcp\Support\ProjectContext;
use Altair\Mcp\Tool\Eval\EvalTool;
use Altair\Tests\Mcp\Fixtures\FakeProcessRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EvalTool::class)]
final class EvalToolTest extends TestCase
{
    private function context(): ProjectContext
    {
        return new ProjectContext('/tmp/mcp-eval', ProjectContext::detect()->altairSrcDir);
    }

    public function testBuildsCommandWithSnippetAndForwardsTimeoutFlag(): void
    {
        $payload = '{"ok":true,"result":{"type":"int","value":42},"duration_ms":5}';
        $runner = new FakeProcessRunner(new ProcessResult(0, $payload, ''));

        $result = (new EvalTool($this->context(), $runner))->call([
            'snippet' => 'return 42;',
            'timeout_ms' => 2_000,
        ]);

        self::assertTrue($result['ok']);
        self::assertSame(42, $result['result']['value']);
        self::assertSame(
            ['php', 'bin/altair', 'eval', '--format=json', '--timeout-ms=2000', '--', 'return 42;'],
            $runner->lastCommand,
        );
        self::assertSame('/tmp/mcp-eval', $runner->lastCwd);
    }

    public function testEndOfOptionsMarkerProtectsAgainstFlagInjectionFromSnippet(): void
    {
        // C2 regression: a hostile MCP input whose `snippet` starts with a
        // flag must not be promoted to a flag by the CLI. The `--` end-of-
        // options marker forces every token after it to be positional.
        $runner = new FakeProcessRunner(new ProcessResult(0, '{"ok":true}', ''));

        (new EvalTool($this->context(), $runner))->call(['snippet' => '--unsafe']);

        $command = $runner->lastCommand ?? [];
        $dashIndex = array_search('--', $command, true);
        self::assertIsInt($dashIndex);
        self::assertSame('--unsafe', $command[$dashIndex + 1] ?? null);
        // The marker means `--unsafe` is NOT in the option-parsing prefix.
        self::assertNotContains('--unsafe', \array_slice($command, 0, $dashIndex));
    }

    public function testAllowWritesAndAllowNetworkFlagsAreForwardedWhenTrue(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(0, '{"ok":true}', ''));

        (new EvalTool($this->context(), $runner))->call([
            'snippet' => 'x',
            'allow_writes' => true,
            'allow_network' => true,
        ]);

        self::assertContains('--writes', $runner->lastCommand ?? []);
        self::assertContains('--network', $runner->lastCommand ?? []);
    }

    public function testFalseGuardFlagsAreNotAppended(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(0, '{"ok":true}', ''));

        (new EvalTool($this->context(), $runner))->call([
            'snippet' => 'x',
            'allow_writes' => false,
            'allow_network' => false,
        ]);

        self::assertNotContains('--writes', $runner->lastCommand ?? []);
        self::assertNotContains('--network', $runner->lastCommand ?? []);
    }

    public function testNonJsonOutputBecomesErrorEnvelope(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(2, 'Provide a snippet or --file=<path>.', ''));

        $result = (new EvalTool($this->context(), $runner))->call(['snippet' => '']);

        self::assertFalse($result['ok']);
        self::assertSame(2, $result['exit_code']);
        self::assertArrayHasKey('error', $result);
    }
}
