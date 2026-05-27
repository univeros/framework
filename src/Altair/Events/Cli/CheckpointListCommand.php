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
use Altair\Events\Storage\CheckpointStorage;

/**
 * `bin/altair events:checkpoint:list` — show every stored checkpoint.
 */
#[Command(
    name: 'events:checkpoint:list',
    description: 'List all stored checkpoints.',
)]
final readonly class CheckpointListCommand
{
    public function __construct(
        private CheckpointStorage $storage,
    ) {}

    public function __invoke(
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        $names = $this->storage->list();
        $items = [];
        foreach ($names as $name) {
            $items[] = $this->storage->read($name);
        }

        if ($format === 'json') {
            echo json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";

            return 0;
        }

        if ($items === []) {
            echo "No checkpoints stored.\n";

            return 0;
        }

        foreach ($items as $item) {
            echo \sprintf(
                "%-30s  %s  %s\n",
                $item['name'],
                $item['created_at'],
                $item['event_id'] === '' ? '(empty log)' : $item['event_id'],
            );
        }

        return 0;
    }
}
