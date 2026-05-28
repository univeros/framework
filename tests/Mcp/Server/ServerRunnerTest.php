<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Server;

use Altair\Mcp\Schema\SchemaValidator;
use Altair\Mcp\Server\Server;
use Altair\Mcp\Server\ServerInfo;
use Altair\Mcp\Server\ServerRunner;
use Altair\Mcp\Tool\AttributeToolDiscoverer;
use Altair\Mcp\Tool\ToolRegistry;
use Altair\Mcp\Transport\InMemoryTransport;
use Altair\Tests\Mcp\Fixtures\EchoTool;
use Altair\Tests\Mcp\Fixtures\MapToolResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerRunner::class)]
final class ServerRunnerTest extends TestCase
{
    public function testRunsAFullAgentStyleSession(): void
    {
        $registry = new ToolRegistry();
        foreach ((new AttributeToolDiscoverer())->fromClasses([EchoTool::class]) as $descriptor) {
            $registry->register($descriptor);
        }

        $server = new Server(
            $registry,
            new MapToolResolver([EchoTool::class => new EchoTool()]),
            new SchemaValidator(),
            new ServerInfo(),
        );

        $transport = new InMemoryTransport([
            (string) json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => []]),
            (string) json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']),
            (string) json_encode(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']),
            (string) json_encode(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'test__echo', 'arguments' => ['message' => 'hello']]]),
        ]);

        (new ServerRunner($server, $transport))->run();

        $sent = $transport->sent();
        // initialize + tools/list + tools/call get replies; the notification does not.
        self::assertCount(3, $sent);

        /** @var array<string, mixed> $call */
        $call = json_decode($sent[2], true);
        self::assertSame(3, $call['id']);
        self::assertSame('hello', $call['result']['structuredContent']['echo']);
    }
}
