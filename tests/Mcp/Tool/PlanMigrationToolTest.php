<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Tool;

use Altair\Doctor\Process\ProcessResult;
use Altair\Mcp\Support\ProjectContext;
use Altair\Mcp\Tool\Database\PlanMigrationTool;
use Altair\Tests\Mcp\Fixtures\FakeProcessRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PlanMigrationTool::class)]
final class PlanMigrationToolTest extends TestCase
{
    private function context(): ProjectContext
    {
        return new ProjectContext('/tmp/mcp-plan', ProjectContext::detect()->altairSrcDir);
    }

    public function testReturnsParsedPlanAndBuildsCommand(): void
    {
        $json = '{"table":"users","two_phase":false,"migrations":[],"safety":{"skipped":true},"exit_code":0}';
        $runner = new FakeProcessRunner(new ProcessResult(0, $json, ''));
        $tool = new PlanMigrationTool($this->context(), $runner);

        $result = $tool->call(['from_spec' => 'a.yaml', 'to_spec' => 'b.yaml', 'driver' => 'mysql']);

        self::assertTrue($result['ok']);
        self::assertSame('users', $result['plan']['table']);
        self::assertContains('--format=json', $runner->lastCommand ?? []);
        self::assertContains('--from-spec=a.yaml', $runner->lastCommand ?? []);
        self::assertContains('--to-spec=b.yaml', $runner->lastCommand ?? []);
        self::assertContains('--driver=mysql', $runner->lastCommand ?? []);
        self::assertSame('/tmp/mcp-plan', $runner->lastCwd);
    }

    public function testPassesSpecPositionalAndSkipSafetyFlag(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(0, '{"table":"users","migrations":[],"exit_code":0}', ''));
        $tool = new PlanMigrationTool($this->context(), $runner);

        $tool->call(['spec' => 'api/users.yaml', 'skip_safety' => true]);

        self::assertContains('api/users.yaml', $runner->lastCommand ?? []);
        self::assertContains('--skip-safety', $runner->lastCommand ?? []);
    }

    public function testUnsafePlanReportsNotOkButStillReturnsPlan(): void
    {
        $json = '{"table":"users","migrations":[],"safety":{"has_errors":true},"exit_code":1}';
        $runner = new FakeProcessRunner(new ProcessResult(1, $json, ''));
        $tool = new PlanMigrationTool($this->context(), $runner);

        $result = $tool->call(['spec' => 'api/users.yaml']);

        self::assertFalse($result['ok']);
        self::assertSame(1, $result['exit_code']);
        self::assertSame('users', $result['plan']['table']);
    }

    public function testNonJsonOutputBecomesErrorEnvelope(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(2, 'Provide a spec path, --from-entity=FQCN, or --from-spec + --to-spec.', ''));
        $tool = new PlanMigrationTool($this->context(), $runner);

        $result = $tool->call([]);

        self::assertFalse($result['ok']);
        self::assertSame(2, $result['exit_code']);
        self::assertArrayHasKey('error', $result);
    }
}
