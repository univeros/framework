<?php

declare(strict_types=1);

namespace Altair\Tests\Introspection\Configuration;

use Altair\Happen\Contracts\EventDispatcherInterface;
use Altair\Container\Container;
use Altair\Happen\EventDispatcher;
use Altair\Http\Collection\MiddlewareCollection;
use Altair\Http\Collection\RouteCollection;
use Altair\Introspection\Configuration\IntrospectionConfiguration;
use Altair\Introspection\Inspector\ConfigInspector;
use Altair\Introspection\Inspector\ContainerInspector;
use Altair\Introspection\Inspector\ListenerInspector;
use Altair\Introspection\Inspector\ManifestDiffInspector;
use Altair\Introspection\Inspector\PipelineInspector;
use Altair\Introspection\Inspector\RouteInspector;
use Altair\Introspection\Inspector\SpecInspector;
use Altair\Introspection\Renderer\RendererRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IntrospectionConfiguration::class)]
class IntrospectionConfigurationTest extends TestCase
{
    public function testWiresEveryInspectorAndRendererRegistry(): void
    {
        $container = new Container();
        $container->share(new RouteCollection());
        $container->share(new MiddlewareCollection());
        $container->share(new EventDispatcher());
        $container->alias(EventDispatcherInterface::class, EventDispatcher::class);

        (new IntrospectionConfiguration(projectRoot: sys_get_temp_dir()))->apply($container);

        $this->assertInstanceOf(RendererRegistry::class, $container->make(RendererRegistry::class));
        $this->assertInstanceOf(ContainerInspector::class, $container->make(ContainerInspector::class));
        $this->assertInstanceOf(RouteInspector::class, $container->make(RouteInspector::class));
        $this->assertInstanceOf(ListenerInspector::class, $container->make(ListenerInspector::class));
        $this->assertInstanceOf(PipelineInspector::class, $container->make(PipelineInspector::class));
        $this->assertInstanceOf(ConfigInspector::class, $container->make(ConfigInspector::class));
        $this->assertInstanceOf(SpecInspector::class, $container->make(SpecInspector::class));
        $this->assertInstanceOf(ManifestDiffInspector::class, $container->make(ManifestDiffInspector::class));
    }
}
