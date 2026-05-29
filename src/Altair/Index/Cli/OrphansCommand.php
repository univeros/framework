<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Index\Support\Json;
use Altair\Index\Support\ProjectIndex;
use Altair\Index\Support\ResolvesProjectIndex;

/**
 * `bin/altair index:orphans` — spec endpoints and entities that name a class
 * which is never declared in the indexed source (a spec not yet scaffolded, or
 * a class renamed out from under its spec). Exit 1 when any are found, so CI
 * can gate on spec/source consistency.
 */
#[Command(
    name: 'index:orphans',
    description: 'List spec endpoints/entities whose target class is never declared (broken references).',
)]
final readonly class OrphansCommand
{
    use ResolvesProjectIndex;

    public function __invoke(
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
        #[Option(description: 'Query the existing index without rebuilding first.', name: 'no-build')]
        bool $noBuild = false,
    ): int {
        $index = $this->readyIndex($noBuild);
        if (!$index instanceof ProjectIndex) {
            echo "No index found. Run `bin/altair index:build` first.\n";

            return 2;
        }

        $orphans = $index->orphans()->danglingSpecTargets();

        if ($format === 'json') {
            echo Json::encode(['count' => \count($orphans), 'orphans' => $orphans]);
        } elseif ($orphans === []) {
            echo "No dangling spec references found.\n";
        } else {
            $lines = [];
            foreach ($orphans as $orphan) {
                $lines[] = \sprintf('  %-14s %s  (%s)', $orphan['usage_kind'], $orphan['fqn'], $orphan['file']);
            }

            echo implode("\n", $lines) . "\n";
        }

        return $orphans === [] ? 0 : 1;
    }
}
