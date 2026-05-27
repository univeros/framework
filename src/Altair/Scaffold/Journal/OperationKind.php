<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Journal;

/**
 * Operation kinds recorded in the journal.
 *
 * Aligned with {@see \Altair\Events\EventKind} so an event-log dual-write
 * stays a 1:1 mapping (scaffold → Scaffold, rewind → Rewind, replay →
 * Replay).
 */
enum OperationKind: string
{
    case Scaffold = 'scaffold';
    case Rewind = 'rewind';
    case Replay = 'replay';
}
