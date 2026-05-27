<?php

declare(strict_types=1);

namespace Altair\Tests\Doctor\Check;

use Altair\Doctor\Check\CsCleanCheck;
use Altair\Doctor\Check\DeterminismCheck;
use Altair\Doctor\Check\ManifestsCurrentCheck;
use Altair\Doctor\Check\PhpstanCleanCheck;
use Altair\Doctor\Process\ProcessResult;
use Altair\Doctor\Result\CheckStatus;
use Altair\Tests\Doctor\Support\FakeProcessRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsCleanCheck::class)]
#[CoversClass(PhpstanCleanCheck::class)]
#[CoversClass(ManifestsCurrentCheck::class)]
#[CoversClass(DeterminismCheck::class)]
class ProcessChecksTest extends TestCase
{
    public function testCsCleanOkWhenComposerCsPasses(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(0));

        $this->assertSame(CheckStatus::Ok, (new CsCleanCheck($runner, '/p'))->run()->status);
    }

    public function testCsCleanWarnsWithRunCommandActionWhenDirty(): void
    {
        $runner = new FakeProcessRunner();
        $runner->on(['composer', 'cs'], new ProcessResult(1));

        $result = (new CsCleanCheck($runner, '/p'))->run();
        $this->assertSame(CheckStatus::Warn, $result->status);
        $this->assertNotNull($result->agentAction);
        $this->assertSame('composer cs:fix', $result->agentAction->toArray()['command']);
    }

    public function testCsCleanFixRunsComposerCsFix(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(0));

        $this->assertTrue((new CsCleanCheck($runner, '/p'))->fix());
        $this->assertContains(['composer', 'cs:fix'], $runner->calls);
    }

    public function testPhpstanErrorsWhenAnalysisFails(): void
    {
        $runner = new FakeProcessRunner();
        $runner->on(['composer', 'stan'], new ProcessResult(1));

        $this->assertSame(CheckStatus::Error, (new PhpstanCleanCheck($runner, '/p'))->run()->status);
    }

    public function testManifestsWarnWithGenerateActionWhenStale(): void
    {
        $runner = new FakeProcessRunner();
        $runner->on(['php', 'bin/altair', 'manifest:diff', '--format=json'], new ProcessResult(1));

        $result = (new ManifestsCurrentCheck($runner, '/p'))->run();
        $this->assertSame(CheckStatus::Warn, $result->status);
        $this->assertSame('bin/altair manifest:generate', $result->agentAction?->toArray()['command']);
    }

    public function testDeterminismSkippedWhenUnconfigured(): void
    {
        $result = (new DeterminismCheck(new FakeProcessRunner(), '/p'))->run();

        $this->assertSame(CheckStatus::Skipped, $result->status);
    }

    public function testDeterminismOkWhenNoDrift(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(0));
        $check = new DeterminismCheck($runner, '/p', [['bin/altair', 'manifest:generate']], ['.agent/']);

        $this->assertSame(CheckStatus::Ok, $check->run()->status);
    }

    public function testDeterminismErrorsOnDrift(): void
    {
        $runner = new FakeProcessRunner();
        $runner->on(['bin/altair', 'manifest:generate'], new ProcessResult(0));
        $runner->on(['git', 'diff', '--exit-code', '.agent/'], new ProcessResult(1));

        $check = new DeterminismCheck($runner, '/p', [['bin/altair', 'manifest:generate']], ['.agent/']);

        $this->assertSame(CheckStatus::Error, $check->run()->status);
    }

    public function testDeterminismErrorsWhenGeneratorFails(): void
    {
        $runner = new FakeProcessRunner();
        $runner->on(['bin/altair', 'manifest:generate'], new ProcessResult(3));

        $check = new DeterminismCheck($runner, '/p', [['bin/altair', 'manifest:generate']], ['.agent/']);

        $result = $check->run();
        $this->assertSame(CheckStatus::Error, $result->status);
        $this->assertStringContainsString('failed to run', $result->detail);
    }
}
