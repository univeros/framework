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
 * One failed (or errored) test, mapped back to the production source
 * the agent should look at first.
 *
 * `sourceUnderTest` is a list because a single test may cover multiple
 * classes (`#[CoversClass]` accepts repetition). Most entries will have
 * one source location.
 *
 * @phpstan-type DiffPayload array<string, mixed>
 */
final readonly class FailureRecord
{
    /**
     * @param list<SourceLocation> $sourceUnderTest
     * @param list<StackFrame>     $stackTrace
     * @param DiffPayload|null     $diff
     */
    public function __construct(
        public string $test,
        public string $testFile,
        public ?int $testLine,
        public string $type,
        public string $message,
        public ?string $expected,
        public ?string $actual,
        public ?array $diff,
        public array $sourceUnderTest,
        public array $stackTrace,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'test' => $this->test,
            'test_file' => $this->testFile,
            'test_line' => $this->testLine,
            'type' => $this->type,
            'message' => $this->message,
            'expected' => $this->expected,
            'actual' => $this->actual,
            'diff' => $this->diff,
            'source_under_test' => array_map(static fn(SourceLocation $s): array => $s->toArray(), $this->sourceUnderTest),
            'stack_trace' => array_map(static fn(StackFrame $f): array => $f->toArray(), $this->stackTrace),
        ];
    }
}
