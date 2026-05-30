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
use Override;

#[McpTool(
    name: 'framework__list_examples',
    description: "List every idiomatic example in the project's `.altair/examples/` library. "
        . 'Pass `package` to narrow to one package; omit it for the whole catalogue.',
    inputSchema: __DIR__ . '/Schema/list-examples-input.json',
    outputSchema: __DIR__ . '/Schema/object-output.json',
)]
final readonly class ListExamplesTool implements McpToolInterface
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
        $package = isset($input['package']) && \is_string($input['package']) && $input['package'] !== ''
            ? $input['package']
            : null;

        $examples = $package !== null
            ? $this->repository->findByPackage($package)
            : $this->repository->findAll();

        return [
            'count' => \count($examples),
            'package' => $package,
            'examples' => array_map(static fn(Example $e): array => $e->toIndexEntry(), $examples),
        ];
    }
}
