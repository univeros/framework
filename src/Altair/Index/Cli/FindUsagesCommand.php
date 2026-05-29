<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Index\Model\Usage;
use Altair\Index\Support\Json;
use Altair\Index\Support\ProjectIndex;
use Altair\Index\Support\ResolvesProjectIndex;
use Altair\Index\Support\View;

/**
 * `bin/altair index:find-usages "App\\User\\User"` — every recorded reference
 * to a class, interface, method (`Class::method`), property (`Class::$prop`),
 * or constant (`Class::CONST`).
 */
#[Command(
    name: 'index:find-usages',
    description: 'List every recorded usage of a symbol (class, method, property, or constant).',
)]
final readonly class FindUsagesCommand
{
    use ResolvesProjectIndex;

    public function __invoke(
        #[Argument(description: 'Fully-qualified symbol: a class, "Class::method", "Class::$prop", or "Class::CONST".')]
        string $symbol,
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

        $usages = $index->usages()->usages($symbol);

        echo $format === 'json'
            ? Json::encode([
                'symbol' => $symbol,
                'count' => \count($usages),
                'usages' => array_map(static fn(Usage $u): array => $u->toArray(), $usages),
            ])
            : View::usageLines($usages, \sprintf('No usages of %s.', $symbol));

        return 0;
    }
}
