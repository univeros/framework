<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Result;

/**
 * The full result of a suggest run: the (already filtered and sorted)
 * suggestions plus the wall-clock analysis duration.
 *
 * `durationMs` is the single explicit timing field permitted by the
 * determinism standard (#74); every other field is reproducible, so the
 * JSON projection is byte-stable for a given snapshot and rule set.
 */
final readonly class SuggestionReport
{
    /**
     * @param list<Suggestion> $suggestions
     */
    public function __construct(
        public array $suggestions,
        public int $durationMs,
    ) {}

    /**
     * Exit code for CI gating: `1` when any warning is present, else `0`.
     * Advisory `info` findings never fail a build.
     */
    public function exitCode(): int
    {
        foreach ($this->suggestions as $suggestion) {
            if ($suggestion->severity === Severity::Warning) {
                return 1;
            }
        }

        return 0;
    }

    public function countBy(Severity $severity): int
    {
        return \count(array_filter(
            $this->suggestions,
            static fn(Suggestion $s): bool => $s->severity === $severity,
        ));
    }

    /**
     * @return array{count: int, duration_ms: int, suggestions: list<array<string, string>>}
     */
    public function toArray(): array
    {
        return [
            'count' => \count($this->suggestions),
            'duration_ms' => $this->durationMs,
            'suggestions' => array_map(
                static fn(Suggestion $s): array => $s->toArray(),
                $this->suggestions,
            ),
        ];
    }
}
