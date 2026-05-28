<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Introspection;

use Altair\Container\Container;
use Altair\Introspection\Inspector\ContainerInspector;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Override;

#[McpTool(
    name: 'framework__container_inspect',
    description: 'List container bindings: aliases, shares, delegates and parameter definitions.',
    inputSchema: __DIR__ . '/../../Schema/container-inspect-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class ContainerInspectTool implements McpToolInterface
{
    public function __construct(private Container $container) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $sharedOnly = ($input['shared_only'] ?? false) === true;
        $filter = \is_string($input['filter'] ?? null) && $input['filter'] !== '' ? $input['filter'] : null;

        return (new ContainerInspector($this->container))->inspectAll($sharedOnly, $filter)->toArray();
    }
}
