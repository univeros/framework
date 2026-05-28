<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Tool;

use Altair\Mcp\Tool\ToolDescriptor;
use Altair\Mcp\Exception\McpException;
use Altair\Mcp\Tool\AttributeToolDiscoverer;
use Altair\Tests\Mcp\Fixtures\EchoTool;
use Altair\Tests\Mcp\Fixtures\FailingTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(AttributeToolDiscoverer::class)]
final class AttributeToolDiscovererTest extends TestCase
{
    public function testDescribeReadsAttributeAndLoadsSchema(): void
    {
        $descriptor = (new AttributeToolDiscoverer())->describe(EchoTool::class);

        self::assertNotNull($descriptor);
        self::assertSame('test__echo', $descriptor->name);
        self::assertSame(EchoTool::class, $descriptor->className);
        self::assertIsArray($descriptor->inputSchema);
        self::assertContains('message', $descriptor->inputSchema['required']);
    }

    public function testDescribeReturnsNullForClassWithoutAttribute(): void
    {
        self::assertNull((new AttributeToolDiscoverer())->describe(stdClass::class));
    }

    public function testFromClassesSkipsNonToolClasses(): void
    {
        $descriptors = (new AttributeToolDiscoverer())->fromClasses([EchoTool::class, stdClass::class, FailingTool::class]);

        self::assertCount(2, $descriptors);
        self::assertSame(['test__echo', 'test__failing'], array_map(static fn(ToolDescriptor $d): string => $d->name, $descriptors));
    }

    public function testDescribeRejectsAttributeWithoutInterface(): void
    {
        $this->expectException(McpException::class);
        (new AttributeToolDiscoverer())->describe(NotATool::class);
    }

    public function testDiscoverClassesScansDirectoryForToolClasses(): void
    {
        $classes = (new AttributeToolDiscoverer())->discoverClasses([__DIR__ . '/../Fixtures']);

        self::assertContains(EchoTool::class, $classes);
        self::assertContains(FailingTool::class, $classes);
    }
}
