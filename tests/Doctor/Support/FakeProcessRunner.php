<?php

declare(strict_types=1);

namespace Altair\Tests\Doctor\Support;

use Altair\Doctor\Contracts\ProcessRunnerInterface;
use Altair\Doctor\Process\ProcessResult;
use Override;

/**
 * In-memory ProcessRunner: scripts results per command and records calls,
 * so process-backed checks are testable without shelling out.
 */
final class FakeProcessRunner implements ProcessRunnerInterface
{
    /** @var list<list<string>> */
    public array $calls = [];

    /** @var array<string, ProcessResult> */
    private array $scripted = [];

    private readonly ProcessResult $default;

    public function __construct(?ProcessResult $default = null)
    {
        $this->default = $default ?? new ProcessResult(0);
    }

    /**
     * @param list<string> $command
     */
    public function on(array $command, ProcessResult $result): void
    {
        $this->scripted[implode(' ', $command)] = $result;
    }

    /**
     * @param list<string> $command
     */
    #[Override]
    public function run(array $command, ?string $cwd = null): ProcessResult
    {
        $this->calls[] = $command;

        return $this->scripted[implode(' ', $command)] ?? $this->default;
    }
}
