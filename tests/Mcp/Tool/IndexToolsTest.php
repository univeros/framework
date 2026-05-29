<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Tool;

use Altair\Doctor\Process\ProcessResult;
use Altair\Mcp\Support\ProjectContext;
use Altair\Mcp\Tool\Index\CallersTool;
use Altair\Mcp\Tool\Index\DeadCodeTool;
use Altair\Mcp\Tool\Index\FindUsagesTool;
use Altair\Mcp\Tool\Index\ImpactTool;
use Altair\Mcp\Tool\Index\ImplementersTool;
use Altair\Mcp\Tool\Index\IndexTool;
use Altair\Tests\Mcp\Fixtures\FakeProcessRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IndexTool::class)]
#[CoversClass(FindUsagesTool::class)]
#[CoversClass(ImplementersTool::class)]
#[CoversClass(CallersTool::class)]
#[CoversClass(DeadCodeTool::class)]
#[CoversClass(ImpactTool::class)]
final class IndexToolsTest extends TestCase
{
    private function context(): ProjectContext
    {
        return new ProjectContext('/tmp/mcp-index', ProjectContext::detect()->altairSrcDir);
    }

    public function testFindUsagesBuildsCommandAndReturnsDecodedPayload(): void
    {
        $json = '{"symbol":"App\\\\User","count":1,"usages":[{"file":"a.php","line":3,"usage_kind":"new","context":null}]}';
        $runner = new FakeProcessRunner(new ProcessResult(0, $json, ''));

        $result = (new FindUsagesTool($this->context(), $runner))->call(['symbol' => 'App\User']);

        self::assertTrue($result['ok']);
        self::assertSame(1, $result['count']);
        self::assertSame(['php', 'bin/altair', 'index:find-usages', 'App\User', '--format=json'], $runner->lastCommand);
        self::assertSame('/tmp/mcp-index', $runner->lastCwd);
    }

    public function testFindUsagesKindFilterNarrowsResults(): void
    {
        $json = '{"symbol":"App\\\\User","count":2,"usages":['
            . '{"file":"a.php","line":3,"usage_kind":"new","context":null},'
            . '{"file":"b.php","line":9,"usage_kind":"type_hint","context":null}]}';
        $runner = new FakeProcessRunner(new ProcessResult(0, $json, ''));

        $result = (new FindUsagesTool($this->context(), $runner))->call(['symbol' => 'App\User', 'kind' => 'new']);

        self::assertSame(1, $result['count']);
        self::assertCount(1, $result['usages']);
        self::assertSame('new', $result['usages'][0]['usage_kind']);
    }

    public function testImpactJoinsSymbolsArrayIntoOneArgument(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(0, '{"symbols":[],"impact":{"files":0,"tests":0,"specs":0}}', ''));

        (new ImpactTool($this->context(), $runner))->call(['symbols' => ['App\A', 'App\B']]);

        self::assertSame(
            ['php', 'bin/altair', 'index:impact', 'App\A,App\B', '--format=json'],
            $runner->lastCommand,
        );
    }

    public function testImplementersAndCallersAndDeadCodeBuildExpectedCommands(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(0, '{"count":0}', ''));

        (new ImplementersTool($this->context(), $runner))->call(['interface' => 'App\I']);
        self::assertSame(['php', 'bin/altair', 'index:implements', 'App\I', '--format=json'], $runner->lastCommand);

        (new CallersTool($this->context(), $runner))->call(['method' => 'App\A::run']);
        self::assertSame(['php', 'bin/altair', 'index:callers-of', 'App\A::run', '--format=json'], $runner->lastCommand);

        (new DeadCodeTool($this->context(), $runner))->call([]);
        self::assertSame(['php', 'bin/altair', 'index:unused', '--format=json'], $runner->lastCommand);
    }

    public function testNonJsonOutputBecomesErrorEnvelope(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(2, 'No index found.', ''));

        $result = (new DeadCodeTool($this->context(), $runner))->call([]);

        self::assertFalse($result['ok']);
        self::assertSame(2, $result['exit_code']);
        self::assertArrayHasKey('error', $result);
    }
}
