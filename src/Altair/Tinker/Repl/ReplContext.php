<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tinker\Repl;

/**
 * The inputs a REPL session needs: the variables put in scope (notably
 * `$container`) and where/how command history is persisted.
 *
 * Immutable — `withScopeVariable()` returns a copy so the Configuration can
 * layer host-specific variables onto a base context.
 */
final readonly class ReplContext
{
    public const string DEFAULT_HISTORY_FILE = '.altair/tinker_history';

    /**
     * @param array<string, mixed> $scopeVariables
     */
    public function __construct(
        public array $scopeVariables = [],
        public ?string $historyFile = self::DEFAULT_HISTORY_FILE,
        public int $historySize = 0,
    ) {}

    public function withScopeVariable(string $name, mixed $value): self
    {
        return new self(
            scopeVariables: [...$this->scopeVariables, $name => $value],
            historyFile: $this->historyFile,
            historySize: $this->historySize,
        );
    }

    /**
     * @return list<string>
     */
    public function scopeVariableNames(): array
    {
        return array_keys($this->scopeVariables);
    }
}
