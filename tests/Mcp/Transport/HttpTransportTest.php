<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Transport;

use Altair\Mcp\Schema\SchemaValidator;
use Altair\Mcp\Server\Server;
use Altair\Mcp\Server\ServerInfo;
use Altair\Mcp\Tool\ToolRegistry;
use Altair\Mcp\Transport\HttpTransport;
use Altair\Tests\Mcp\Fixtures\MapToolResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HttpTransport::class)]
final class HttpTransportTest extends TestCase
{
    private function http(): HttpTransport
    {
        $server = new Server(new ToolRegistry(), new MapToolResolver(), new SchemaValidator(), new ServerInfo());

        return new HttpTransport($server);
    }

    private function post(string $body): string
    {
        return "POST / HTTP/1.1\r\nHost: localhost\r\nContent-Type: application/json\r\nContent-Length: "
            . \strlen($body) . "\r\n\r\n" . $body;
    }

    public function testPostReturnsJsonRpcReply(): void
    {
        $body = (string) json_encode(['jsonrpc' => '2.0', 'id' => 42, 'method' => 'ping']);
        $response = $this->http()->respond($this->post($body));

        self::assertStringStartsWith('HTTP/1.1 200 OK', $response);
        self::assertStringContainsString('application/json', $response);

        [, $payload] = explode("\r\n\r\n", $response, 2);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($payload, true);
        self::assertSame(42, $decoded['id']);
    }

    public function testNotificationReturns202WithEmptyBody(): void
    {
        $body = (string) json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);
        $response = $this->http()->respond($this->post($body));

        self::assertStringStartsWith('HTTP/1.1 202 Accepted', $response);
        self::assertStringEndsWith("\r\n\r\n", $response);
    }

    public function testNonPostReturns405(): void
    {
        $response = $this->http()->respond("GET / HTTP/1.1\r\nHost: localhost\r\n\r\n");

        self::assertStringStartsWith('HTTP/1.1 405', $response);
    }
}
