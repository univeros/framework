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
use Altair\Index\Support\Json;
use Altair\Index\Support\ProjectIndex;
use Altair\Index\Support\ResolvesProjectIndex;
use Altair\Index\Support\View;

/**
 * `bin/altair index:implements "Altair\\Http\\Contracts\\MiddlewareInterface"`
 * — the classes that declare `implements <interface>`.
 */
#[Command(
    name: 'index:implements',
    description: 'List the classes that implement an interface.',
)]
final readonly class ImplementsCommand
{
    use ResolvesProjectIndex;

    public function __invoke(
        #[Argument(description: 'Fully-qualified interface name.')]
        string $interface,
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

        $implementers = $index->usages()->implementers($interface);

        echo $format === 'json'
            ? Json::encode(['interface' => $interface, 'count' => \count($implementers), 'implementers' => $implementers])
            : View::nameLines($implementers, \sprintf('No classes implement %s.', $interface));

        return 0;
    }
}
