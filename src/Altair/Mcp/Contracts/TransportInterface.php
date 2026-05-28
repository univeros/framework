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
 * A bidirectional message channel between the MCP server and a client.
 *
 * Messages are individual JSON-RPC payloads (already framed — line-delimited
 * for stdio). The server loop pulls one message at a time with {@see receive()}
 * and writes replies with {@see send()}.
 */
interface TransportInterface
{
    /**
     * Block for the next inbound message; return null when the channel is
     * closed / end-of-input is reached.
     */
    public function receive(): ?string;

    public function send(string $message): void;

    public function close(): void;
}
