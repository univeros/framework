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
use Altair\Examples\Library\Exception\ExampleNotFoundException;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Exception\McpException;
use Override;

#[McpTool(
    name: 'framework__read_example',
    description: 'Return the full Markdown body and frontmatter of a single example by id (e.g. `http/basic-endpoint`).',
    inputSchema: __DIR__ . '/Schema/read-example-input.json',
    outputSchema: __DIR__ . '/Schema/object-output.json',
)]
final readonly class ReadExampleTool implements McpToolInterface
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
        $id = \is_string($input['id'] ?? null) ? $input['id'] : '';
        if ($id === '') {
            throw new McpException("'id' is required.");
        }

        try {
            $example = $this->repository->findById($id);
        } catch (ExampleNotFoundException $exampleNotFoundException) {
            throw new McpException($exampleNotFoundException->getMessage(), $exampleNotFoundException->getCode(), $exampleNotFoundException);
        }

        $payload = $example->toIndexEntry();
        $payload['body'] = $example->body;

        return $payload;
    }
}
