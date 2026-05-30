<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Examples\Mcp;

use Altair\Examples\Library\Contracts\ExampleRepositoryInterface;
use Altair\Examples\Library\Example;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Exception\McpException;
use Override;

#[McpTool(
    name: 'framework__search_examples',
    description: "Free-text substring search (case-insensitive) across every example's id, title, scenario, and body.",
    inputSchema: __DIR__ . '/Schema/search-examples-input.json',
    outputSchema: __DIR__ . '/Schema/object-output.json',
)]
final readonly class SearchExamplesTool implements McpToolInterface
{
    public function __construct(
        private ExampleRepositoryInterface $repository,
    ) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $query = \is_string($input['query'] ?? null) ? $input['query'] : '';
        if (trim($query) === '') {
            throw new McpException("'query' is required.");
        }

        $matches = $this->repository->search($query);

        return [
            'query' => $query,
            'count' => \count($matches),
            'examples' => array_map(static fn(Example $e): array => $e->toIndexEntry(), $matches),
        ];
    }
}
