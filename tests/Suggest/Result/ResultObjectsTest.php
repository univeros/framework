<?php

declare(strict_types=1);

namespace Altair\Tests\Suggest\Result;

use Altair\Suggest\Exception\SuggestException;
use Altair\Suggest\Result\Severity;
use Altair\Suggest\Result\Suggestion;
use Altair\Suggest\Result\SuggestionReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Severity::class)]
#[CoversClass(Suggestion::class)]
#[CoversClass(SuggestionReport::class)]
class ResultObjectsTest extends TestCase
{
    public function testSeverityRankOrdersWarningAboveInfo(): void
    {
        $this->assertGreaterThan(Severity::Info->rank(), Severity::Warning->rank());
    }

    public function testSeverityFromNameIsCaseAndWhitespaceInsensitive(): void
    {
        $this->assertSame(Severity::Warning, Severity::fromName('  WARNING '));
        $this->assertSame(Severity::Info, Severity::fromName('info'));
    }

    public function testSeverityFromNameRejectsUnknown(): void
    {
        $this->expectException(SuggestException::class);
        Severity::fromName('critical');
    }

    public function testSuggestionOmitsFixWhenAbsent(): void
    {
        $suggestion = new Suggestion('r', Severity::Info, 'Subject', 'message');

        $this->assertSame(
            ['rule' => 'r', 'severity' => 'info', 'subject' => 'Subject', 'message' => 'message'],
            $suggestion->toArray(),
        );
    }

    public function testSuggestionIncludesFixWhenPresent(): void
    {
        $suggestion = new Suggestion('r', Severity::Warning, 'Subject', 'message', 'do this');

        $this->assertSame('do this', $suggestion->toArray()['fix']);
    }

    public function testReportExitCodeIsOneWhenAnyWarning(): void
    {
        $report = new SuggestionReport([
            new Suggestion('a', Severity::Info, 's', 'm'),
            new Suggestion('b', Severity::Warning, 's', 'm'),
        ], 4);

        $this->assertSame(1, $report->exitCode());
        $this->assertSame(1, $report->countBy(Severity::Warning));
        $this->assertSame(1, $report->countBy(Severity::Info));
    }

    public function testReportExitCodeIsZeroWithOnlyInfo(): void
    {
        $report = new SuggestionReport([new Suggestion('a', Severity::Info, 's', 'm')], 0);

        $this->assertSame(0, $report->exitCode());
    }

    public function testReportToArrayShape(): void
    {
        $report = new SuggestionReport([new Suggestion('a', Severity::Info, 's', 'm')], 7);

        $this->assertSame(
            ['count' => 1, 'duration_ms' => 7, 'suggestions' => [
                ['rule' => 'a', 'severity' => 'info', 'subject' => 's', 'message' => 'm'],
            ]],
            $report->toArray(),
        );
    }
}
