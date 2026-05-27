<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor\Result;

/**
 * The machine-actionable "do this next" block on a failing check.
 *
 * This is the differentiator over human-shaped error text: instead of an
 * agent guessing what to do, the check states the exact tool call — run a
 * command, edit a file, install a dependency.
 */
final readonly class AgentAction
{
    /**
     * @param array<string, string> $payload
     */
    private function __construct(
        public string $type,
        public array $payload,
    ) {}

    public static function runCommand(string $command): self
    {
        return new self('run_command', ['command' => $command]);
    }

    public static function editFile(string $file, string $hint): self
    {
        return new self('edit_file', ['file' => $file, 'hint' => $hint]);
    }

    public static function installDep(string $package): self
    {
        return new self('install_dep', ['package' => $package]);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['type' => $this->type, ...$this->payload];
    }
}
