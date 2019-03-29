<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Resolver;

use Altair\Container\Container;
use Altair\Courier\Contracts\CommandMiddlewareInterface;
use Altair\Courier\Contracts\MiddlewareResolverInterface;

class MiddlewareResolver implements MiddlewareResolverInterface
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * MiddlewareResolver constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Resolve a class spec into an object, if it is not already instantiated.
     *
     * @param string|object $entry
     *
     * @return CommandMiddlewareInterface
     */
    public function __invoke($entry): CommandMiddlewareInterface
    {
        if (is_object($entry)) {
            return $entry;
        }

        return $this->container->make($entry);
    }
}
