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
use Altair\Http\Collection\RouteCollection;
use Altair\Introspection\Exception\NotFoundException;
use Altair\Introspection\Inspector\RouteInspector;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Support\ContainerLookup;
use Override;

#[McpTool(
    name: 'framework__route_show',
    description: 'Show the registrations for one route path (across methods).',
    inputSchema: __DIR__ . '/../../Schema/route-show-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class RouteShowTool implements McpToolInterface
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
        $routes = ContainerLookup::optional($this->container, RouteCollection::class);
        if (!$routes instanceof RouteCollection) {
            return ['available' => false, 'note' => 'No RouteCollection is bound; this project registers no HTTP routes.'];
        }

        $path = \is_string($input['path'] ?? null) ? $input['path'] : '';

        try {
            return (new RouteInspector($routes))->inspectOne($path)->toArray();
        } catch (NotFoundException) {
            return ['found' => false, 'path' => $path];
        }
    }
}
