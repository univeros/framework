<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tinker\Repl;

use Altair\Container\Container;

/**
 * A PsySH/VarDumper caster that renders a {@see Container} as a short summary
 * instead of its full internal object graph.
 *
 * Dependency-free: it reads only the container's public
 * `getRealisedSingletons()` so it works whether or not
 * `univeros/introspection` is installed.
 */
final class ContainerCaster
{
    /**
     * @return array<string, mixed>
     */
    public static function cast(Container $container): array
    {
        return [
            'class' => $container::class,
            'realised singletons' => \count($container->getRealisedSingletons()),
            'tip' => 'resolve services with $container->make(Foo::class)',
        ];
    }
}
