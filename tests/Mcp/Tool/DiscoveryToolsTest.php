<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Tool;

use Altair\Container\Container;
use Altair\Mcp\Exception\GuardrailException;
use Altair\Mcp\Exception\McpException;
use Altair\Mcp\Guard\PathGuard;
use Altair\Mcp\Support\ProjectContext;
use Altair\Mcp\Tool\Discovery\ContainerResolveTool;
use Altair\Mcp\Tool\Discovery\DescribeEndpointTool;
use Altair\Mcp\Tool\Discovery\DescribePackageTool;
use Altair\Mcp\Tool\Discovery\ListCommandsTool;
use Altair\Mcp\Tool\Discovery\ListEndpointsTool;
use Altair\Mcp\Tool\Discovery\ListPackagesTool;
use Altair\Mcp\Tool\Discovery\ListSpecsTool;
use Altair\Mcp\Tool\Discovery\ReadSpecTool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListPackagesTool::class)]
#[CoversClass(DescribePackageTool::class)]
#[CoversClass(ListSpecsTool::class)]
#[CoversClass(ReadSpecTool::class)]
#[CoversClass(ListEndpointsTool::class)]
#[CoversClass(DescribeEndpointTool::class)]
#[CoversClass(ContainerResolveTool::class)]
#[CoversClass(ListCommandsTool::class)]
final class DiscoveryToolsTest extends TestCase
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

    private ProjectContext $context;

    #[Override]
    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . '/mcp-disco-' . bin2hex(random_bytes(4));
        mkdir($this->tempRoot . '/api/users', 0o755, true);
        file_put_contents($this->tempRoot . '/api/users/create.yaml', self::SAMPLE_SPEC);

        // Real framework src so package/command scans see actual packages.
        $this->context = new ProjectContext($this->tempRoot, ProjectContext::detect()->altairSrcDir);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeDir($this->tempRoot);
    }

    public function testListPackagesIncludesKnownPackages(): void
    {
        $result = (new ListPackagesTool($this->context))->call([]);

        $names = array_column($result['packages'], 'name');
        self::assertContains('univeros/mcp', $names);
        self::assertContains('univeros/scaffold', $names);
        self::assertGreaterThan(15, $result['count']);
    }

    public function testDescribePackageReturnsManifestAndContracts(): void
    {
        $result = (new DescribePackageTool($this->context))->call(['package' => 'scaffold']);

        self::assertSame('univeros/scaffold', $result['name']);
        self::assertSame('Scaffold', $result['directory']);
        self::assertNotEmpty($result['classes']);
    }

    public function testDescribePackageAcceptsFullComposerName(): void
    {
        $result = (new DescribePackageTool($this->context))->call(['package' => 'univeros/events']);

        self::assertSame('univeros/events', $result['name']);
    }

    public function testDescribeUnknownPackageThrows(): void
    {
        $this->expectException(McpException::class);
        (new DescribePackageTool($this->context))->call(['package' => 'nope']);
    }

    public function testListSpecsFindsTheTempSpec(): void
    {
        $result = (new ListSpecsTool($this->context))->call([]);

        self::assertArrayHasKey('rows', $result);
        self::assertNotEmpty($result['rows']);
    }

    public function testListSpecsNotesMissingApiDir(): void
    {
        $empty = new ProjectContext($this->tempRoot . '/nope', $this->context->altairSrcDir);

        self::assertSame(0, (new ListSpecsTool($empty))->call([])['count']);
    }

    public function testReadSpecReturnsRawAndParsed(): void
    {
        $result = (new ReadSpecTool($this->context, new PathGuard($this->tempRoot)))->call(['path' => 'api/users/create.yaml']);

        self::assertStringContainsString('method: POST', $result['raw']);
        self::assertSame('POST', $result['parsed']['endpoint']['method']);
    }

    public function testReadSpecMissingFileThrows(): void
    {
        $this->expectException(McpException::class);
        (new ReadSpecTool($this->context, new PathGuard($this->tempRoot)))->call(['path' => 'api/missing.yaml']);
    }

    public function testReadSpecRejectsPathTraversal(): void
    {
        $this->expectException(GuardrailException::class);
        (new ReadSpecTool($this->context, new PathGuard($this->tempRoot)))->call(['path' => '../../../../etc/passwd']);
    }

    public function testReadSpecRejectsAbsolutePathOutsideRoot(): void
    {
        $this->expectException(GuardrailException::class);
        (new ReadSpecTool($this->context, new PathGuard($this->tempRoot)))->call(['path' => '/etc/hosts']);
    }

    public function testListEndpointsReturnsTheEndpoint(): void
    {
        $result = (new ListEndpointsTool($this->context))->call([]);

        self::assertSame(1, $result['count']);
        self::assertSame('POST', $result['endpoints'][0]['method']);
        self::assertSame('/users', $result['endpoints'][0]['path']);
    }

    public function testDescribeEndpointReturnsPlannedFiles(): void
    {
        $result = (new DescribeEndpointTool($this->context, new PathGuard($this->tempRoot)))->call(['spec_path' => 'api/users/create.yaml']);

        self::assertSame('POST', $result['endpoint']['method']);
        self::assertNotEmpty($result['planned_files']);
        self::assertIsArray($result['drift']);
    }

    public function testListCommandsIncludesScaffoldCommand(): void
    {
        $result = (new ListCommandsTool($this->context))->call([]);

        $names = array_column($result['commands'], 'name');
        self::assertContains('spec:scaffold', $names);
    }

    public function testContainerResolveDescribesABinding(): void
    {
        $container = new Container();
        $container->instance($container::class, $container);

        $result = (new ContainerResolveTool($container))->call(['interface' => Container::class]);

        self::assertArrayHasKey('rows', $result);
    }

    public function testContainerResolveUnknownReportsNotFound(): void
    {
        $result = (new ContainerResolveTool(new Container()))->call(['interface' => 'App\\Nope']);

        self::assertFalse($result['found']);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
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
