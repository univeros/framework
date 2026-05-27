<?php

declare(strict_types=1);

namespace Altair\Tests\TestReporter;

use Altair\TestReporter\Resolver\SourceUnderTestResolver;
use Altair\TestReporter\ResultCollector;
use Altair\TestReporter\Result\ReportStatus;
use Altair\TestReporter\Result\TestReport;
use Altair\TestReporter\Result\Totals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests cover the parts of `ResultCollector` that don't require
 * a `PHPUnit\Event\Code\Test` instance (an abstract value class
 * constructed only by PHPUnit's runner). The Test-taking record paths
 * are covered by integration: registering the extension in
 * `phpunit.xml.dist` makes every test in this suite exercise those
 * paths for real.
 */
#[CoversClass(ResultCollector::class)]
#[CoversClass(Totals::class)]
#[CoversClass(TestReport::class)]
class ResultCollectorTest extends TestCase
{
    public function testEmptyRunReportsPass(): void
    {
        $collector = $this->collector();
        $report = $collector->build('11.5.0');

        $this->assertSame(ReportStatus::Pass, $report->status);
        $this->assertSame(0, $report->totals->tests);
        $this->assertSame(0, $report->totals->failed);
    }

    public function testPassCountIncrementsTotals(): void
    {
        $collector = $this->collector();
        $collector->recordTestStart();
        $collector->recordPassed();
        $collector->recordAssertions(3);

        $report = $collector->build('11.5.0');
        $this->assertSame(1, $report->totals->tests);
        $this->assertSame(1, $report->totals->passed);
        $this->assertSame(3, $report->totals->assertions);
        $this->assertSame(ReportStatus::Pass, $report->status);
    }

    public function testWarningCountIncrementsTotals(): void
    {
        $collector = $this->collector();
        $collector->recordWarning();
        $collector->recordWarning();

        $report = $collector->build('11.5.0');
        $this->assertSame(2, $report->totals->warnings);
    }

    public function testReportSerialisesToJsonWithExpectedShape(): void
    {
        $collector = $this->collector();
        $collector->recordTestStart();
        $collector->recordPassed();
        $collector->recordAssertions(1);

        $report = $collector->build('11.5.0');
        $array = $report->toArray();

        $this->assertSame('1.0', $array['version']);
        $this->assertSame('pass', $array['result']);
        $this->assertIsArray($array['totals']);
        $this->assertIsArray($array['failures']);
        $this->assertIsString($array['started_at']);
        $this->assertSame('11.5.0', $array['phpunit_version']);
    }

    public function testReportTotalsRoundTripThroughJson(): void
    {
        $collector = $this->collector();
        for ($i = 0; $i < 5; $i++) {
            $collector->recordTestStart();
            $collector->recordPassed();
            $collector->recordAssertions(2);
        }

        $report = $collector->build('11.5.0');
        $decoded = json_decode((string) json_encode($report->toArray()), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(5, $decoded['totals']['tests']);
        $this->assertSame(5, $decoded['totals']['passed']);
        $this->assertSame(10, $decoded['totals']['assertions']);
    }

    private function collector(): ResultCollector
    {
        return new ResultCollector(new SourceUnderTestResolver(\dirname(__DIR__, 2)));
    }
}
