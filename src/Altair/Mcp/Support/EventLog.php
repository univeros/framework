<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Support;

use Altair\Events\Actor;
use Altair\Events\Changes;
use Altair\Events\Contracts\RecorderInterface;
use Altair\Events\Event;
use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Throwable;

/**
 * Best-effort bridge from MCP tools to the `.altair/events.jsonl` mutation log.
 *
 * Every mutating tool records what it changed so the agent has a chronological
 * "what just happened?" trail. Recording never fails the tool: a missing or
 * broken recorder is silently ignored, exactly like the CLI commands do.
 */
final readonly class EventLog
{
    public function __construct(private ?RecorderInterface $recorder = null) {}

    public function record(
        EventKind $kind,
        EventStatus $status,
        string $command,
        int $durationMs = 0,
        ?string $error = null,
        ?Changes $changes = null,
    ): void {
        if (!$this->recorder instanceof RecorderInterface) {
            return;
        }

        try {
            $this->recorder->record(Event::create(
                actor: Actor::Mcp,
                command: $command,
                kind: $kind,
                status: $status,
                durationMs: $durationMs,
                changes: $changes,
                error: $error,
            ));
        } catch (Throwable) {
            // Event recording is best-effort.
        }
    }
}
