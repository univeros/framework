<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Guard;

/**
 * The server's mutation policy, set once at `mcp serve` startup.
 *
 * - `readonly` turns the whole server inspect-only: no file writes, no DB writes.
 * - `allowWrites` is the explicit opt-in required before destructive DB tools
 *   (migrations) will run. File-generating tools (scaffold, write_spec) only
 *   need the server to not be readonly.
 */
final readonly class ServerMode
{
    public function __construct(
        public bool $readonly = false,
        public bool $allowWrites = false,
    ) {}

    public function allowsFileMutation(): bool
    {
        return !$this->readonly;
    }

    public function allowsDbWrites(): bool
    {
        return !$this->readonly && $this->allowWrites;
    }
}
