<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events\Contracts;

use Altair\Events\Event;

/**
 * Best-effort sink for mutating-command events.
 *
 * Implementations MUST NOT throw: if recording fails (read-only fs, disk
 * full, race), the event drops silently and the calling command keeps
 * its return value. Events are observability, not load-bearing logic.
 */
interface RecorderInterface
{
    public function record(Event $event): void;
}
