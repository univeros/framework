<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Output;

use Altair\Profiling\Diff\ChangedFunction;
use Altair\Profiling\Diff\ProfileDiff;
use Altair\Profiling\Model\ProfileReport;
use Altair\Profiling\Storage\ProfileSummary;

/**
 * Terminal-facing rendering for the profile CLI commands. JSON output is
 * built per-command so each tool's structured shape stays explicit; only the
 * scannable text is shared here.
 */
final class HumanRenderer
{
    public function report(ProfileReport $report): string
    {
        $lines = [
            \sprintf('Profile %s — %s', $report->id, $report->target),
            \sprintf(
                '  samples=%d  duration=%dms  period=%dus  backend=%s',
                $report->totalSamples,
                $report->durationMs,
                $report->periodUs,
                $report->backend,
            ),
            '',
            'Hotspots (top ' . \count($report->hotspots) . ' by self-samples):',
        ];

        foreach ($report->hotspots as $hotspot) {
            $lines[] = \sprintf(
                '  %6.2f%%  self=%-5d  total=%-5d  %s',
                $hotspot->percent,
                $hotspot->selfSamples,
                $hotspot->totalSamples,
                $hotspot->function,
            );
        }

        return implode("\n", $lines) . "\n";
    }

    public function diff(ProfileDiff $diff): string
    {
        $lines = [
            \sprintf('Compare base=%s head=%s', $diff->baseId, $diff->headId),
            \sprintf(
                '  base_samples=%d  head_samples=%d  total Δ=%+.1f%%',
                $diff->baseTotalSamples,
                $diff->headTotalSamples,
                $diff->deltaTotalPercent,
            ),
        ];

        if ($diff->regressions !== []) {
            $lines[] = '';
            $lines[] = 'Regressions (Δ ≥ ' . ProfileDiff::REGRESSION_THRESHOLD_PERCENT . '%):';
            foreach ($diff->regressions as $row) {
                $lines[] = $this->changeRow($row, '✗');
            }
        }

        $improvements = array_values(array_filter(
            $diff->changes,
            static fn(ChangedFunction $c): bool => $c->deltaPercent < 0,
        ));
        if ($improvements !== []) {
            $lines[] = '';
            $lines[] = 'Improvements:';
            foreach ($improvements as $row) {
                $lines[] = $this->changeRow($row, '✓');
            }
        }

        if ($diff->changes === []) {
            $lines[] = '';
            $lines[] = 'No significant changes (threshold ' . ProfileDiff::SIGNIFICANCE_PERCENT . '%).';
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<ProfileSummary> $summaries
     */
    public function list(array $summaries): string
    {
        if ($summaries === []) {
            return "No profiles stored. Capture one with `bin/altair profile:run <script.php>`.\n";
        }

        $lines = [\sprintf('%d profile(s):', \count($summaries))];
        foreach ($summaries as $summary) {
            $lines[] = \sprintf(
                '  %s  samples=%-5d  %s  %s',
                $summary->id,
                $summary->totalSamples,
                $summary->createdAt,
                $summary->target,
            );
        }

        return implode("\n", $lines) . "\n";
    }

    private function changeRow(ChangedFunction $row, string $marker): string
    {
        return \sprintf(
            '  %s %+7.1f%%  base=%-5d  head=%-5d  %s',
            $marker,
            $row->deltaPercent,
            $row->baseSelfSamples,
            $row->headSelfSamples,
            $row->function,
        );
    }
}
