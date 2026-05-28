<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Fixtures;

use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Contracts\ToolResolverInterface;
use Altair\Mcp\Exception\McpException;
use Override;

/**
 * Container-free tool resolver for tests: maps class-strings to instances.
 */
final readonly class MapToolResolver implements ToolResolverInterface
{
    /**
     * @param array<class-string, McpToolInterface> $tools
     */
    public function __construct(private array $tools = [])
    {
    }

    #[Override]
    public function resolve(string $className): McpToolInterface
    {
        return $this->tools[$className]
            ?? throw new McpException(\sprintf("No test tool bound for '%s'.", $className));
    }
}
