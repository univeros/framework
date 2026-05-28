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
use Altair\Happen\Contracts\EventDispatcherInterface;
use Altair\Happen\EventDispatcher;
use Altair\Introspection\Inspector\ListenerInspector;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Support\ContainerLookup;
use Override;

#[McpTool(
    name: 'framework__listeners_list',
    description: 'List event listeners registered on the Happen dispatcher, by event name.',
    inputSchema: __DIR__ . '/../../Schema/no-args.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class ListenersListTool implements McpToolInterface
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
        $dispatcher = ContainerLookup::firstOf($this->container, EventDispatcher::class, EventDispatcherInterface::class);
        if (!$dispatcher instanceof EventDispatcher) {
            return ['available' => false, 'note' => 'No Happen EventDispatcher is bound; this project registers no listeners.'];
        }

        return (new ListenerInspector($dispatcher))->inspectAll()->toArray();
    }
}
