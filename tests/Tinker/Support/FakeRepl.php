<?php

declare(strict_types=1);

namespace Altair\Tests\Tinker\Support;

use Altair\Tinker\Contracts\ReplInterface;
use Altair\Tinker\Repl\ReplContext;
use Override;

/**
 * A non-interactive REPL stand-in: records what it was asked to run and returns
 * a canned exit code, so the command is testable without blocking on stdin.
 */
final class FakeRepl implements ReplInterface
{
    public ?ReplContext $ranContext = null;

    public ?string $ranBanner = null;

    public function __construct(
        private readonly bool $available = true,
        private readonly int $exitCode = 0,
    ) {}

    #[Override]
    public function isAvailable(): bool
    {
        return $this->available;
    }

    #[Override]
    public function run(ReplContext $context, string $banner): int
    {
        $this->ranContext = $context;
        $this->ranBanner = $banner;

        return $this->exitCode;
    }
}
