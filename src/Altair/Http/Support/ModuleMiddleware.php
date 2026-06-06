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
use Altair\Module\Contracts\MiddlewareProviderInterface;
use Psr\Http\Server\MiddlewareInterface;

/**
 * Merges the host's base PSR-15 pipeline with the middleware contributed by
 * every registered module, producing the flat, dispatch-ordered list the front
 * controller feeds to Relay.
 *
 * Modules are discovered through the container tag `altair.module` (the value
 * of {@see \Altair\Module\ModuleConfiguration::MODULE_TAG} — referenced as a
 * literal here so `univeros/http` need not depend on `univeros/module` at the
 * class level, mirroring {@see ModuleRoutes}).
 *
 * Ordering is by integer `priority` (lower = earlier / more outer), against the
 * documented anchors in {@see MiddlewarePriority}. The merge is a STABLE sort:
 * entries with equal priority keep their input order — base entries first, then
 * modules in registration order — so the merged pipeline is fully deterministic.
 */
final class ModuleMiddleware
{
    /**
     * Merge base + module entries and return the middleware refs in dispatch order.
     *
     * @param list<array{middleware: class-string<MiddlewareInterface>|MiddlewareInterface, priority: int}> $baseMiddleware
     *
     * @return list<class-string<MiddlewareInterface>|MiddlewareInterface>
     */
    public static function collect(Container $container, array $baseMiddleware): array
    {
        return array_column(self::entries($container, $baseMiddleware), 'middleware');
    }

    /**
     * Same merge as {@see self::collect()} but returns the prioritised entries
     * (middleware + priority) rather than just the refs. Useful for binding a
     * {@see \Altair\Http\Collection\MiddlewareCollection} that introspection can
     * read, or for any caller that needs the resolved priorities.
     *
     * @param list<array{middleware: class-string<MiddlewareInterface>|MiddlewareInterface, priority: int}> $baseMiddleware
     *
     * @return list<array{middleware: class-string<MiddlewareInterface>|MiddlewareInterface, priority: int}>
     */
    public static function entries(Container $container, array $baseMiddleware): array
    {
        $entries = $baseMiddleware;

        foreach ($container->tagged('altair.module') as $module) {
            if (!$module instanceof MiddlewareProviderInterface) {
                continue;
            }

            foreach ($module->middleware() as $entry) {
                $entries[] = $entry;
            }
        }

        // PHP 8.0+ guarantees usort is stable (this package requires >=8.3), so
        // equal priorities always keep input order: base entries first, then
        // modules in registration order. That is what makes the merge deterministic.
        usort($entries, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);

        return $entries;
    }
}
