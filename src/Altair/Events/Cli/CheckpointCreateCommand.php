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
use Altair\Events\Reader;
use Altair\Events\Storage\CheckpointStorage;

/**
 * `bin/altair events:checkpoint:create <name>` — name the current head of
 * the event stream so future `since-checkpoint` queries can answer "what
 * did I do after starting work on X?".
 */
#[Command(
    name: 'events:checkpoint:create',
    description: 'Create a named bookmark at the current head of the event stream.',
)]
final readonly class CheckpointCreateCommand
{
    public function __construct(
        private Reader $reader,
        private CheckpointStorage $storage,
    ) {}

    public function __invoke(
        #[Argument(description: 'Checkpoint name (alphanumeric, _, ., -, /).')]
        string $name,
    ): int {
        $head = null;
        foreach ($this->reader->tail(1) as $event) {
            $head = $event;
        }

        $eventId = $head->id ?? '';

        $this->storage->create($name, $eventId);

        echo $eventId === ''
            ? "Created checkpoint '{$name}' at the empty log.\n"
            : "Created checkpoint '{$name}' at event {$eventId}.\n";

        return 0;
    }
}
