<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor\Result;

/**
 * The full result of a doctor run: every check's result plus the wall-clock
 * duration.
 *
 * `durationMs` is the single explicit timing field permitted by the
 * determinism standard (#74) — every other field is reproducible, so the
 * JSON projection is byte-stable for a given set of results.
 */
final readonly class Report
{
    /**
     * @param list<CheckResult> $checks
     */
    public function __construct(
        public array $checks,
        public int $durationMs,
    ) {}

    public function status(): CheckStatus
    {
        $worst = CheckStatus::Ok;
        foreach ($this->checks as $check) {
            if ($check->status->severity() > $worst->severity()) {
                $worst = $check->status;
            }
        }

        return $worst;
    }

    public function exitCode(): int
    {
        return $this->status()->severity();
    }

    /**
     * @return array{status: string, duration_ms: int, checks: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status()->value,
            'duration_ms' => $this->durationMs,
            'checks' => array_map(static fn(CheckResult $c): array => $c->toArray(), $this->checks),
        ];
    }
}
