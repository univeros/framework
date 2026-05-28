<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Transport;

use Altair\Mcp\Transport\InMemoryTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryTransport::class)]
final class InMemoryTransportTest extends TestCase
{
    public function testReceivesQueuedMessagesInOrderThenNull(): void
    {
        $transport = new InMemoryTransport(['a', 'b']);
        $transport->push('c');

        self::assertSame('a', $transport->receive());
        self::assertSame('b', $transport->receive());
        self::assertSame('c', $transport->receive());
        self::assertNull($transport->receive());
    }

    public function testSendBuffersOutbound(): void
    {
        $transport = new InMemoryTransport();
        $transport->send('one');
        $transport->send('two');

        self::assertSame(['one', 'two'], $transport->sent());
    }

    public function testCloseStopsReceiving(): void
    {
        $transport = new InMemoryTransport(['a']);
        $transport->close();

        self::assertNull($transport->receive());
    }
}
