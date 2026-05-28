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
use Altair\Observatory\Observatory;
use Altair\Observatory\Panel\RuntimePanel;
use Altair\Observatory\PanelRegistry;
use Altair\Observatory\Security\AccessGuardInterface;
use Altair\Observatory\Security\EnvironmentAccessGuard;
use Override;

/**
 * Wires the access guard, the panel registry (with the built-in panels) and the
 * Observatory facade into the Container.
 *
 * Access is fail-closed: it reads OBSERVATORY_ENABLED (default off) and APP_ENV
 * (default "production"), so an unconfigured or production deploy never serves
 * the panel. Hosts add panels by `prepare()`-ing {@see PanelRegistry} after this
 * Configuration runs, and can rebind {@see AccessGuardInterface} for real auth.
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
        $registry = new PanelRegistry([new RuntimePanel()]);

        $container
            ->delegate(AccessGuardInterface::class, static fn(): AccessGuardInterface => $guard)
            ->share(AccessGuardInterface::class)

            ->delegate(PanelRegistry::class, static fn(): PanelRegistry => $registry)
            ->share(PanelRegistry::class)

            ->delegate(Observatory::class, static fn(): Observatory => new Observatory($registry, $guard))
            ->share(Observatory::class);
    }
}
