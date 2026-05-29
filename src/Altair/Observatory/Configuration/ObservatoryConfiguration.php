<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Traits\EnvAwareTrait;
use Altair\Container\Container;
use Altair\Doctor\Doctor;
use Altair\Events\Reader;
use Altair\Introspection\Inspector\ConfigInspector;
use Altair\Introspection\Inspector\ContainerInspector;
use Altair\Introspection\Inspector\RouteInspector;
use Altair\Observatory\Contracts\FailedQueueReaderInterface;
use Altair\Observatory\Contracts\MigrationStatusReaderInterface;
use Altair\Observatory\Contracts\PanelInterface;
use Altair\Observatory\Observatory;
use Altair\Observatory\Panel\ConfigPanel;
use Altair\Observatory\Panel\ContainerPanel;
use Altair\Observatory\Panel\EventsPanel;
use Altair\Observatory\Panel\HealthPanel;
use Altair\Observatory\Panel\MigrationsPanel;
use Altair\Observatory\Panel\QueuesPanel;
use Altair\Observatory\Panel\RoutesPanel;
use Altair\Observatory\Panel\RuntimePanel;
use Altair\Observatory\PanelRegistry;
use Altair\Observatory\Security\AccessGuardInterface;
use Altair\Observatory\Security\EnvironmentAccessGuard;
use Altair\Observatory\View\TemplateRenderer;
use Override;

/**
 * Wires the access guard, the panel registry and the Observatory facade.
 *
 * Access is fail-closed (OBSERVATORY_ENABLED default off, APP_ENV default
 * "production"). The registry is built lazily inside a delegate, so panels are
 * only added when their data source is actually bound in the container — the
 * dashboard degrades gracefully (fewer cards) rather than failing, and there is
 * no ordering requirement between this Configuration and the source packages.
 *
 * QueuesPanel and MigrationsPanel read through the framework-owned
 * {@see FailedQueueReaderInterface} / {@see MigrationStatusReaderInterface}
 * seams; bind a host adapter for those to light up with live data.
 */
class ObservatoryConfiguration implements ConfigurationInterface
{
    use EnvAwareTrait;

    #[Override]
    public function apply(Container $container): void
    {
        $enabled = filter_var($this->env->get('OBSERVATORY_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        $environment = (string) $this->env->get('APP_ENV', 'production');
        $guard = new EnvironmentAccessGuard($enabled, $environment);

        $container->factory(AccessGuardInterface::class, static fn(): AccessGuardInterface => $guard)->shared();

        $container->factory(TemplateRenderer::class, static fn(): TemplateRenderer => TemplateRenderer::default())->shared();

        $container->factory(PanelRegistry::class, function () use ($container): PanelRegistry {
            $registry = new PanelRegistry([new RuntimePanel()]);

            $this->registerIfAvailable($registry, $container, HealthPanel::class, Doctor::class);
            $this->registerIfAvailable($registry, $container, EventsPanel::class, Reader::class);
            $this->registerIfAvailable($registry, $container, QueuesPanel::class, FailedQueueReaderInterface::class);
            $this->registerIfAvailable($registry, $container, RoutesPanel::class, RouteInspector::class);
            $this->registerIfAvailable($registry, $container, ContainerPanel::class, ContainerInspector::class);
            $this->registerIfAvailable($registry, $container, ConfigPanel::class, ConfigInspector::class);
            $this->registerIfAvailable($registry, $container, MigrationsPanel::class, MigrationStatusReaderInterface::class);

            return $registry;
        })->shared();

        $container->factory(Observatory::class, static function () use ($container, $guard): Observatory {
            $registry = $container->get(PanelRegistry::class);

            return new Observatory($registry instanceof PanelRegistry ? $registry : new PanelRegistry(), $guard);
        })->shared();
    }

    /**
     * Register $panelClass only when its required data source is bound, autowiring
     * the panel's constructor from the container.
     */
    private function registerIfAvailable(
        PanelRegistry $registry,
        Container $container,
        string $panelClass,
        string $requiredDependency,
    ): void {
        if (!$container->has($requiredDependency)) {
            return;
        }

        $panel = $container->get($panelClass);

        if ($panel instanceof PanelInterface) {
            $registry->register($panel);
        }
    }
}
