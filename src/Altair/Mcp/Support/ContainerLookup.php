<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Support;

use Altair\Container\Container;
use Throwable;

/**
 * Best-effort optional resolution of an already-registered binding. Returns
 * null (never throws) when the id is unbound or its construction fails, so
 * introspection tools can degrade to an "unavailable" note instead of
 * surfacing a raw container error.
 */
final class ContainerLookup
{
    public static function optional(Container $container, string $id): ?object
    {
        if (!$container->isset($id)) {
            return null;
        }

        try {
            $value = $container->get($id);
        } catch (Throwable) {
            return null;
        }

        return \is_object($value) ? $value : null;
    }

    /**
     * Resolve the first of several candidate ids that yields an object —
     * e.g. a concrete class registered directly OR under its interface key.
     */
    public static function firstOf(Container $container, string ...$ids): ?object
    {
        foreach ($ids as $id) {
            $value = self::optional($container, $id);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }
}
