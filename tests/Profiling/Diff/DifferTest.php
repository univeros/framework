<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Profiling\Diff;

use Altair\Profiling\Diff\ChangedFunction;
use Altair\Profiling\Diff\Differ;
use Altair\Profiling\Diff\ProfileDiff;
use Altair\Profiling\Model\CallNode;
use Altair\Profiling\Model\Hotspot;
use Altair\Profiling\Model\ProfileReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Differ::class)]
#[CoversClass(ProfileDiff::class)]
#[CoversClass(ChangedFunction::class)]
final class DifferTest extends TestCase
{
    public function testRegressionIsFlaggedWhenSelfTimeIncreasesPastTheThreshold(): void
    {
        $base = $this->report('base', hotspots: [
            new Hotspot('App\Fast', 100, 100, 100.0),
        ]);
        $head = $this->report('head', hotspots: [
            new Hotspot('App\Fast', 130, 130, 100.0), // +30% on a function with 130 head-samples
        ]);

        $diff = (new Differ())->diff($base, $head);

        self::assertTrue($diff->hasRegressions());
        self::assertSame('App\Fast', $diff->regressions[0]->function);
        self::assertSame(30.0, $diff->regressions[0]->deltaPercent);
    }

    public function testSmallChangesAreNotReported(): void
    {
        $base = $this->report('base', hotspots: [new Hotspot('App\Fast', 100, 100, 100.0)]);
        $head = $this->report('head', hotspots: [new Hotspot('App\Fast', 103, 103, 100.0)]); // +3%

        $diff = (new Differ())->diff($base, $head);

        self::assertSame([], $diff->changes);
        self::assertFalse($diff->hasRegressions());
    }

    public function testImprovementIsAChangeButNotARegression(): void
    {
        $base = $this->report('base', hotspots: [new Hotspot('App\Slow', 100, 100, 100.0)]);
        $head = $this->report('head', hotspots: [new Hotspot('App\Slow', 50, 50, 100.0)]); // -50%

        $diff = (new Differ())->diff($base, $head);

        self::assertCount(1, $diff->changes);
        self::assertSame(-50.0, $diff->changes[0]->deltaPercent);
        self::assertFalse($diff->hasRegressions());
    }

    public function testNewFunctionAppearsAsOneHundredPercentDelta(): void
    {
        $base = $this->report('base', hotspots: []);
        $head = $this->report('head', hotspots: [new Hotspot('App\Brand\New', 20, 20, 100.0)]);

        $diff = (new Differ())->diff($base, $head);

        self::assertCount(1, $diff->changes);
        self::assertSame(100.0, $diff->changes[0]->deltaPercent);
        self::assertTrue($diff->hasRegressions(), 'a brand-new hotspot in HEAD is a regression');
    }

    public function testTinyAbsoluteRegressionIsBelowTheMinSamplesFloor(): void
    {
        // 1 sample → 4 samples is +300% in percentage terms, but only 4 absolute
        // samples — below the REGRESSION_MIN_SAMPLES floor, so not gated.
        $base = $this->report('base', hotspots: [new Hotspot('App\Tail', 1, 1, 100.0)]);
        $head = $this->report('head', hotspots: [new Hotspot('App\Tail', 4, 4, 100.0)]);

        $diff = (new Differ())->diff($base, $head);

        self::assertCount(1, $diff->changes, 'still surfaced as a change');
        self::assertFalse($diff->hasRegressions(), 'but below the min-samples floor for regressions');
    }

    /**
     * @param list<Hotspot> $hotspots
     */
    private function report(string $id, array $hotspots): ProfileReport
    {
        return new ProfileReport(
            $id,
            'test target',
            '2026-05-29T12:00:00Z',
            totalSamples: array_sum(array_map(static fn(Hotspot $h): int => $h->selfSamples, $hotspots)),
            durationMs: 100,
            periodUs: 1_000,
            backend: 'excimer',
            tree: new CallNode('<root>', 0, 0, []),
            hotspots: $hotspots,
        );
    }
}
