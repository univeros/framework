<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Middleware\Resolver;

use Altair\Container\Exception\InjectionException;
use Altair\Container\Container;
use Altair\Middleware\Contracts\MiddlewareInterface;
use Altair\Middleware\Contracts\MiddlewareResolverInterface;

class MiddlewareResolver implements MiddlewareResolverInterface
{
    /**
     * MiddlewareResolver constructor.
     */
    public function __construct(protected Container $container)
    {
    }

    /**
     * @param mixed $entry
     * @throws InjectionException
     * @throws \ReflectionException
     */
    #[\Override]
    public function __invoke($entry): MiddlewareInterface
    {
        if (is_object($entry)) {
            return $entry;
        }

        return $this->container->make($entry);
    }
}
