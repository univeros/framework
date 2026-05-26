<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Resolver;

use Altair\Container\Container;
use Altair\Container\Exception\InjectionException;
use Altair\Courier\Contracts\CommandMiddlewareInterface;
use Altair\Courier\Contracts\MiddlewareResolverInterface;
use Override;
use ReflectionException;

class MiddlewareResolver implements MiddlewareResolverInterface
{
    /**
     * MiddlewareResolver constructor.
     */
    public function __construct(protected Container $container) {}

    /**
     * Resolve a class spec into an object, if it is not already instantiated.
     *
     * @param string|object $entry
     * @throws InjectionException
     * @throws ReflectionException
     */
    #[Override]
    public function __invoke($entry): CommandMiddlewareInterface
    {
        return \is_object($entry) ? $entry : $this->container->make($entry);
    }
}
