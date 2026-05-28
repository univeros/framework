<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Panel;

use Altair\Doctor\Doctor;
use Altair\Doctor\Result\CheckResult;
use Altair\Doctor\Result\CheckStatus;
use Altair\Observatory\Contracts\PanelInterface;
use Override;

/**
 * Surfaces the framework's {@see Doctor} health checks as a panel.
 *
 * Running the full doctor suite on every dashboard render would be far too
 * slow (phpstan/tests probes shell out), so the panel runs a constrained
 * `--only` subset. The host narrows or widens that list when wiring the panel;
 * an empty list runs every registered check (use sparingly).
 */
final readonly class HealthPanel implements PanelInterface
{
    /**
     * @param list<string> $only check names to run (empty = the full suite)
     */
    public function __construct(
        private Doctor $doctor,
        private array $only = [],
    ) {}

    #[Override]
    public function id(): string
    {
        return 'health';
    }

    #[Override]
    public function label(): string
    {
        return 'Health';
    }

    #[Override]
    public function icon(): string
    {
        return 'heart-pulse';
    }

    #[Override]
    public function snapshot(): PanelSnapshot
    {
        $report = $this->doctor->run($this->only);

        $passing = 0;
        $failed = 0;
        $skipped = 0;
        $items = [];

        foreach ($report->checks as $check) {
            $items[] = $this->row($check);

            match ($check->status) {
                CheckStatus::Ok => $passing++,
                CheckStatus::Error, CheckStatus::Warn => $failed++,
                CheckStatus::Skipped => $skipped++,
            };
        }

        $total = \count($report->checks);

        return new PanelSnapshot(
            $this->status($total, $passing, $failed),
            \sprintf('%d/%d passing', $passing, $total),
            [
                'total' => $total,
                'passing' => $passing,
                'failed' => $failed,
                'skipped' => $skipped,
                'duration_ms' => $report->durationMs,
            ],
            $items,
        );
    }

    /**
     * Worst observed outcome wins. Errors/warns count as failures (Critical),
     * any skip without a clean pass is a Warning, an all-ok run is Ok, and an
     * empty run (nothing matched the `--only` filter) is Unknown.
     *
     * Counts are used instead of the report's own worst-status because the
     * doctor collapses Skipped to severity 0 (same as Ok), so a skipped-only
     * run would be indistinguishable from a clean run at the report level.
     */
    private function status(int $total, int $passing, int $failed): PanelStatus
    {
        if ($total === 0) {
            return PanelStatus::Unknown;
        }

        if ($failed > 0) {
            return PanelStatus::Critical;
        }

        if ($passing < $total) {
            return PanelStatus::Warning;
        }

        return PanelStatus::Ok;
    }

    /**
     * @return array<string, scalar|null>
     */
    private function row(CheckResult $check): array
    {
        return [
            'name' => $check->name,
            'status' => $check->status->value,
            'summary' => $check->detail,
        ];
    }
}
