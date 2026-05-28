<?php

declare(strict_types=1);

namespace Altair\Tests\MigrationIntelligence\Configuration;

use Altair\Container\Container;
use Altair\MigrationIntelligence\Configuration\MigrationIntelligenceConfiguration;
use Altair\MigrationIntelligence\Emitter\CycleMigrationEmitter;
use Altair\MigrationIntelligence\Output\RendererRegistry;
use Altair\MigrationIntelligence\Plan\PlanBuilder;
use Altair\MigrationIntelligence\Planner\PlannerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MigrationIntelligenceConfiguration::class)]
class MigrationIntelligenceConfigurationTest extends TestCase
{
    public function testWiresPlannerEmitterBuilderAndRenderers(): void
    {
        $container = new Container();
        (new MigrationIntelligenceConfiguration())->apply($container);

        $this->assertInstanceOf(PlanBuilder::class, $container->make(PlanBuilder::class));
        $this->assertInstanceOf(PlannerRegistry::class, $container->make(PlannerRegistry::class));
        $this->assertInstanceOf(CycleMigrationEmitter::class, $container->make(CycleMigrationEmitter::class));

        $renderers = $container->make(RendererRegistry::class);
        $this->assertInstanceOf(RendererRegistry::class, $renderers);
        $this->assertSame(['human', 'json'], $renderers->available());
    }

    public function testSharedServicesAreSingletons(): void
    {
        $container = new Container();
        (new MigrationIntelligenceConfiguration())->apply($container);

        $this->assertSame($container->make(PlanBuilder::class), $container->make(PlanBuilder::class));
    }
}
