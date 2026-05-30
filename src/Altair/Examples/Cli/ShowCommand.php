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
use Altair\Examples\Library\Exception\ExampleNotFoundException;

/**
 * `bin/altair examples:show <id>` — render a single example to stdout.
 */
#[Command(
    name: 'examples:show',
    description: 'Render a single example to stdout (human Markdown or JSON envelope).',
)]
final readonly class ShowCommand
{
    public function __construct(
        private ExampleRepositoryInterface $repository,
    ) {}

    public function __invoke(
        #[Argument(description: 'The example id (the path under the library root, no .md), e.g. http/basic-endpoint.')]
        string $id,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        try {
            $example = $this->repository->findById($id);
        } catch (ExampleNotFoundException $exception) {
            echo $exception->getMessage(), "\n";

            return 1;
        }

        if ($format === 'json') {
            $payload = $example->toIndexEntry();
            $payload['body'] = $example->body;
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";

            return 0;
        }

        echo "# {$example->title}\n\n";
        echo "> {$example->scenario}\n\n";
        echo "**Packages:** " . implode(', ', $example->packages) . "\n";
        echo "**Since:** {$example->since}\n";
        echo "**Tested by:** {$example->testedBy}\n\n";
        echo "---\n\n";
        echo $example->body;
        if (!str_ends_with($example->body, "\n")) {
            echo "\n";
        }

        return 0;
    }
}
