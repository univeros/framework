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
use Altair\Events\Storage\CheckpointStorage;

/**
 * `bin/altair events:checkpoint:delete <name>` — remove a checkpoint.
 */
#[Command(
    name: 'events:checkpoint:delete',
    description: 'Delete a stored checkpoint.',
)]
final readonly class CheckpointDeleteCommand
{
    public function __construct(
        private CheckpointStorage $storage,
    ) {}

    public function __invoke(
        #[Argument(description: 'Checkpoint name to delete.')]
        string $name,
    ): int {
        if (!$this->storage->exists($name)) {
            echo "Checkpoint '{$name}' does not exist.\n";

            return 1;
        }

        if (!$this->storage->delete($name)) {
            echo "Failed to delete checkpoint '{$name}'.\n";

            return 1;
        }

        echo "Deleted checkpoint '{$name}'.\n";

        return 0;
    }
}
