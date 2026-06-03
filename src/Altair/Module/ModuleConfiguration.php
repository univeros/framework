<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Module;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Module\Contracts\ModuleInterface;
use Override;

/**
 * Registers a list of modules into the host container.
 *
 * For each module it (1) binds the instance and tags it {@see self::MODULE_TAG}
 * so framework consumers can discover it via `Container::tagged()`, then (2)
 * runs the module's own `apply()` to register its service bindings. This is the
 * single entry point a host adds to `config/modules.php`:
 *
 * ```php
 * return [
 *     new Acme\UserManagement\UserManagementModule(),
 * ];
 * ```
 *
 * Mirrors {@see \Altair\Configuration\Collection\ConfigurationCollection}; kept
 * separate so the module tag and the host-facing "modules" concept have a home.
 */
final readonly class ModuleConfiguration implements ConfigurationInterface
{
    /**
     * Container tag under which every registered module is discoverable.
     *
     * Consumers in other packages reference this value (the string literal,
     * to avoid a hard dependency on `univeros/module`) when collecting modules.
     */
    public const string MODULE_TAG = 'altair.module';

    /**
     * @param list<ModuleInterface> $modules
     */
    public function __construct(private array $modules = []) {}

    #[Override]
    public function apply(Container $container): void
    {
        foreach ($this->modules as $module) {
            $container->instance($module::class, $module)->tag(self::MODULE_TAG);
            $module->apply($container);
        }
    }
}
