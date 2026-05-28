<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Fixtures;

use Altair\Doctor\Contracts\ProcessRunnerInterface;
use Altair\Doctor\Process\ProcessResult;
use Override;

/**
 * Canned process runner for verification-tool tests — records the last command
 * and returns a pre-set result instead of shelling out.
 */
final class FakeProcessRunner implements ProcessRunnerInterface
{
    /**
     * @var list<string>|null
     */
    public ?array $lastCommand = null;

    public ?string $lastCwd = null;

    public function __construct(private readonly ProcessResult $result)
    {
    }

    /**
     * @param list<string> $command
     */
    #[Override]
    public function run(array $command, ?string $cwd = null): ProcessResult
    {
        $this->lastCommand = $command;
        $this->lastCwd = $cwd;

        return $this->result;
    }
}
