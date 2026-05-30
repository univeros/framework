<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Examples\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Examples\Library\Contracts\ExampleRepositoryInterface;
use Altair\Examples\Library\Example;

/**
 * `bin/altair examples:search <query>` — free-text substring search across
 * id, title, scenario and body.
 */
#[Command(
    name: 'examples:search',
    description: 'Free-text substring search across the example library.',
)]
final readonly class SearchCommand
{
    public function __construct(
        private ExampleRepositoryInterface $repository,
    ) {}

    public function __invoke(
        #[Argument(description: 'Substring to search for (case-insensitive).')]
        string $query,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        $results = $this->repository->search($query);

        if ($format === 'json') {
            $payload = [
                'query' => $query,
                'count' => \count($results),
                'examples' => array_map(static fn(Example $e): array => $e->toIndexEntry(), $results),
            ];
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";

            return 0;
        }

        if ($results === []) {
            echo "No examples matched '{$query}'.\n";

            return 0;
        }

        printf("%d match%s for '%s':\n\n", \count($results), \count($results) === 1 ? '' : 'es', $query);
        foreach ($results as $example) {
            echo $example->id . PHP_EOL;
            echo \sprintf('  %s%s', $example->title, PHP_EOL);
            echo "  {$example->scenario}\n\n";
        }

        return 0;
    }
}
