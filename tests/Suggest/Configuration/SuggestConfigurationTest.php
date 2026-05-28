<?php

declare(strict_types=1);

namespace Altair\Tests\Suggest\Configuration;

use Altair\Container\Container;
use Altair\Introspection\Inspector\ContainerInspector;
use Altair\Suggest\Configuration\SuggestConfiguration;
use Altair\Suggest\Contracts\SuggestionRuleInterface;
use Altair\Suggest\Output\RendererRegistry;
use Altair\Suggest\Result\Severity;
use Altair\Suggest\RuleRegistry;
use Altair\Suggest\Snapshot\SnapshotFactory;
use Altair\Suggest\SuggestionEngine;
use Altair\Tests\Suggest\Support\Fixtures\Collaborator;
use Altair\Tests\Suggest\Support\Fixtures\ServiceWithCollaborator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SuggestConfiguration::class)]
class SuggestConfigurationTest extends TestCase
{
    public function testWiresRegistryFactoryEngineAndRenderers(): void
    {
        $container = new Container();
        (new SuggestConfiguration())->apply($container);

        $this->assertInstanceOf(RuleRegistry::class, $container->make(RuleRegistry::class));
        $this->assertInstanceOf(SnapshotFactory::class, $container->make(SnapshotFactory::class));
        $this->assertInstanceOf(SuggestionEngine::class, $container->make(SuggestionEngine::class));
        $this->assertInstanceOf(RendererRegistry::class, $container->make(RendererRegistry::class));

        $names = array_map(
            static fn(SuggestionRuleInterface $r): string => $r->name(),
            $container->make(RuleRegistry::class)->all(),
        );

        foreach (['dead_event', 'route_without_spec', 'orphan_middleware', 'fat_constructor', 'dead_binding'] as $expected) {
            $this->assertContains($expected, $names);
        }
    }

    public function testAppliesCleanlyWithoutIntrospectionInspectors(): void
    {
        $container = new Container();
        (new SuggestConfiguration())->apply($container);

        // No IntrospectionConfiguration applied, no collections bound — the
        // factory degrades to an empty snapshot rather than throwing.
        $snapshot = $container->make(SnapshotFactory::class)->create();

        $this->assertSame([], $snapshot->routes);
        $this->assertSame([], $snapshot->specs);
    }

    public function testCustomThresholdFlowsIntoFatConstructorRule(): void
    {
        $container = new Container();
        $container->share(new ServiceWithCollaborator(new Collaborator(), 'x'));
        $container->delegate(
            ContainerInspector::class,
            static fn(Container $c): ContainerInspector => new ContainerInspector($c),
        );

        (new SuggestConfiguration(fatConstructorThreshold: 0))->apply($container);

        $report = $container->make(SuggestionEngine::class)->analyse(
            $container->make(SnapshotFactory::class)->create(),
            Severity::Info,
            ['fat_constructor'],
        );

        $subjects = array_map(static fn($s): string => $s->subject, $report->suggestions);
        $this->assertContains(ServiceWithCollaborator::class, $subjects, 'threshold 0 flags the one-object-dep service');
    }
}
