<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\TestReporter;

use Altair\TestReporter\Diff\ValueDiffer;
use Altair\TestReporter\Resolver\SourceUnderTestResolver;
use Altair\TestReporter\Result\FailureRecord;
use Altair\TestReporter\Result\ReportStatus;
use Altair\TestReporter\Result\SkippedRecord;
use Altair\TestReporter\Result\StackFrame;
use Altair\TestReporter\Result\TestReport;
use Altair\TestReporter\Result\Totals;
use DateTimeImmutable;
use PHPUnit\Event\Code\ComparisonFailure;
use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Code\TestMethod;

/**
 * Single owner of mutable run state — every subscriber feeds into one
 * of these, the Extension's shutdown hook builds the {@see TestReport}.
 *
 * Stays in-process for the duration of one PHPUnit run. Not thread-safe
 * — PHPUnit's per-process model doesn't need it to be.
 */
final class ResultCollector
{
    public Totals $totals;

    /** @var list<FailureRecord> */
    public array $failures = [];

    /** @var list<FailureRecord> */
    public array $errors = [];

    /** @var list<SkippedRecord> */
    public array $skipped = [];

    /** @var list<SkippedRecord> */
    public array $risky = [];

    /** @var list<SkippedRecord> */
    public array $incomplete = [];

    private readonly DateTimeImmutable $startedAt;

    private readonly float $startMicrotime;

    public function __construct(
        private readonly SourceUnderTestResolver $resolver,
        private readonly ValueDiffer $differ = new ValueDiffer(),
    ) {
        $this->startedAt = new DateTimeImmutable('now');
        $this->startMicrotime = microtime(true);
        $this->totals = new Totals();
    }

    public function recordTestStart(): void
    {
        $this->totals->tests++;
    }

    public function recordPassed(): void
    {
        $this->totals->passed++;
    }

    public function recordAssertions(int $count): void
    {
        $this->totals->assertions += $count;
    }

    public function recordFailure(Test $test, string $message, ?ComparisonFailure $comparison, string $type): void
    {
        $this->totals->failed++;
        $this->failures[] = $this->buildFailureRecord($test, $message, $comparison, $type);
    }

    public function recordError(Test $test, string $message, string $type): void
    {
        $this->totals->errored++;
        $this->errors[] = $this->buildFailureRecord($test, $message, null, $type);
    }

    public function recordSkipped(Test $test, string $reason): void
    {
        $this->totals->skipped++;
        $this->skipped[] = new SkippedRecord(test: $this->testName($test), reason: $reason);
    }

    public function recordRisky(Test $test, string $reason): void
    {
        $this->totals->risky++;
        $this->risky[] = new SkippedRecord(test: $this->testName($test), reason: $reason);
    }

    public function recordIncomplete(Test $test, string $reason): void
    {
        $this->totals->incomplete++;
        $this->incomplete[] = new SkippedRecord(test: $this->testName($test), reason: $reason);
    }

    public function recordWarning(): void
    {
        $this->totals->warnings++;
    }

    public function build(string $phpunitVersion): TestReport
    {
        $status = match (true) {
            $this->totals->errored > 0 => ReportStatus::Error,
            $this->totals->failed > 0 => ReportStatus::Fail,
            default => ReportStatus::Pass,
        };

        return new TestReport(
            startedAt: $this->startedAt,
            durationMs: (int) ((microtime(true) - $this->startMicrotime) * 1000),
            phpVersion: PHP_VERSION,
            phpunitVersion: $phpunitVersion,
            totals: $this->totals,
            status: $status,
            failures: $this->failures,
            errors: $this->errors,
            skipped: $this->skipped,
            risky: $this->risky,
            incomplete: $this->incomplete,
        );
    }

    private function buildFailureRecord(Test $test, string $message, ?ComparisonFailure $comparison, string $type): FailureRecord
    {
        $testFile = '(unknown)';
        $testLine = null;
        $testClass = '';
        $testMethod = '';

        if ($test instanceof TestMethod) {
            $testFile = $test->file();
            $testLine = $test->line();
            $testClass = $test->className();
            $testMethod = $test->methodName();
        }

        $sources = $testClass !== '' && $testMethod !== ''
            ? $this->resolver->resolve($testClass, $testMethod)
            : [];

        $diff = $this->differ->diff($comparison);
        [$expected, $actual] = $this->expectedActualStrings($comparison);

        return new FailureRecord(
            test: $this->testName($test),
            testFile: $testFile,
            testLine: $testLine,
            type: $type,
            message: $message,
            expected: $expected,
            actual: $actual,
            diff: $diff,
            sourceUnderTest: $sources,
            stackTrace: [new StackFrame(file: $testFile, line: $testLine ?? 0, function: $this->testName($test))],
        );
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function expectedActualStrings(?ComparisonFailure $comparison): array
    {
        if (!$comparison instanceof ComparisonFailure) {
            return [null, null];
        }

        $expected = $comparison->expected();
        $actual = $comparison->actual();

        return [
            $expected === '' ? null : $expected,
            $actual === '' ? null : $actual,
        ];
    }

    private function testName(Test $test): string
    {
        if ($test instanceof TestMethod) {
            return $test->className() . '::' . $test->methodName();
        }

        return $test->id();
    }
}
