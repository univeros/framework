<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Discovery;

use Altair\Container\Container;
use Altair\Introspection\Exception\NotFoundException;
use Altair\Introspection\Inspector\ContainerInspector;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Override;

#[McpTool(
    name: 'framework__container_resolve',
    description: 'Show what an interface or class is bound to in the container.',
    inputSchema: __DIR__ . '/../../Schema/container-resolve-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class ContainerResolveTool implements McpToolInterface
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
        $interface = \is_string($input['interface'] ?? null) ? $input['interface'] : '';

        try {
            return (new ContainerInspector($this->container))->inspectOne($interface)->toArray();
        } catch (NotFoundException) {
            return ['interface' => $interface, 'found' => false];
        }
    }
}
