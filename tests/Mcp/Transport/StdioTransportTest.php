<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Transport;

use Altair\Mcp\Transport\StdioTransport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StdioTransport::class)]
final class StdioTransportTest extends TestCase
{
    public function testReceivesLinesSkippingBlanksThenNull(): void
    {
        $in = fopen('php://temp', 'r+');
        self::assertNotFalse($in);
        fwrite($in, "first\n\nsecond\n");
        rewind($in);

        $out = fopen('php://temp', 'r+');
        self::assertNotFalse($out);

        $transport = new StdioTransport($in, $out);

        self::assertSame('first', $transport->receive());
        self::assertSame('second', $transport->receive());
        self::assertNull($transport->receive());
    }

    public function testSendAppendsNewline(): void
    {
        $in = fopen('php://temp', 'r+');
        self::assertNotFalse($in);
        $out = fopen('php://temp', 'r+');
        self::assertNotFalse($out);

        (new StdioTransport($in, $out))->send('{"a":1}');

        rewind($out);
        self::assertSame("{\"a\":1}\n", stream_get_contents($out));
    }
}
