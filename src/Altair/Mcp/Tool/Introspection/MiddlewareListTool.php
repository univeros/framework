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
use Altair\Http\Collection\MiddlewareCollection;
use Altair\Introspection\Inspector\PipelineInspector;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Support\ContainerLookup;
use Override;

#[McpTool(
    name: 'framework__middleware_list',
    description: 'List the middleware pipeline in dispatch order.',
    inputSchema: __DIR__ . '/../../Schema/no-args.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class MiddlewareListTool implements McpToolInterface
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
        $queue = ContainerLookup::optional($this->container, MiddlewareCollection::class);
        if (!$queue instanceof MiddlewareCollection) {
            return ['available' => false, 'note' => 'No MiddlewareCollection is bound; this project registers no middleware.'];
        }

        return (new PipelineInspector($queue))->inspectAll()->toArray();
    }
}
