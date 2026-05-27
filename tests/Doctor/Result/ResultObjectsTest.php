<?php

declare(strict_types=1);

namespace Altair\Tests\Doctor\Result;

use Altair\Doctor\Result\AgentAction;
use Altair\Doctor\Result\CheckResult;
use Altair\Doctor\Result\CheckStatus;
use Altair\Doctor\Result\Report;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckStatus::class)]
#[CoversClass(AgentAction::class)]
#[CoversClass(CheckResult::class)]
#[CoversClass(Report::class)]
class ResultObjectsTest extends TestCase
{
    public function testStatusSeverityOrdersOkBelowWarnBelowError(): void
    {
        $this->assertSame(0, CheckStatus::Ok->severity());
        $this->assertSame(0, CheckStatus::Skipped->severity());
        $this->assertSame(1, CheckStatus::Warn->severity());
        $this->assertSame(2, CheckStatus::Error->severity());
    }

    public function testAgentActionFlattensTypeAndPayload(): void
    {
        $this->assertSame(['type' => 'run_command', 'command' => 'composer cs:fix'], AgentAction::runCommand('composer cs:fix')->toArray());
        $this->assertSame(['type' => 'edit_file', 'file' => 'a.php', 'hint' => 'add x'], AgentAction::editFile('a.php', 'add x')->toArray());
        $this->assertSame(['type' => 'install_dep', 'package' => 'ext-redis'], AgentAction::installDep('ext-redis')->toArray());
    }

    public function testCheckResultOmitsAbsentOptionalFields(): void
    {
        $ok = CheckResult::ok('php_version', 'fine')->toArray();
        $this->assertSame(['name' => 'php_version', 'status' => 'ok', 'detail' => 'fine'], $ok);
    }

    public function testCheckResultIncludesFixActionAndSourceWhenPresent(): void
    {
        $warn = CheckResult::warn('spec_drift', 'drift', 'edit it', AgentAction::editFile('In.php', 'add field'), 'api/x.yaml')->toArray();
        $this->assertSame('warn', $warn['status']);
        $this->assertSame('edit it', $warn['fix']);
        $this->assertSame(['type' => 'edit_file', 'file' => 'In.php', 'hint' => 'add field'], $warn['agent_action']);
        $this->assertSame('api/x.yaml', $warn['source']);
    }

    public function testReportStatusIsWorstAndExitCodeMatchesSeverity(): void
    {
        $report = new Report([
            CheckResult::ok('a', 'ok'),
            CheckResult::warn('b', 'warn'),
            CheckResult::error('c', 'err'),
            CheckResult::skipped('d', 'skip'),
        ], 12);

        $this->assertSame(CheckStatus::Error, $report->status());
        $this->assertSame(2, $report->exitCode());

        $warnOnly = new Report([CheckResult::ok('a', 'ok'), CheckResult::warn('b', 'w')], 1);
        $this->assertSame(1, $warnOnly->exitCode());

        $allOk = new Report([CheckResult::ok('a', 'ok')], 0);
        $this->assertSame(0, $allOk->exitCode());
    }

    public function testReportToArrayShape(): void
    {
        $array = (new Report([CheckResult::ok('a', 'ok')], 7))->toArray();
        $this->assertSame('ok', $array['status']);
        $this->assertSame(7, $array['duration_ms']);
        $this->assertSame([['name' => 'a', 'status' => 'ok', 'detail' => 'ok']], $array['checks']);
    }
}
