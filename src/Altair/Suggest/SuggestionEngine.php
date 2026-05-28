<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest;

use Altair\Suggest\Result\Severity;
use Altair\Suggest\Result\Suggestion;
use Altair\Suggest\Result\SuggestionReport;
use Altair\Suggest\Snapshot\Snapshot;

/**
 * Runs the registered rules over a {@see Snapshot} and assembles a
 * {@see SuggestionReport}.
 *
 * Pure given its inputs (the snapshot is built elsewhere), so it is timed
 * only for the analysis itself. Findings below the `$minimum` severity floor
 * are dropped, then the survivors are sorted deterministically: highest
 * severity first, then by rule name, then by subject — so two runs over the
 * same snapshot emit byte-identical output.
 */
final readonly class SuggestionEngine
{
    public function __construct(
        private RuleRegistry $registry,
    ) {}

    /**
     * @param list<string> $only rule names to run exclusively
     * @param list<string> $skip rule names to exclude
     */
    public function analyse(
        Snapshot $snapshot,
        Severity $minimum = Severity::Info,
        array $only = [],
        array $skip = [],
    ): SuggestionReport {
        $startedAt = hrtime(true);
        $found = [];

        foreach ($this->registry->all() as $rule) {
            $name = $rule->name();

            if ($only !== [] && !\in_array($name, $only, true)) {
                continue;
            }

            if (\in_array($name, $skip, true)) {
                continue;
            }

            foreach ($rule->analyse($snapshot) as $suggestion) {
                if ($suggestion->severity->rank() >= $minimum->rank()) {
                    $found[] = $suggestion;
                }
            }
        }

        usort($found, $this->order(...));

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        return new SuggestionReport($found, max(0, $durationMs));
    }

    private function order(Suggestion $a, Suggestion $b): int
    {
        return [$b->severity->rank(), $a->rule, $a->subject, $a->message]
            <=> [$a->severity->rank(), $b->rule, $b->subject, $b->message];
    }
}
