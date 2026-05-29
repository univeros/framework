<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Introspection\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Happen\Contracts\EventDispatcherInterface;
use Altair\Happen\EventDispatcher;
use Altair\Http\Collection\MiddlewareCollection;
use Altair\Http\Collection\RouteCollection;
use Altair\Introspection\Inspector\ConfigInspector;
use Altair\Introspection\Inspector\ContainerInspector;
use Altair\Introspection\Inspector\ListenerInspector;
use Altair\Introspection\Inspector\ManifestDiffInspector;
use Altair\Introspection\Inspector\PipelineInspector;
use Altair\Introspection\Inspector\RouteInspector;
use Altair\Introspection\Inspector\SpecInspector;
use Altair\Introspection\Renderer\RendererRegistry;

use const DIRECTORY_SEPARATOR;

use Override;

/**
 * Wires every introspection primitive into the Altair Container.
 *
 * Each Inspector is bound as a shared service (they're stateless wrappers
 * over already-shared collections, so sharing them costs nothing).
 *
 * Hosts that don't use FastRoute / Happen / Relay can apply this
 * Configuration and skip the inspectors they don't need — every Inspector
 * is independently constructable. The CLI commands type-hint individual
 * inspectors, so a missing dependency only fails the command that needs it.
 */
final readonly class IntrospectionConfiguration implements ConfigurationInterface
{
    /**
     * @param string|null  $specRoot       Default spec directory (defaults to <project>/api).
     * @param string|null  $manifestRoot   Default .agent root (defaults to <project>/.agent).
     * @param list<string> $extraSecretPatterns Additional secret-name patterns for ConfigInspector.
     */
    public function __construct(
        private ?string $projectRoot = null,
        private ?string $specRoot = null,
        private ?string $manifestRoot = null,
        private array $extraSecretPatterns = [],
    ) {}

    #[Override]
    public function apply(Container $container): void
    {
        $projectRoot = $this->projectRoot ?? (getcwd() ?: '.');
        $specRoot = $this->specRoot ?? $projectRoot . DIRECTORY_SEPARATOR . 'api';
        $manifestRoot = $this->manifestRoot ?? $projectRoot . DIRECTORY_SEPARATOR . '.agent';
        $extraSecretPatterns = $this->extraSecretPatterns;

        $container->factory(
            RendererRegistry::class,
            static fn(): RendererRegistry => RendererRegistry::default(),
        )->shared();

        $container->factory(
            ContainerInspector::class,
            static fn(Container $c): ContainerInspector => new ContainerInspector($c),
        )->shared();

        $container->factory(
            RouteInspector::class,
            static fn(RouteCollection $routes): RouteInspector => new RouteInspector($routes),
        )->shared();

        $container->factory(
            ListenerInspector::class,
            static fn(EventDispatcherInterface $d): ListenerInspector => new ListenerInspector(
                $d instanceof EventDispatcher ? $d : new EventDispatcher(),
            ),
        )->shared();

        $container->factory(
            PipelineInspector::class,
            static fn(MiddlewareCollection $q): PipelineInspector => new PipelineInspector($q),
        )->shared();

        $container->factory(
            ConfigInspector::class,
            static fn(Container $c): ConfigInspector => new ConfigInspector($c, $extraSecretPatterns),
        )->shared();

        $container->factory(
            SpecInspector::class,
            static fn(): SpecInspector => new SpecInspector($specRoot),
        )->shared();

        $container->factory(
            ManifestDiffInspector::class,
            static fn(): ManifestDiffInspector => new ManifestDiffInspector($manifestRoot),
        )->shared();
    }
}
