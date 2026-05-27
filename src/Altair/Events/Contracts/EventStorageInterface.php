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
 * Backing store for the append-only event log.
 *
 * Implementations MUST guarantee that {@see append()} is atomic against
 * concurrent writers (file lock, atomic rename, transaction — whatever
 * the medium permits) and that {@see readAll()} skips lines that fail
 * to deserialise rather than aborting the iteration.
 *
 * @phpstan-type RawLine array{ raw: string, line: int }
 */
interface EventStorageInterface
{
    public function append(Event $event): void;

    /**
     * Iterate events oldest → newest.
     *
     * @return iterable<int, Event>
     */
    public function readAll(): iterable;

    /**
     * Iterate events newest → oldest. Implementations are free to load the
     * full file into memory for this — the log is meant to be compacted
     * before it gets gigantic.
     *
     * @return iterable<int, Event>
     */
    public function readReverse(): iterable;

    /**
     * Total event count (cheap line count, not a full parse).
     */
    public function count(): int;
}
