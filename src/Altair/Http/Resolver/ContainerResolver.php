<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Resolver;

use Altair\Container\Container;

/**
 * Relay 2 resolver: any callable that turns an entry (class name or object) into an instance.
 */
class ContainerResolver
{
    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * @param class-string|object $entry
     */
    public function __invoke(object|string $entry): object
    {
        return \is_object($entry) ? $entry : $this->container->make($entry);
    }
}
