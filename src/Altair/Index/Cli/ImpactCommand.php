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

/**
 * `bin/altair index:impact "App\\User\\User,App\\User\\Email"` — the aggregate
 * blast radius of changing one or more symbols: how many files, tests, and
 * specs reference them (and which), so an agent can run only the affected tests
 * before declaring a refactor done.
 */
#[Command(
    name: 'index:impact',
    description: 'Report the files, tests, and specs affected by changing a comma-separated set of symbols.',
)]
final readonly class ImpactCommand
{
    use ResolvesProjectIndex;

    public function __invoke(
        #[Argument(description: 'Comma-separated fully-qualified symbols to assess.')]
        string $symbols,
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

        $report = $index->impact()->impact($this->csv($symbols));

        echo $format === 'json' ? Json::encode($report->toArray()) : $this->human($report->toArray());

        return 0;
    }

    /**
     * @return list<string>
     */
    private function csv(string $value): array
    {
        return array_values(array_filter(
            array_map(trim(...), explode(',', $value)),
            static fn(string $item): bool => $item !== '',
        ));
    }

    /**
     * @param array{impact: array{files: int, tests: int, specs: int}, tests_to_run: list<string>, specs_affected: list<string>} $report
     */
    private function human(array $report): string
    {
        $lines = [\sprintf(
            'Impact: %d files, %d tests, %d specs.',
            $report['impact']['files'],
            $report['impact']['tests'],
            $report['impact']['specs'],
        )];

        if ($report['tests_to_run'] !== []) {
            $lines[] = 'Tests to run:';
            foreach ($report['tests_to_run'] as $test) {
                $lines[] = '  ' . $test;
            }
        }

        if ($report['specs_affected'] !== []) {
            $lines[] = 'Specs affected:';
            foreach ($report['specs_affected'] as $spec) {
                $lines[] = '  ' . $spec;
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
