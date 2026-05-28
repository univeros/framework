<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Exception;

use RuntimeException;

/**
 * Base exception for the MCP server. All package-specific failures extend
 * this so callers can catch the whole surface with one type.
 */
class McpException extends RuntimeException {}
