<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Events\Event;
use Altair\Events\Reader;
use Altair\Events\Storage\SnapshotStorage;

/**
 * `bin/altair events:show <id>` — full detail for one event, with its
 * snapshot payload if one was attached.
 */
#[Command(
    name: 'events:show',
    description: 'Show full detail for one event by id.',
)]
final readonly class ShowCommand
{
    public function __construct(
        private Reader $reader,
        private SnapshotStorage $snapshots,
        private OutputRenderer $renderer = new OutputRenderer(),
    ) {}

    public function __invoke(
        #[Argument(description: 'The event id (ULID) to show.')]
        string $id,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        $event = $this->reader->findById($id);
        if (!$event instanceof Event) {
            echo "Event '{$id}' not found.\n";

            return 1;
        }

        $snapshot = $this->snapshots->read($id);

        if ($format === 'json') {
            $payload = $event->toArray();
            if ($snapshot !== null) {
                $payload['snapshot'] = $snapshot;
            }

            echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";

            return 0;
        }

        echo $this->renderer->eventDetailHuman($event);
        if ($snapshot !== null) {
            $encoded = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($encoded !== false) {
                echo "snapshot:\n";
                echo "  " . str_replace("\n", "\n  ", $encoded), "\n";
            }
        }

        return 0;
    }
}
