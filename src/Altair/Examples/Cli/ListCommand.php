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
use Altair\Examples\Library\Contracts\ExampleRepositoryInterface;
use Altair\Examples\Library\Example;

/**
 * `bin/altair examples:list` — list the library, optionally filtered by package.
 */
#[Command(
    name: 'examples:list',
    description: 'List every example in the library (optionally filtered by package).',
)]
final readonly class ListCommand
{
    public function __construct(
        private ExampleRepositoryInterface $repository,
    ) {}

    public function __invoke(
        #[Option(description: 'Filter to examples that list this package in their packages frontmatter.')]
        ?string $package = null,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        $examples = $package !== null
            ? $this->repository->findByPackage($package)
            : $this->repository->findAll();

        if ($format === 'json') {
            $payload = [
                'count' => \count($examples),
                'examples' => array_map(static fn(Example $e): array => $e->toIndexEntry(), $examples),
            ];
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";

            return 0;
        }

        if ($examples === []) {
            echo $package === null
                ? "No examples found.\n"
                : "No examples for package '{$package}'.\n";

            return 0;
        }

        printf("%-40s %-25s %s\n", 'ID', 'PACKAGES', 'TITLE');
        printf("%-40s %-25s %s\n", str_repeat('-', 40), str_repeat('-', 25), str_repeat('-', 40));
        foreach ($examples as $example) {
            printf(
                "%-40s %-25s %s\n",
                $example->id,
                implode(',', $example->packages),
                $example->title,
            );
        }

        return 0;
    }
}
