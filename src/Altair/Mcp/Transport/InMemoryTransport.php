<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Transport;

use Altair\Mcp\Contracts\TransportInterface;
use Override;

/**
 * In-memory transport for tests and embedded use: queued inbound messages, an
 * outbound buffer you can assert against. Lets a full agent-style session run
 * without touching stdio or a socket.
 */
final class InMemoryTransport implements TransportInterface
{
    /**
     * @var list<string>
     */
    private array $inbox;

    /**
     * @var list<string>
     */
    private array $outbox = [];

    private bool $closed = false;

    /**
     * @param list<string> $inbox
     */
    public function __construct(array $inbox = [])
    {
        $this->inbox = array_values($inbox);
    }

    public function push(string $message): void
    {
        $this->inbox[] = $message;
    }

    #[Override]
    public function receive(): ?string
    {
        if ($this->closed || $this->inbox === []) {
            return null;
        }

        return array_shift($this->inbox);
    }

    #[Override]
    public function send(string $message): void
    {
        $this->outbox[] = $message;
    }

    #[Override]
    public function close(): void
    {
        $this->closed = true;
    }

    /**
     * @return list<string>
     */
    public function sent(): array
    {
        return $this->outbox;
    }
}
