<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Contracts;

/**
 * A narrow, render-agnostic view over a messaging failure transport.
 *
 * Observatory must read failed jobs without coupling to Symfony Messenger or
 * requiring a live broker, so it depends on this framework-owned seam instead.
 * The host adapts its real failure transport (a Symfony
 * `ListableReceiverInterface`, a database table, ...) behind this interface;
 * tests provide a trivial in-memory fake.
 *
 * Configured-but-empty (zero failures) and not-configured (no transports) are
 * distinct states, so callers can render "all clear" differently from "no
 * queues wired".
 */
interface FailedQueueReaderInterface
{
    /**
     * Names of the configured transports (e.g. ["default", "high"]).
     *
     * Empty when messaging is not configured, which the panel surfaces as an
     * Unknown status rather than a healthy one.
     *
     * @return list<string>
     */
    public function transportNames(): array;

    /**
     * Total number of envelopes currently held in the failure transport.
     */
    public function failedCount(): int;

    /**
     * The most recent failed envelopes, newest first, flattened to scalar rows.
     *
     * Every row is best-effort: any of the keys may be absent when the backing
     * store can't supply them, so the panel must not assume their presence.
     *
     * @param  int                              $limit maximum rows to return
     * @return list<array<string, scalar|null>> rows shaped like
     *                                          {id?, message_class?, error?, transport?}
     */
    public function recentFailures(int $limit = 25): array;
}
