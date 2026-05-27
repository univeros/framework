<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\TestReporter\Result;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Root container for the JSON output. Built by the {@see
 * \Altair\TestReporter\ResultCollector} and serialised by the
 * {@see \Altair\TestReporter\Output\JsonWriter}.
 */
final readonly class TestReport
{
    public const string VERSION = '1.0';

    /**
     * @param list<FailureRecord> $failures
     * @param list<FailureRecord> $errors
     * @param list<SkippedRecord> $skipped
     * @param list<SkippedRecord> $risky
     * @param list<SkippedRecord> $incomplete
     */
    public function __construct(
        public DateTimeImmutable $startedAt,
        public int $durationMs,
        public string $phpVersion,
        public string $phpunitVersion,
        public Totals $totals,
        public ReportStatus $status,
        public array $failures,
        public array $errors,
        public array $skipped,
        public array $risky,
        public array $incomplete,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => self::VERSION,
            'started_at' => $this->startedAt->format(DateTimeInterface::RFC3339_EXTENDED),
            'duration_ms' => $this->durationMs,
            'php_version' => $this->phpVersion,
            'phpunit_version' => $this->phpunitVersion,
            'totals' => $this->totals->toArray(),
            'result' => $this->status->value,
            'failures' => array_map(static fn(FailureRecord $f): array => $f->toArray(), $this->failures),
            'errors' => array_map(static fn(FailureRecord $f): array => $f->toArray(), $this->errors),
            'skipped' => array_map(static fn(SkippedRecord $s): array => $s->toArray(), $this->skipped),
            'risky' => array_map(static fn(SkippedRecord $s): array => $s->toArray(), $this->risky),
            'incomplete' => array_map(static fn(SkippedRecord $s): array => $s->toArray(), $this->incomplete),
        ];
    }
}
