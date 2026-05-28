<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp;

use Altair\Container\Container;
use Altair\Mcp\Configuration\McpConfiguration;
use Altair\Mcp\Server\Server;
use Altair\Mcp\Server\ServerRunner;
use Altair\Mcp\Tool\BuiltinTools;
use Altair\Mcp\Transport\InMemoryTransport;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end: build the server from the real Container via McpConfiguration and
 * drive a full agent-style session over the in-memory transport. Proves tools
 * autowire their dependencies from the container and the protocol round-trips.
 */
#[CoversClass(McpConfiguration::class)]
final class McpServerIntegrationTest extends TestCase
{
    private const string SAMPLE_SPEC = <<<'YAML'
        endpoint:
          method: POST
          path: /users
          summary: Create a new user
          tags: [users]
        input:
          email:
            type: string
            rules: [email, required]
        output:
          201:
            body:
              user: App\User\User
        domain:
          class: App\User\CreateUser
          invocation: __invoke
        YAML;

    private string $tempRoot;

    #[Override]
    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . '/mcp-int-' . bin2hex(random_bytes(4));
        mkdir($this->tempRoot . '/api/users', 0o755, true);
        file_put_contents($this->tempRoot . '/api/users/create.yaml', self::SAMPLE_SPEC);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeDir($this->tempRoot);
    }

    private function server(): Server
    {
        $container = new Container();
        (new McpConfiguration(projectRoot: $this->tempRoot))->apply($container);

        $server = $container->make(Server::class);
        self::assertInstanceOf(Server::class, $server);

        return $server;
    }

    public function testFullAgentSession(): void
    {
        $transport = new InMemoryTransport([
            (string) json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-06-18']]),
            (string) json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']),
            (string) json_encode(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']),
            (string) json_encode(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'framework__list_packages', 'arguments' => []]]),
            (string) json_encode(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'framework__scaffold', 'arguments' => ['spec_path' => 'api/users/create.yaml', 'dry_run' => true]]]),
        ]);

        (new ServerRunner($this->server(), $transport))->run();

        $replies = array_map(
            static fn(string $raw): array => (array) json_decode($raw, true),
            $transport->sent(),
        );

        // initialize + tools/list + 2 tools/call = 4 replies; the notification gets none.
        self::assertCount(4, $replies);

        // initialize
        self::assertSame('2025-06-18', $replies[0]['result']['protocolVersion']);

        // tools/list advertises the full v1 palette.
        $tools = array_column($replies[1]['result']['tools'], 'name');
        self::assertCount(\count(BuiltinTools::classes()), $tools);
        self::assertContains('framework__scaffold', $tools);
        self::assertContains('framework__list_packages', $tools);

        // list_packages resolved from the container and ran.
        self::assertFalse($replies[2]['result']['isError']);
        self::assertGreaterThan(15, $replies[2]['result']['structuredContent']['count']);

        // scaffold dry-run planned files without writing.
        self::assertFalse($replies[3]['result']['isError']);
        self::assertTrue($replies[3]['result']['structuredContent']['dry_run']);
        self::assertNotEmpty($replies[3]['result']['structuredContent']['emitted']);
        self::assertFileDoesNotExist($this->tempRoot . '/app/Http/Actions/CreateUserAction.php');
    }

    public function testToolsListAdvertisesAtLeastTwentyTools(): void
    {
        $raw = $this->server()->handle((string) json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']));
        self::assertIsString($raw);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($raw, true);
        self::assertGreaterThanOrEqual(20, \count($decoded['result']['tools']));

        // every tool publishes an input schema
        foreach ($decoded['result']['tools'] as $tool) {
            self::assertArrayHasKey('inputSchema', $tool);
            self::assertArrayHasKey('outputSchema', $tool);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.') {
                continue;
            }

            if ($item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
