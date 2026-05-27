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
use Altair\Events\Reader;
use Altair\Events\Storage\CheckpointStorage;

/**
 * `bin/altair events:checkpoint:diff <name>` — every event recorded after
 * the named checkpoint, newest first.
 */
#[Command(
    name: 'events:checkpoint:diff',
    description: 'Print events recorded after the named checkpoint.',
)]
final readonly class CheckpointDiffCommand
{
    public function __construct(
        private Reader $reader,
        private CheckpointStorage $storage,
        private OutputRenderer $renderer = new OutputRenderer(),
    ) {}

    public function __invoke(
        #[Argument(description: 'Checkpoint name to diff against.')]
        string $name,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        if (!$this->storage->exists($name)) {
            echo "Checkpoint '{$name}' does not exist.\n";

            return 1;
        }

        $eventId = $this->storage->read($name)['event_id'];

        $any = false;
        foreach ($this->reader->sinceId($eventId) as $event) {
            $any = true;
            echo $format === 'json'
                ? $this->renderer->eventJson($event) . "\n"
                : $this->renderer->eventLineHuman($event) . "\n";
        }

        if (!$any && $format !== 'json') {
            echo "No events recorded since checkpoint '{$name}'.\n";
        }

        return 0;
    }
}
