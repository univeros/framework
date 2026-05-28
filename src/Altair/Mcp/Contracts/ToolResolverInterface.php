<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Contracts;

/**
 * Turns a tool class-string into a ready-to-call instance.
 *
 * Production binds the Container-backed implementation (constructor deps are
 * autowired exactly like CLI commands); tests bind a trivial map so the
 * protocol layer can be exercised without a container.
 */
interface ToolResolverInterface
{
    /**
     * @param class-string $className
     */
    public function resolve(string $className): McpToolInterface;
}
