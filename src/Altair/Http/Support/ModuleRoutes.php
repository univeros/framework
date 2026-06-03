<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Support;

use Altair\Container\Container;
use Altair\Module\Contracts\RoutesProviderInterface;

/**
 * Merges the host's `config/routes.php` with the routes contributed by every
 * registered module, producing the flat route list the front controller feeds
 * to FastRoute.
 *
 * Modules are discovered through the container tag `altair.module` (the value
 * of {@see \Altair\Module\ModuleConfiguration::MODULE_TAG} — referenced as a
 * literal here so `univeros/http` need not depend on `univeros/module` at the
 * class level). Host routes come first, so a host can always override a route a
 * module would otherwise add.
 */
final class ModuleRoutes
{
    /**
     * @param list<array{0: string, 1: string, 2: class-string}> $baseRoutes
     *
     * @return list<array{0: string, 1: string, 2: class-string}>
     */
    public static function collect(Container $container, array $baseRoutes): array
    {
        $routes = $baseRoutes;

        foreach ($container->tagged('altair.module') as $module) {
            if (!$module instanceof RoutesProviderInterface) {
                continue;
            }

            foreach ($module->routes() as $route) {
                $routes[] = $route;
            }
        }

        return $routes;
    }
}
