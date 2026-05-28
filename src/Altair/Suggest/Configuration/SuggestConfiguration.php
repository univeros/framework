<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Introspection\Inspector\ContainerInspector;
use Altair\Introspection\Inspector\ListenerInspector;
use Altair\Introspection\Inspector\PipelineInspector;
use Altair\Introspection\Inspector\RouteInspector;
use Altair\Introspection\Inspector\SpecInspector;
use Altair\Suggest\Output\RendererRegistry;
use Altair\Suggest\Rule\DeadBindingRule;
use Altair\Suggest\Rule\DeadEventRule;
use Altair\Suggest\Rule\FatConstructorRule;
use Altair\Suggest\Rule\OrphanMiddlewareRule;
use Altair\Suggest\Rule\RouteWithoutSpecRule;
use Altair\Suggest\RuleRegistry;
use Altair\Suggest\Snapshot\SnapshotFactory;
use Altair\Suggest\SuggestionEngine;
use Exception;
use Override;

/**
 * Wires the rule registry, the snapshot factory, the engine, and the
 * renderer registry into the Container.
 *
 * The snapshot factory resolves each introspection inspector lazily and
 * defensively: an inspector that is not bound — or that cannot construct
 * because its underlying collection (routes, listeners, pipeline) is absent —
 * is treated as null, and that snapshot section comes back empty. So this
 * Configuration applies cleanly whether or not the host uses FastRoute,
 * Happen, Relay, or the spec scaffolder. Apply `IntrospectionConfiguration`
 * first to get the richest snapshot.
 */
final readonly class SuggestConfiguration implements ConfigurationInterface
{
    public function __construct(
        private int $fatConstructorThreshold = FatConstructorRule::DEFAULT_THRESHOLD,
    ) {}

    #[Override]
    public function apply(Container $container): void
    {
        $threshold = $this->fatConstructorThreshold;

        $registry = new RuleRegistry([
            new DeadEventRule(),
            new RouteWithoutSpecRule(),
            new OrphanMiddlewareRule(),
            new FatConstructorRule($threshold),
            new DeadBindingRule(),
        ]);

        $container
            ->delegate(RuleRegistry::class, static fn(): RuleRegistry => $registry)
            ->share(RuleRegistry::class)

            ->delegate(
                SnapshotFactory::class,
                // Capture the real container: a `Container`-typed delegate
                // parameter would be auto-wired to a fresh, empty instance,
                // so ContainerInspector is constructed against the container
                // we were handed rather than resolved through make().
                static fn(): SnapshotFactory => new SnapshotFactory(
                    new ContainerInspector($container),
                    self::optional($container, RouteInspector::class),
                    self::optional($container, ListenerInspector::class),
                    self::optional($container, PipelineInspector::class),
                    self::optional($container, SpecInspector::class),
                ),
            )
            ->share(SnapshotFactory::class)

            ->delegate(SuggestionEngine::class, static fn(): SuggestionEngine => new SuggestionEngine($registry))
            ->share(SuggestionEngine::class)

            ->delegate(RendererRegistry::class, static fn(): RendererRegistry => RendererRegistry::default())
            ->share(RendererRegistry::class);
    }

    /**
     * Resolve an inspector or return null when it is unbound or unconstructable.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private static function optional(Container $container, string $class): ?object
    {
        try {
            $instance = $container->make($class);

            return $instance instanceof $class ? $instance : null;
        } catch (Exception) {
            // A missing binding or unconstructable inspector is expected and
            // degrades to an empty section. `Error`/`TypeError` are real bugs
            // and propagate rather than being masked as "host doesn't use it".
            return null;
        }
    }
}
