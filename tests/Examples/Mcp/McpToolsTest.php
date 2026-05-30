<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Examples\Mcp;

use Altair\Examples\Library\ExampleRepository;
use Altair\Examples\Mcp\ListExamplesTool;
use Altair\Examples\Mcp\ReadExampleTool;
use Altair\Examples\Mcp\SearchExamplesTool;
use Altair\Mcp\Exception\McpException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListExamplesTool::class)]
#[CoversClass(ReadExampleTool::class)]
#[CoversClass(SearchExamplesTool::class)]
final class McpToolsTest extends TestCase
{
    private string $root;
    private ExampleRepository $repository;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/altair-examples-mcp-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/http', recursive: true);
        mkdir($this->root . '/persistence', recursive: true);

        file_put_contents($this->root . '/http/basic-endpoint.md', $this->stub(
            'Basic endpoint', 'The smallest viable endpoint', ['http'], 'Body of the basic endpoint.',
        ));
        file_put_contents($this->root . '/persistence/crud-repository.md', $this->stub(
            'CRUD repository', 'Define a repository', ['persistence'], 'CRUD body.',
        ));

        $this->repository = new ExampleRepository($this->root);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testListExamplesReturnsEverything(): void
    {
        $tool = new ListExamplesTool($this->repository);

        $result = $tool->call([]);

        self::assertSame(2, $result['count']);
        self::assertNull($result['package']);
        self::assertSame('http/basic-endpoint', $result['examples'][0]['id']);
        self::assertArrayNotHasKey('body', $result['examples'][0], 'list does not return bodies — use read_example');
    }

    public function testListExamplesFiltersByPackage(): void
    {
        $tool = new ListExamplesTool($this->repository);

        $result = $tool->call(['package' => 'http']);

        self::assertSame(1, $result['count']);
        self::assertSame('http', $result['package']);
        self::assertSame('http/basic-endpoint', $result['examples'][0]['id']);
    }

    public function testReadExampleReturnsFullBody(): void
    {
        $tool = new ReadExampleTool($this->repository);

        $result = $tool->call(['id' => 'http/basic-endpoint']);

        self::assertSame('http/basic-endpoint', $result['id']);
        self::assertSame('Basic endpoint', $result['title']);
        self::assertSame(['http'], $result['packages']);
        self::assertStringContainsString('Body of the basic endpoint.', $result['body']);
    }

    public function testReadExampleRequiresId(): void
    {
        $tool = new ReadExampleTool($this->repository);

        $this->expectException(McpException::class);
        $this->expectExceptionMessage("'id' is required");

        $tool->call([]);
    }

    public function testReadExampleThrowsOnMissingId(): void
    {
        $tool = new ReadExampleTool($this->repository);

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('No example with id "http/does-not-exist"');

        $tool->call(['id' => 'http/does-not-exist']);
    }

    public function testSearchExamplesReturnsMatches(): void
    {
        $tool = new SearchExamplesTool($this->repository);

        $result = $tool->call(['query' => 'CRUD']);

        self::assertSame('CRUD', $result['query']);
        self::assertSame(1, $result['count']);
        self::assertSame('persistence/crud-repository', $result['examples'][0]['id']);
    }

    public function testSearchExamplesRequiresNonBlankQuery(): void
    {
        $tool = new SearchExamplesTool($this->repository);

        $this->expectException(McpException::class);
        $this->expectExceptionMessage("'query' is required");

        $tool->call(['query' => '   ']);
    }

    /**
     * @param list<string> $packages
     */
    private function stub(string $title, string $scenario, array $packages, string $body): string
    {
        $packagesYaml = '[' . implode(', ', $packages) . ']';

        return <<<MD
        ---
        title: {$title}
        scenario: {$scenario}
        packages: {$packagesYaml}
        since: 2.0.0
        tested_by: tests/Examples/Stub.php
        ---
        {$body}
        MD;
    }

    private function rmrf(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($full) ? $this->rmrf($full) : @unlink($full);
        }
        @rmdir($path);
    }
}
