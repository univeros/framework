<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Profiling\Tree;

use Altair\Profiling\Model\Hotspot;
use Altair\Profiling\Model\Sample;
use Altair\Profiling\Tree\HotspotAnalyzer;
use Altair\Profiling\Tree\TreeBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HotspotAnalyzer::class)]
#[CoversClass(Hotspot::class)]
final class HotspotAnalyzerTest extends TestCase
{
    public function testHotspotsAreRankedByAggregateSelfSamplesAcrossAllCallSites(): void
    {
        $tree = (new TreeBuilder())->build([
            new Sample(['Action', 'Slow', 'PDO::query'], count: 10),
            new Sample(['Action', 'Cache', 'PDO::query'], count: 5),  // same fn, different parent
            new Sample(['Action', 'Slow'], count: 2),
        ]);

        $hotspots = (new HotspotAnalyzer())->analyse($tree);
        $byFn = [];
        foreach ($hotspots as $h) {
            $byFn[$h->function] = $h;
        }

        // PDO::query is the leaf of 10 + 5 samples regardless of which parent reached it.
        self::assertSame(15, $byFn['PDO::query']->selfSamples);
        // `Slow` is the leaf of 2 samples and a parent of 10 (so total=12, self=2).
        self::assertSame(2, $byFn['Slow']->selfSamples);
        self::assertSame(12, $byFn['Slow']->totalSamples);
        // PDO::query is the hottest (most self-samples).
        self::assertSame('PDO::query', $hotspots[0]->function);
    }

    public function testPercentSumsToOneHundredAcrossAllRows(): void
    {
        $tree = (new TreeBuilder())->build([
            new Sample(['A'], count: 3),
            new Sample(['B'], count: 1),
        ]);

        $hotspots = (new HotspotAnalyzer())->analyse($tree);
        $totalPercent = array_sum(array_map(static fn(Hotspot $h): float => $h->percent, $hotspots));

        self::assertEqualsWithDelta(100.0, $totalPercent, 0.01);
    }

    public function testLimitTruncatesToTheRequestedTopN(): void
    {
        $samples = [];
        for ($i = 0; $i < 20; ++$i) {
            $samples[] = new Sample(['fn_' . $i], count: 20 - $i);
        }

        $tree = (new TreeBuilder())->build($samples);

        $top5 = (new HotspotAnalyzer())->analyse($tree, limit: 5);

        self::assertCount(5, $top5);
        self::assertSame('fn_0', $top5[0]->function); // 20 samples — hottest
    }

    public function testEmptyTreeProducesEmptyHotspots(): void
    {
        $tree = (new TreeBuilder())->build([]);

        self::assertSame([], (new HotspotAnalyzer())->analyse($tree));
    }
}
