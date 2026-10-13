<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Generator;

use Altair\AgentSpec\Model\ModuleManifest;
use Altair\Container\Container;
use Altair\Module\Contracts\ManifestContributorInterface;
use Altair\Module\Contracts\ModuleInterface;

/**
 * Collects the manifest sections contributed by every registered module that
 * implements {@see ManifestContributorInterface}, sorted by module name so the
 * output does not depend on registration order.
 *
 * Modules are discovered through the container tag `altair.module` (the value
 * of {@see \Altair\Module\ModuleConfiguration::MODULE_TAG}).
 */
final class ModuleManifests
{
    /**
     * Null container (the framework's bare `bin/altair`, or a command built by
     * hand in a test) → none.
     *
     * @return list<ModuleManifest>
     */
    public static function collect(?Container $container): array
    {
        if (!$container instanceof Container) {
            return [];
        }

        $manifests = [];
        foreach ($container->tagged('altair.module') as $module) {
            if (!$module instanceof ModuleInterface) {
                continue;
            }

            if (!$module instanceof ManifestContributorInterface) {
                continue;
            }

            $manifests[] = new ModuleManifest($module->name(), $module->manifestDescription(), $module->manifest());
        }

        usort($manifests, static fn(ModuleManifest $a, ModuleManifest $b): int => strcmp($a->name, $b->name));

        return $manifests;
    }
}
