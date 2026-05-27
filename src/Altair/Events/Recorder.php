<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events;

use Altair\Events\Contracts\EventStorageInterface;
use Altair\Events\Contracts\RecorderInterface;
use Override;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Default RecorderInterface implementation.
 *
 * Pipes the event through the {@see Scrubber} (so credentials don't reach
 * disk), then hands it to the {@see EventStorageInterface}. Any failure
 * from the storage layer is swallowed and logged at warning level — the
 * event log is observability, never load-bearing.
 */
final readonly class Recorder implements RecorderInterface
{
    public function __construct(
        private EventStorageInterface $storage,
        private Scrubber $scrubber = new Scrubber(),
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    #[Override]
    public function record(Event $event): void
    {
        $scrubbed = new Event(
            id: $event->id,
            timestamp: $event->timestamp,
            actor: $event->actor,
            command: $this->scrubber->scrub($event->command),
            kind: $event->kind,
            status: $event->status,
            durationMs: $event->durationMs,
            user: $event->user,
            client: $event->client,
            changes: $event->changes,
            error: $event->error,
            extra: $event->extra,
        );

        try {
            $this->storage->append($scrubbed);
        } catch (Throwable $e) {
            $this->logger->warning('Event recorder failed: {message}', [
                'message' => $e->getMessage(),
                'event_id' => $event->id,
                'kind' => $event->kind->value,
            ]);
        }
    }
}
