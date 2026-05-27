<?php

declare(strict_types=1);

namespace Altair\Tests\Doctor;

use Altair\Doctor\CheckRegistry;
use Altair\Doctor\Doctor;
use Altair\Doctor\Result\CheckResult;
use Altair\Doctor\Result\CheckStatus;
use Altair\Tests\Doctor\Support\FakeCheck;
use Altair\Tests\Doctor\Support\FakeFixableCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Doctor::class)]
#[CoversClass(CheckRegistry::class)]
class DoctorTest extends TestCase
{
    public function testRunsAllChecksAndReportsWorstStatus(): void
    {
        $registry = new CheckRegistry([
            new FakeCheck('a', CheckResult::ok('a', 'ok')),
            new FakeCheck('b', CheckResult::warn('b', 'warn')),
        ]);

        $report = (new Doctor($registry))->run();

        $this->assertCount(2, $report->checks);
        $this->assertSame(CheckStatus::Warn, $report->status());
    }

    public function testOnlyFilterRunsExclusivelyTheNamedChecks(): void
    {
        $a = new FakeCheck('a', CheckResult::ok('a', 'ok'));
        $b = new FakeCheck('b', CheckResult::ok('b', 'ok'));
        $registry = new CheckRegistry([$a, $b]);

        $report = (new Doctor($registry))->run(only: ['b']);

        $this->assertFalse($a->ran);
        $this->assertTrue($b->ran);
        $this->assertCount(1, $report->checks);
    }

    public function testSkipFilterExcludesNamedChecks(): void
    {
        $a = new FakeCheck('a', CheckResult::ok('a', 'ok'));
        $registry = new CheckRegistry([$a]);

        $report = (new Doctor($registry))->run(skip: ['a']);

        $this->assertFalse($a->ran);
        $this->assertSame([], $report->checks);
    }

    public function testDownstreamCheckIsSkippedWhenPrerequisiteErrors(): void
    {
        $downstream = new FakeCheck('tests_passing', CheckResult::ok('tests_passing', 'ok'), ['composer_deps']);
        $registry = new CheckRegistry([
            new FakeCheck('composer_deps', CheckResult::error('composer_deps', 'stale')),
            $downstream,
        ]);

        $report = (new Doctor($registry))->run();

        $this->assertFalse($downstream->ran, 'prerequisite errored, so downstream must not run');
        $this->assertSame(CheckStatus::Skipped, $report->checks[1]->status);
        $this->assertStringContainsString('composer_deps', $report->checks[1]->detail);
    }

    public function testFixIsAttemptedThenCheckIsReRun(): void
    {
        $check = new FakeFixableCheck(
            'cs_clean',
            CheckResult::warn('cs_clean', 'dirty'),
            CheckResult::ok('cs_clean', 'clean'),
        );
        $registry = new CheckRegistry([$check]);

        $report = (new Doctor($registry))->run(fix: true);

        $this->assertSame(1, $check->fixCount);
        $this->assertSame(2, $check->runCount, 'check runs, fix applies, check re-runs');
        $this->assertSame(CheckStatus::Ok, $report->checks[0]->status);
    }

    public function testFixIsNotAttemptedWithoutTheFlag(): void
    {
        $check = new FakeFixableCheck('cs_clean', CheckResult::warn('cs_clean', 'dirty'), CheckResult::ok('cs_clean', 'clean'));
        $registry = new CheckRegistry([$check]);

        (new Doctor($registry))->run();

        $this->assertSame(0, $check->fixCount);
        $this->assertSame(1, $check->runCount);
    }
}
