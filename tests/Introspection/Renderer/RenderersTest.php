<?php

declare(strict_types=1);

namespace Altair\Tests\Introspection\Renderer;

use Altair\Introspection\Exception\IntrospectionException;
use Altair\Introspection\Renderer\JsonRenderer;
use Altair\Introspection\Renderer\RendererRegistry;
use Altair\Introspection\Renderer\TableRenderer;
use Altair\Introspection\Result\InspectionTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonRenderer::class)]
#[CoversClass(TableRenderer::class)]
#[CoversClass(RendererRegistry::class)]
#[CoversClass(InspectionTable::class)]
class RenderersTest extends TestCase
{
    public function testJsonRendererOutputIsDeterministic(): void
    {
        $table = new InspectionTable(
            title: 'sample',
            columns: ['a', 'b'],
            rows: [['a' => 1, 'b' => 'x'], ['a' => 2, 'b' => 'y']],
            extras: ['total' => 2],
        );

        $first = (new JsonRenderer())->render($table);
        $second = (new JsonRenderer())->render($table);

        $this->assertSame($first, $second, 'Renderer must be byte-deterministic for golden-file diffs.');
        $this->assertJson(trim($first));
    }

    public function testTableRendererPadsColumnsToWidestValue(): void
    {
        $table = new InspectionTable(
            title: 'sample',
            columns: ['id', 'name'],
            rows: [['id' => '1', 'name' => 'short'], ['id' => '42', 'name' => 'much-longer-value']],
        );

        $rendered = (new TableRenderer())->render($table);

        $this->assertStringContainsString('sample', $rendered);
        $this->assertStringContainsString('much-longer-value', $rendered);
        $this->assertStringContainsString('id  name', $rendered);
    }

    public function testTableRendererAnnouncesEmpty(): void
    {
        $table = new InspectionTable(title: 'empty', columns: ['x'], rows: []);
        $this->assertStringContainsString('(no rows)', (new TableRenderer())->render($table));
    }

    public function testTableRendererFlattensMultiLineValues(): void
    {
        $table = new InspectionTable(
            title: 't',
            columns: ['v'],
            rows: [['v' => "line1\nline2"]],
        );

        $rendered = (new TableRenderer())->render($table);
        $this->assertStringNotContainsString("line1\nline2", $rendered);
        $this->assertStringContainsString('line1 line2', $rendered);
    }

    public function testRendererRegistryDefaultsResolveHumanAndJson(): void
    {
        $registry = RendererRegistry::default();
        $this->assertInstanceOf(TableRenderer::class, $registry->get('human'));
        $this->assertInstanceOf(JsonRenderer::class, $registry->get('json'));
        $this->assertSame(['human', 'json'], $registry->available());
    }

    public function testRendererRegistryThrowsOnUnknownFormat(): void
    {
        $this->expectException(IntrospectionException::class);
        RendererRegistry::default()->get('xml');
    }

    public function testInspectionTableRejectsEmptyColumnName(): void
    {
        $this->expectException(IntrospectionException::class);
        new InspectionTable(title: 't', columns: [''], rows: []);
    }
}
