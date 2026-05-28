<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Fixtures;

use Altair\Doctor\Contracts\CheckInterface;
use Altair\Doctor\Result\CheckResult;
use Override;

/**
 * Deterministic doctor check for DoctorTool tests.
 */
final readonly class FakeCheck implements CheckInterface
{
    public function __construct(
        private string $name = 'fake_check',
        private bool $ok = true,
    ) {}

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function dependsOn(): array
    {
        return [];
    }

    #[Override]
    public function run(): CheckResult
    {
        return $this->ok
            ? CheckResult::ok($this->name, 'all good')
            : CheckResult::error($this->name, 'broken');
    }
}
