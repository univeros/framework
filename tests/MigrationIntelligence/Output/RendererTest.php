<?php

declare(strict_types=1);

namespace Altair\Tests\MigrationIntelligence\Output;

use Altair\MigrationIntelligence\Exception\MigrationIntelligenceException;
use Altair\MigrationIntelligence\Plan\PlanSet;
use Altair\MigrationIntelligence\Output\HumanRenderer;
use Altair\MigrationIntelligence\Output\JsonRenderer;
use Altair\MigrationIntelligence\Output\RendererRegistry;
use Altair\MigrationIntelligence\Plan\PlanBuilder;
use Altair\MigrationIntelligence\Plan\PlanRequest;
use Altair\MigrationIntelligence\Schema\ColumnShape;
use Altair\MigrationIntelligence\Schema\ColumnType;
use Altair\MigrationIntelligence\Schema\TableShape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonRenderer::class)]
#[CoversClass(HumanRenderer::class)]
#[CoversClass(RendererRegistry::class)]
class RendererTest extends TestCase
{
    private const int FIXED_TS = 1_748_000_000;

    public function testJsonRendererProducesStableStructure(): void
    {
        $plan = $this->addColumnPlan();

        $json = (new JsonRenderer())->render($plan);
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertSame('users', $decoded['table']);
        $this->assertFalse($decoded['two_phase']);
        $this->assertSame('add_column', $decoded['migrations'][0]['operations'][0]['op']);
        $this->assertArrayHasKey('safety', $decoded);
        $this->assertTrue($decoded['safety']['skipped']);
    }

    public function testHumanRendererReportsNoChanges(): void
    {
        $shape = new TableShape('users', [new ColumnShape('id', ColumnType::PRIMARY, primary: true)]);
        $plan = (new PlanBuilder())->build(new PlanRequest($shape, $shape, timestamp: self::FIXED_TS));

        $output = (new HumanRenderer())->render($plan);

        $this->assertStringContainsString('No changes', $output);
    }

    public function testHumanRendererShowsOperationsAndSafety(): void
    {
        $output = (new HumanRenderer())->render($this->addColumnPlan());

        $this->assertStringContainsString('Operations:', $output);
        $this->assertStringContainsString('ADD COLUMN', $output);
        $this->assertStringContainsString('Safety:', $output);
    }

    public function testRegistryResolvesFormatsAndRejectsUnknown(): void
    {
        $registry = RendererRegistry::default();

        $this->assertInstanceOf(JsonRenderer::class, $registry->get('json'));
        $this->assertInstanceOf(HumanRenderer::class, $registry->get('human'));
        $this->assertSame(['human', 'json'], $registry->available());

        $this->expectException(MigrationIntelligenceException::class);
        $registry->get('xml');
    }

    private function addColumnPlan(): PlanSet
    {
        $from = new TableShape('users', [new ColumnShape('id', ColumnType::PRIMARY, primary: true)]);
        $to = new TableShape('users', [
            new ColumnShape('id', ColumnType::PRIMARY, primary: true),
            new ColumnShape('display_name', ColumnType::STRING, nullable: true),
        ]);

        return (new PlanBuilder())->build(new PlanRequest($from, $to, timestamp: self::FIXED_TS));
    }
}
