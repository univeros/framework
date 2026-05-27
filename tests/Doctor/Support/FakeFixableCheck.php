<?php

declare(strict_types=1);

namespace Altair\Tests\Doctor\Support;

use Altair\Doctor\Contracts\FixableCheckInterface;
use Altair\Doctor\Result\CheckResult;
use Override;

/**
 * A fixable check: returns `$before` until `fix()` succeeds, then `$after`.
 * Lets the runner's --fix path (fix, then re-run) be asserted.
 */
final class FakeFixableCheck implements FixableCheckInterface
{
    public int $runCount = 0;

    public int $fixCount = 0;

    private bool $fixed = false;

    public function __construct(
        private readonly string $name,
        private readonly CheckResult $before,
        private readonly CheckResult $after,
        private readonly bool $fixSucceeds = true,
    ) {}

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function dependsOn(): array
    {
        return [];
    }

    #[Override]
    public function run(): CheckResult
    {
        ++$this->runCount;

        return $this->fixed ? $this->after : $this->before;
    }

    #[Override]
    public function fix(): bool
    {
        ++$this->fixCount;
        $this->fixed = $this->fixSucceeds;

        return $this->fixSucceeds;
    }
}
