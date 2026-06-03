<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Schema;

use Altair\Container\Container;
use Altair\Module\Contracts\EntityDirectoriesProviderInterface;

/**
 * Collects the entity directories the schema should be compiled from: the
 * host's own directories first, then those contributed by every registered
 * module that ships entities.
 *
 * Modules are discovered through the container tag `altair.module` (the value
 * of {@see \Altair\Module\ModuleConfiguration::MODULE_TAG} — referenced as a
 * literal so `univeros/persistence` need not depend on the tag class).
 */
final class ModuleEntityDirectories
{
    /**
     * @param list<string> $baseDirectories
     *
     * @return list<string>
     */
    public static function collect(Container $container, array $baseDirectories = []): array
    {
        $directories = $baseDirectories;

        foreach ($container->tagged('altair.module') as $module) {
            if (!$module instanceof EntityDirectoriesProviderInterface) {
                continue;
            }

            foreach ($module->entityDirectories() as $directory) {
                $directories[] = $directory;
            }
        }

        return $directories;
    }
}
