<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Migrations;

use Altair\Container\Container;
use Altair\Module\Contracts\MigrationDirectoriesProviderInterface;

/**
 * Collects the migration directories contributed by every registered module
 * that ships migrations.
 *
 * The directories are passed to Cycle's {@see \Cycle\Migrations\Config\MigrationConfig}
 * as `vendorDirectories`, so a single migrator scans the host's
 * `database/migrations` plus every module's directory with correct global
 * ordering, status, and rollback. Cycle reads each migration's FQCN from the
 * file itself, so per-module namespaces never collide.
 *
 * Modules are discovered through the container tag `altair.module` (the value
 * of {@see \Altair\Module\ModuleConfiguration::MODULE_TAG}).
 */
final class ModuleMigrationDirectories
{
    /**
     * @return list<string>
     */
    public static function collect(Container $container): array
    {
        $directories = [];

        foreach ($container->tagged('altair.module') as $module) {
            if (!$module instanceof MigrationDirectoriesProviderInterface) {
                continue;
            }

            foreach ($module->migrationDirectories() as $source) {
                $directories[] = $source->directory;
            }
        }

        return $directories;
    }

    /**
     * Module migration directories that actually exist on disk, ready to be
     * passed to {@see MigrationConfigFactory::create()} as `vendorDirectories`.
     * Null container (e.g. a command constructed by hand in a test) → none.
     *
     * @return list<string>
     */
    public static function existingFor(?Container $container): array
    {
        if (!$container instanceof Container) {
            return [];
        }

        return array_values(array_filter(self::collect($container), is_dir(...)));
    }
}
