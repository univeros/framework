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
 * No ORM, no queue, synchronous handling — the smallest runnable project.
 */
final class MinimalPreset implements PresetInterface
{
    #[Override]
    public function name(): string
    {
        return 'minimal';
    }

    #[Override]
    public function orm(): string
    {
        return 'none';
    }

    #[Override]
    public function queue(): string
    {
        return 'sync';
    }
}
