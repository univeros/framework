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
 * Skipped / incomplete / risky tests share the same shape — name plus
 * a short reason string. Kept distinct from {@see FailureRecord} so
 * agents can branch on intent (`failures[]` is actionable, this isn't).
 */
final readonly class SkippedRecord
{
    public function __construct(
        public string $test,
        public string $reason,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['test' => $this->test, 'reason' => $this->reason];
    }
}
