<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Server;

use Altair\Mcp\Protocol\ErrorCode;
use Altair\Mcp\Schema\SchemaValidator;
use Altair\Mcp\Server\Server;
use Altair\Mcp\Server\ServerInfo;
use Altair\Mcp\Tool\AttributeToolDiscoverer;
use Altair\Mcp\Tool\ToolRegistry;
use Altair\Tests\Mcp\Fixtures\EchoTool;
use Altair\Tests\Mcp\Fixtures\FailingTool;
use Altair\Tests\Mcp\Fixtures\MapToolResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Server::class)]
#[CoversClass(ServerInfo::class)]
final class ServerTest extends TestCase
{
    private function server(): Server
    {
        $registry = new ToolRegistry();
        foreach ((new AttributeToolDiscoverer())->fromClasses([EchoTool::class, FailingTool::class]) as $descriptor) {
            $registry->register($descriptor);
        }

        $resolver = new MapToolResolver([
            EchoTool::class => new EchoTool(),
            FailingTool::class => new FailingTool(),
        ]);

        return new Server($registry, $resolver, new SchemaValidator(), new ServerInfo());
    }

    /**
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>
     */
    private function call(array $message): array
    {
        $raw = $this->server()->handle((string) json_encode($message));
        self::assertIsString($raw);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true);

        return $decoded;
    }

    public function testInitializeReturnsProtocolCapabilitiesAndServerInfo(): void
    {
        $response = $this->call(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18']]);

        self::assertSame(1, $response['id']);
        self::assertSame('2025-06-18', $response['result']['protocolVersion']);
        self::assertArrayHasKey('tools', $response['result']['capabilities']);
        self::assertSame(ServerInfo::DEFAULT_NAME, $response['result']['serverInfo']['name']);
    }

    public function testInitializeFallsBackToLatestForUnknownProtocol(): void
    {
        $response = $this->call(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '1999-01-01']]);

        self::assertSame(ServerInfo::SUPPORTED_PROTOCOLS[0], $response['result']['protocolVersion']);
    }

    public function testPingReturnsEmptyResult(): void
    {
        $response = $this->call(['jsonrpc' => '2.0', 'id' => 7, 'method' => 'ping']);

        self::assertSame(7, $response['id']);
        self::assertSame([], $response['result']);
    }

    public function testToolsListReturnsRegisteredToolsSortedByName(): void
    {
        $response = $this->call(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']);

        $names = array_column($response['result']['tools'], 'name');
        self::assertSame(['test__echo', 'test__failing'], $names);

        $echo = $response['result']['tools'][0];
        self::assertSame('object', $echo['inputSchema']['type']);
        self::assertContains('message', $echo['inputSchema']['required']);
    }

    public function testToolsCallReturnsStructuredAndTextContent(): void
    {
        $response = $this->call([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'test__echo', 'arguments' => ['message' => 'hi']],
        ]);

        self::assertFalse($response['result']['isError']);
        self::assertSame('hi', $response['result']['structuredContent']['echo']);
        self::assertSame('text', $response['result']['content'][0]['type']);
        self::assertStringContainsString('hi', $response['result']['content'][0]['text']);
    }

    public function testToolsCallWithInvalidInputReturnsToolError(): void
    {
        $response = $this->call([
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => ['name' => 'test__echo', 'arguments' => ['wrong' => 'field']],
        ]);

        self::assertTrue($response['result']['isError']);
        self::assertStringContainsString('Invalid input', $response['result']['content'][0]['text']);
    }

    public function testToolsCallWithUnknownToolReturnsMethodNotFound(): void
    {
        $response = $this->call([
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => ['name' => 'does__not_exist', 'arguments' => []],
        ]);

        self::assertSame(ErrorCode::MethodNotFound->value, $response['error']['code']);
    }

    public function testToolsCallWithoutNameReturnsInvalidParams(): void
    {
        $response = $this->call(['jsonrpc' => '2.0', 'id' => 6, 'method' => 'tools/call', 'params' => []]);

        self::assertSame(ErrorCode::InvalidParams->value, $response['error']['code']);
    }

    public function testThrowingToolBecomesToolErrorNotProtocolError(): void
    {
        $response = $this->call([
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'tools/call',
            'params' => ['name' => 'test__failing', 'arguments' => []],
        ]);

        self::assertArrayNotHasKey('error', $response);
        self::assertTrue($response['result']['isError']);
        self::assertStringContainsString('boom', $response['result']['content'][0]['text']);
    }

    public function testGuardrailViolationBecomesToolError(): void
    {
        $response = $this->call([
            'jsonrpc' => '2.0',
            'id' => 9,
            'method' => 'tools/call',
            'params' => ['name' => 'test__failing', 'arguments' => ['guardrail' => true]],
        ]);

        self::assertTrue($response['result']['isError']);
        self::assertStringContainsString('vendor', $response['result']['content'][0]['text']);
    }

    public function testUnknownMethodReturnsMethodNotFound(): void
    {
        $response = $this->call(['jsonrpc' => '2.0', 'id' => 10, 'method' => 'no/such/method']);

        self::assertSame(ErrorCode::MethodNotFound->value, $response['error']['code']);
    }

    public function testParseErrorOnInvalidJson(): void
    {
        $raw = $this->server()->handle('{ not json');
        self::assertIsString($raw);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true);
        self::assertNull($decoded['id']);
        self::assertSame(ErrorCode::ParseError->value, $decoded['error']['code']);
    }

    public function testInitializedNotificationProducesNoReply(): void
    {
        self::assertNull($this->server()->handle((string) json_encode([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ])));
    }

    public function testUnknownNotificationProducesNoReply(): void
    {
        self::assertNull($this->server()->handle((string) json_encode([
            'jsonrpc' => '2.0',
            'method' => 'some/unknown/notification',
        ])));
    }
}
