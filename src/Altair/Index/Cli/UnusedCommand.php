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
use Altair\Index\Model\Symbol;
use Altair\Index\Support\Json;
use Altair\Index\Support\ProjectIndex;
use Altair\Index\Support\ResolvesProjectIndex;
use Altair\Index\Support\View;

/**
 * `bin/altair index:unused` — symbols with zero recorded references.
 *
 * Dead-code *candidates*: framework entry points (an Action's `__invoke`, a
 * route handler) are reached by dispatch the AST can't see, so review before
 * deleting. Informational by default; pass `--strict` to exit non-zero when any
 * candidate is found (a CI gate).
 */
#[Command(
    name: 'index:unused',
    description: 'List symbols with zero recorded usages (dead-code candidates).',
)]
final readonly class UnusedCommand
{
    use ResolvesProjectIndex;

    public function __invoke(
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
        #[Option(description: 'Exit non-zero when any dead-code candidate is found.')]
        bool $strict = false,
        #[Option(description: 'Query the existing index without rebuilding first.', name: 'no-build')]
        bool $noBuild = false,
    ): int {
        $index = $this->readyIndex($noBuild);
        if (!$index instanceof ProjectIndex) {
            echo "No index found. Run `bin/altair index:build` first.\n";

            return 2;
        }

        $unused = $index->usages()->unused();

        echo $format === 'json'
            ? Json::encode([
                'count' => \count($unused),
                'symbols' => array_map(static fn(Symbol $s): array => $s->toArray(), $unused),
            ])
            : View::symbolLines($unused, 'No dead-code candidates found.');

        return $strict && $unused !== [] ? 1 : 0;
    }
}
