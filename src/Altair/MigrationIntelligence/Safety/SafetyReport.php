<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Safety;

/**
 * The aggregate result of running every safety check over a plan's intents.
 *
 * `skipped` means the checks could not run (no database configured, or the
 * database was unreachable) — distinct from "ran and found nothing".
 */
final readonly class SafetyReport
{
    /**
     * @param list<SafetyFinding> $findings
     */
    public function __construct(
        public array $findings = [],
        public bool $skipped = false,
        public ?string $skipReason = null,
    ) {}

    public static function skipped(string $reason): self
    {
        return new self(skipped: true, skipReason: $reason);
    }

    public function hasErrors(): bool
    {
        return $this->countAtLeast(Severity::Error) > 0;
    }

    public function hasWarnings(): bool
    {
        return $this->countAtLeast(Severity::Warn) > 0;
    }

    /**
     * @return list<SafetyFinding>
     */
    public function errors(): array
    {
        return array_values(array_filter(
            $this->findings,
            static fn(SafetyFinding $finding): bool => $finding->severity === Severity::Error,
        ));
    }

    /**
     * @return list<array{severity: string, message: string, check: string}>
     */
    public function toArray(): array
    {
        return array_map(static fn(SafetyFinding $finding): array => $finding->toArray(), $this->findings);
    }

    private function countAtLeast(Severity $severity): int
    {
        $rank = [Severity::Info->value => 0, Severity::Warn->value => 1, Severity::Error->value => 2];

        return \count(array_filter(
            $this->findings,
            static fn(SafetyFinding $finding): bool => $rank[$finding->severity->value] >= $rank[$severity->value],
        ));
    }
}
