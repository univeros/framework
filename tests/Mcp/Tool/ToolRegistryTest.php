<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Tool;

use Altair\Mcp\Exception\McpException;
use Altair\Mcp\Tool\ToolDescriptor;
use Altair\Mcp\Tool\ToolRegistry;
use Altair\Tests\Mcp\Fixtures\EchoTool;
use Altair\Tests\Mcp\Fixtures\FailingTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ToolRegistry::class)]
#[CoversClass(ToolDescriptor::class)]
final class ToolRegistryTest extends TestCase
{
    public function testRegisterAndGet(): void
    {
        $registry = new ToolRegistry();
        $descriptor = new ToolDescriptor('a__tool', 'desc', EchoTool::class);
        $registry->register($descriptor);

        self::assertTrue($registry->has('a__tool'));
        self::assertSame($descriptor, $registry->get('a__tool'));
        self::assertSame(1, $registry->count());
    }

    public function testDuplicateNameThrows(): void
    {
        $registry = new ToolRegistry();
        $registry->register(new ToolDescriptor('dup', 'a', EchoTool::class));

        $this->expectException(McpException::class);
        $registry->register(new ToolDescriptor('dup', 'b', FailingTool::class));
    }

    public function testGetUnknownThrows(): void
    {
        $this->expectException(McpException::class);
        (new ToolRegistry())->get('nope');
    }

    public function testListingIsNameSorted(): void
    {
        $registry = new ToolRegistry();
        $registry->register(new ToolDescriptor('zeta', 'z', EchoTool::class));
        $registry->register(new ToolDescriptor('alpha', 'a', FailingTool::class));

        self::assertSame(['alpha', 'zeta'], array_column($registry->listing(), 'name'));
    }

    public function testListEntryDefaultsInputSchemaAndOmitsNullOutputSchema(): void
    {
        $entry = (new ToolDescriptor('t', 'd', EchoTool::class))->toListEntry();

        self::assertSame(['type' => 'object'], $entry['inputSchema']);
        self::assertArrayNotHasKey('outputSchema', $entry);
    }

    public function testListEntryIncludesProvidedSchemas(): void
    {
        $entry = (new ToolDescriptor(
            't',
            'd',
            EchoTool::class,
            inputSchema: ['type' => 'object', 'required' => ['x']],
            outputSchema: ['type' => 'object'],
        ))->toListEntry();

        self::assertContains('x', $entry['inputSchema']['required']);
        self::assertSame(['type' => 'object'], $entry['outputSchema']);
    }
}
