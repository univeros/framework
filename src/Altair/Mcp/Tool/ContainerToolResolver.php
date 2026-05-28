<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool;

use Altair\Container\Container;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Contracts\ToolResolverInterface;
use Altair\Mcp\Exception\McpException;
use Override;

/**
 * Resolves tool instances through the framework Container, so a tool's
 * constructor dependencies are autowired exactly like a CLI command's.
 */
final readonly class ContainerToolResolver implements ToolResolverInterface
{
    public function __construct(private Container $container) {}

    #[Override]
    public function resolve(string $className): McpToolInterface
    {
        $tool = $this->container->make($className);
        if (!$tool instanceof McpToolInterface) {
            throw new McpException(\sprintf('%s did not resolve to an %s.', $className, McpToolInterface::class));
        }

        return $tool;
    }
}
