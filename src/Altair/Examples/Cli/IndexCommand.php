<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Examples\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Examples\Configuration\ExamplesSettings;
use Altair\Examples\Library\IndexBuilder;

/**
 * `bin/altair examples:index` — regenerate `.altair/examples/index.json`.
 *
 * Pure write step — agents and CI use this to catch drift between content and
 * the published index. `--check` exits non-zero on drift without writing.
 */
#[Command(
    name: 'examples:index',
    description: 'Regenerate (or drift-check) the deterministic examples index.json.',
)]
final readonly class IndexCommand
{
    public function __construct(
        private IndexBuilder $builder,
        private ExamplesSettings $settings,
    ) {}

    public function __invoke(
        #[Option(description: 'Drift-gate mode: do not write; exit 1 if the index differs from what would be written.')]
        bool $check = false,
    ): int {
        $expected = $this->builder->build();
        $path = $this->settings->indexPath();

        if ($check) {
            if (!is_file($path)) {
                echo "Index missing at '{$path}'. Run 'bin/altair examples:index' to write it.\n";

                return 1;
            }

            $actual = (string) file_get_contents($path);
            if ($actual !== $expected) {
                echo "Index at '{$path}' is out of date. Run 'bin/altair examples:index' to refresh it.\n";

                return 1;
            }

            echo "Index up to date.\n";

            return 0;
        }

        $this->builder->writeTo($path);
        echo "Wrote {$path}\n";

        return 0;
    }
}
