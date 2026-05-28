<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Server;

use Altair\Mcp\Contracts\TransportInterface;

/**
 * Pumps messages between a streaming transport and the {@see Server}: read a
 * message, dispatch it, write the reply (if any), repeat until end-of-input.
 * Used by the stdio transport and the in-memory transport.
 */
final readonly class ServerRunner
{
    public function __construct(
        private Server $server,
        private TransportInterface $transport,
    ) {}

    public function run(): void
    {
        while (($raw = $this->transport->receive()) !== null) {
            $reply = $this->server->handle($raw);
            if ($reply !== null) {
                $this->transport->send($reply);
            }
        }

        $this->transport->close();
    }
}
