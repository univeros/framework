<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Bootstrap\Profile;

use Altair\Bootstrap\Contracts\PresetInterface;
use Override;

/**
 * Cycle ORM + Redis queue with the agent-spec and MCP tooling pre-wired.
 */
final class FullPreset implements PresetInterface
{
    #[Override]
    public function name(): string
    {
        return 'full';
    }

    #[Override]
    public function orm(): string
    {
        return 'cycle';
    }

    #[Override]
    public function queue(): string
    {
        return 'redis';
    }
}
