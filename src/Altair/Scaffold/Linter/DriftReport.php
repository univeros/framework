<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Linter;

final readonly class DriftReport
{
    /**
     * @param list<DriftFinding> $findings
     */
    public function __construct(public array $findings) {}

    public function hasDrift(): bool
    {
        return $this->findings !== [];
    }

    public function with(DriftFinding $finding): self
    {
        return new self([...$this->findings, $finding]);
    }

    /**
     * @param list<DriftFinding> $additional
     */
    public function withMany(array $additional): self
    {
        return new self([...$this->findings, ...$additional]);
    }
}
