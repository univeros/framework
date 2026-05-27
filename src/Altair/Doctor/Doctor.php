<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor;

use Altair\Doctor\Contracts\CheckInterface;
use Altair\Doctor\Contracts\FixableCheckInterface;
use Altair\Doctor\Result\CheckResult;
use Altair\Doctor\Result\CheckStatus;
use Altair\Doctor\Result\Report;

/**
 * Runs the registered checks and assembles a {@see Report}.
 *
 * Execution rules:
 * - `--only` / `--skip` filter which checks run (by name).
 * - dependency-aware: a check whose prerequisite errored or was skipped is
 *   reported `skipped` instead of running (no point testing downstream when
 *   the foundation is broken).
 * - `--fix`: for a non-ok {@see FixableCheckInterface}, attempt the fix then
 *   re-run the check so the report reflects the post-fix state.
 */
final readonly class Doctor
{
    public function __construct(
        private CheckRegistry $registry,
    ) {}

    /**
     * @param list<string> $only
     * @param list<string> $skip
     */
    public function run(array $only = [], array $skip = [], bool $fix = false): Report
    {
        $startedAt = hrtime(true);
        $results = [];

        /** @var array<string, CheckStatus> $statusByName */
        $statusByName = [];

        foreach ($this->registry->all() as $check) {
            $name = $check->name();

            if ($only !== [] && !\in_array($name, $only, true)) {
                continue;
            }

            if (\in_array($name, $skip, true)) {
                continue;
            }

            $blockedBy = $this->unmetPrerequisite($check, $statusByName);
            if ($blockedBy !== null) {
                $result = CheckResult::skipped(
                    $name,
                    \sprintf("Skipped — prerequisite '%s' did not pass.", $blockedBy),
                );
            } else {
                $result = $check->run();
                if ($fix && $result->status->severity() > 0 && $check instanceof FixableCheckInterface && $check->fix()) {
                    $result = $check->run();
                }
            }

            $statusByName[$name] = $result->status;
            $results[] = $result;
        }

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        return new Report($results, max(0, $durationMs));
    }

    /**
     * @param array<string, CheckStatus> $statusByName
     */
    private function unmetPrerequisite(CheckInterface $check, array $statusByName): ?string
    {
        foreach ($check->dependsOn() as $dependency) {
            $status = $statusByName[$dependency] ?? null;
            if ($status === CheckStatus::Error || $status === CheckStatus::Skipped) {
                return $dependency;
            }
        }

        return null;
    }
}
