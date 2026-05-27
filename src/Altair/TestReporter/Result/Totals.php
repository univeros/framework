<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\TestReporter\Result;

/**
 * Aggregate counts for the top-level `totals` block of the report.
 *
 * Mutable so subscribers can increment in place — the `ResultCollector`
 * is the single owner so the mutation is bounded to one component.
 */
final class Totals
{
    public int $tests = 0;

    public int $assertions = 0;

    public int $passed = 0;

    public int $failed = 0;

    public int $errored = 0;

    public int $skipped = 0;

    public int $warnings = 0;

    public int $risky = 0;

    public int $incomplete = 0;

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'tests' => $this->tests,
            'assertions' => $this->assertions,
            'passed' => $this->passed,
            'failed' => $this->failed,
            'errored' => $this->errored,
            'skipped' => $this->skipped,
            'warnings' => $this->warnings,
            'risky' => $this->risky,
            'incomplete' => $this->incomplete,
        ];
    }
}
