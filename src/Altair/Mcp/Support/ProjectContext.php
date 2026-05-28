<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Support;

use const DIRECTORY_SEPARATOR;

/**
 * Where the MCP server is operating: the host project root (the cwd of
 * `mcp serve`, where `api/`, `docs/`, `bin/altair` live) and the Altair
 * source directory (the framework's `src/Altair`, found relative to this file
 * so package discovery works whether running from the framework repo or a
 * consuming app's vendor tree).
 */
final readonly class ProjectContext
{
    public function __construct(
        public string $projectRoot,
        public string $altairSrcDir,
    ) {}

    public static function detect(?string $projectRoot = null): self
    {
        $root = $projectRoot !== null && $projectRoot !== ''
            ? $projectRoot
            : (getcwd() ?: '.');

        // __DIR__ = src/Altair/Mcp/Support → two levels up = src/Altair.
        $altairSrc = \dirname(__DIR__, 2);

        return new self(rtrim($root, '/\\'), $altairSrc);
    }

    public function path(string ...$parts): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts);
    }
}
