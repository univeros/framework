<?php

declare(strict_types=1);

namespace Altair\Tests\Doctor\Support;

use Altair\Doctor\Contracts\CheckInterface;
use Altair\Doctor\Result\CheckResult;
use Override;

/**
 * A check that returns a pre-baked result, recording whether it ran — used
 * to exercise the runner's filtering and dependency-skip logic.
 */
final class FakeCheck implements CheckInterface
{
    public bool $ran = false;

    /**
     * @param list<string> $dependsOn
     */
    public function __construct(
        private readonly string $name,
        private readonly CheckResult $result,
        private readonly array $dependsOn = [],
    ) {}

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function dependsOn(): array
    {
        return $this->dependsOn;
    }

    #[Override]
    public function run(): CheckResult
    {
        $this->ran = true;

        return $this->result;
    }
}
