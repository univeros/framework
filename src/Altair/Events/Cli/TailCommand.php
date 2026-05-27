<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Events\Reader;

/**
 * `bin/altair events:tail` — print the last N events, newest first.
 */
#[Command(
    name: 'events:tail',
    description: 'Print the most recent events from .altair/events.jsonl.',
)]
final readonly class TailCommand
{
    public function __construct(
        private Reader $reader,
        private OutputRenderer $renderer = new OutputRenderer(),
    ) {}

    public function __invoke(
        #[Option(description: 'Number of events to print (newest first).', short: 'n')]
        int $n = 20,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        $events = iterator_to_array($this->reader->tail($n), false);

        if ($format === 'json') {
            foreach ($events as $event) {
                echo $this->renderer->eventJson($event), "\n";
            }

            return 0;
        }

        if ($events === []) {
            echo "No events recorded yet.\n";

            return 0;
        }

        // Print oldest-first in human mode so the eye scrolls naturally.
        foreach (array_reverse($events) as $event) {
            echo $this->renderer->eventLineHuman($event), "\n";
        }

        return 0;
    }
}
