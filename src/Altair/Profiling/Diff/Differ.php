<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Diff;

use Altair\Profiling\Model\Hotspot;
use Altair\Profiling\Model\ProfileReport;

/**
 * Compares two {@see ProfileReport}s function-by-function and produces a
 * {@see ProfileDiff} marking the changes worth showing and the regressions
 * worth gating on. The function set is the UNION of both reports' hotspots —
 * a function that disappeared (HEAD self = 0) is a 100% improvement; a
 * function that appeared (BASE self = 0) is reported as a +∞ change rendered
 * as `100.0` in the delta column to keep the JSON shape numeric.
 */
final readonly class Differ
{
    public function diff(ProfileReport $base, ProfileReport $head): ProfileDiff
    {
        $baseRows = $this->index($base->hotspots);
        $headRows = $this->index($head->hotspots);

        $functions = array_unique([...array_keys($baseRows), ...array_keys($headRows)]);

        $changes = [];
        $regressions = [];
        foreach ($functions as $function) {
            $baseSelf = $baseRows[$function] ?? 0;
            $headSelf = $headRows[$function] ?? 0;
            $delta = $this->deltaPercent($baseSelf, $headSelf);

            if (abs($delta) < ProfileDiff::SIGNIFICANCE_PERCENT) {
                continue;
            }

            $change = new ChangedFunction($function, $baseSelf, $headSelf, $delta);
            $changes[] = $change;

            if ($delta >= ProfileDiff::REGRESSION_THRESHOLD_PERCENT && $headSelf >= ProfileDiff::REGRESSION_MIN_SAMPLES) {
                $regressions[] = $change;
            }
        }

        usort($changes, static fn(ChangedFunction $a, ChangedFunction $b): int => abs($b->deltaPercent) <=> abs($a->deltaPercent));
        usort($regressions, static fn(ChangedFunction $a, ChangedFunction $b): int => $b->deltaPercent <=> $a->deltaPercent);

        return new ProfileDiff(
            $base->id,
            $head->id,
            $base->totalSamples,
            $head->totalSamples,
            $this->deltaPercent($base->totalSamples, $head->totalSamples),
            $changes,
            $regressions,
        );
    }

    /**
     * @param list<Hotspot> $hotspots
     *
     * @return array<string, int>
     */
    private function index(array $hotspots): array
    {
        $rows = [];
        foreach ($hotspots as $hotspot) {
            $rows[$hotspot->function] = $hotspot->selfSamples;
        }

        return $rows;
    }

    private function deltaPercent(int $base, int $head): float
    {
        if ($base === 0 && $head === 0) {
            return 0.0;
        }

        if ($base === 0) {
            return 100.0; // appeared
        }

        return round(($head - $base) / $base * 100, 2);
    }
}
