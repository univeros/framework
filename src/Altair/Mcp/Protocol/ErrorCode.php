<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Protocol;

/**
 * JSON-RPC 2.0 reserved error codes (MCP rides on JSON-RPC, so these are the
 * codes surfaced for protocol-level failures). Tool-execution failures are NOT
 * errors at this layer — they come back as a successful `tools/call` result
 * with `isError: true`.
 */
enum ErrorCode: int
{
    case ParseError = -32700;
    case InvalidRequest = -32600;
    case MethodNotFound = -32601;
    case InvalidParams = -32602;
    case InternalError = -32603;
}
