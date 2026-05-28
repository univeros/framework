<?php

declare(strict_types=1);

namespace Altair\Tests\Doctor\Check;

use Altair\Doctor\Check\ComposerDepsCheck;
use Altair\Doctor\Check\CsCleanCheck;
use Altair\Doctor\Check\DeterminismCheck;
use Altair\Doctor\Check\ManifestsCurrentCheck;
use Altair\Doctor\Check\MigrationsPendingCheck;
use Altair\Doctor\Check\OpenApiValidCheck;
use Altair\Doctor\Check\PhpstanCleanCheck;
use Altair\Doctor\Check\SpecDriftCheck;
use Altair\Doctor\Check\TestsPassingCheck;
use Altair\Doctor\Process\ProcessResult;
use Altair\Doctor\Result\CheckStatus;
use Altair\Tests\Doctor\Support\FakeProcessRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsCleanCheck::class)]
#[CoversClass(PhpstanCleanCheck::class)]
#[CoversClass(ManifestsCurrentCheck::class)]
#[CoversClass(DeterminismCheck::class)]
#[CoversClass(ComposerDepsCheck::class)]
#[CoversClass(TestsPassingCheck::class)]
#[CoversClass(SpecDriftCheck::class)]
#[CoversClass(OpenApiValidCheck::class)]
#[CoversClass(MigrationsPendingCheck::class)]
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

    public function testComposerDepsOkWhenInSync(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(0));

        $this->assertSame(CheckStatus::Ok, (new ComposerDepsCheck($runner, '/p'))->run()->status);
    }

    public function testComposerDepsWarnsWithInstallActionWhenStale(): void
    {
        $runner = new FakeProcessRunner();
        $runner->on(['composer', 'install', '--dry-run', '--no-interaction', '--no-scripts'], new ProcessResult(1));

        $result = (new ComposerDepsCheck($runner, '/p'))->run();
        $this->assertSame(CheckStatus::Warn, $result->status);
        $this->assertSame('composer install', $result->agentAction?->toArray()['command']);
    }

    public function testComposerDepsFixRunsInstall(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(0));

        $this->assertTrue((new ComposerDepsCheck($runner, '/p'))->fix());
        $this->assertContains(['composer', 'install', '--no-interaction'], $runner->calls);
    }

    public function testTestsPassingOk(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(0));

        $this->assertSame(CheckStatus::Ok, (new TestsPassingCheck($runner, '/p'))->run()->status);
    }

    public function testTestsPassingErrorsWhenSuiteFails(): void
    {
        $runner = new FakeProcessRunner();
        $runner->on(['vendor/bin/phpunit', '--no-progress'], new ProcessResult(1));

        $this->assertSame(CheckStatus::Error, (new TestsPassingCheck($runner, '/p'))->run()->status);
    }

    public function testTestsPassingDependsOnComposerDeps(): void
    {
        $this->assertSame(['composer_deps'], (new TestsPassingCheck(new FakeProcessRunner(), '/p'))->dependsOn());
    }

    public function testSpecDriftOkWhenAligned(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(0));

        $this->assertSame(CheckStatus::Ok, (new SpecDriftCheck($runner, '/p'))->run()->status);
    }

    public function testSpecDriftWarnsWithScaffoldActionWhenDrifted(): void
    {
        $runner = new FakeProcessRunner();
        $runner->on(['php', 'bin/altair', 'spec:lint'], new ProcessResult(1));

        $result = (new SpecDriftCheck($runner, '/p'))->run();
        $this->assertSame(CheckStatus::Warn, $result->status);
        $this->assertSame('bin/altair spec:scaffold', $result->agentAction?->toArray()['command']);
    }

    public function testOpenApiValidOk(): void
    {
        $runner = new FakeProcessRunner();
        $runner->on(['php', 'bin/altair', 'spec:emit-openapi', '--out=/dev/null'], new ProcessResult(0));

        $this->assertSame(CheckStatus::Ok, (new OpenApiValidCheck($runner, '/p'))->run()->status);
    }

    public function testOpenApiValidErrorsWhenEmitterFails(): void
    {
        $runner = new FakeProcessRunner();
        $runner->on(['php', 'bin/altair', 'spec:emit-openapi', '--out=/dev/null'], new ProcessResult(2));

        $this->assertSame(CheckStatus::Error, (new OpenApiValidCheck($runner, '/p'))->run()->status);
    }

    public function testMigrationsPendingOk(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(0));

        $this->assertSame(CheckStatus::Ok, (new MigrationsPendingCheck($runner, '/p'))->run()->status);
    }

    public function testMigrationsPendingWarnsWithMigrateAction(): void
    {
        $runner = new FakeProcessRunner();
        $runner->on(['php', 'bin/altair', 'db:migrate:status'], new ProcessResult(1));

        $result = (new MigrationsPendingCheck($runner, '/p'))->run();
        $this->assertSame(CheckStatus::Warn, $result->status);
        $this->assertSame('bin/altair db:migrate', $result->agentAction?->toArray()['command']);
    }

    public function testMigrationsPendingFixRunsMigrate(): void
    {
        $runner = new FakeProcessRunner(new ProcessResult(0));

        $this->assertTrue((new MigrationsPendingCheck($runner, '/p'))->fix());
        $this->assertContains(['php', 'bin/altair', 'db:migrate'], $runner->calls);
    }

    public function testMigrationsPendingDependsOnDatabaseReachable(): void
    {
        $this->assertSame(['database_reachable'], (new MigrationsPendingCheck(new FakeProcessRunner(), '/p'))->dependsOn());
    }
}
