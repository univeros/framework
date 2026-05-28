<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Middleware\Resolver;

use Altair\Container\Container;
use Altair\Container\Exception\InjectionException;
use Altair\Middleware\Contracts\MiddlewareInterface;
use Altair\Middleware\Contracts\MiddlewareResolverInterface;
use InvalidArgumentException;
use Override;
use ReflectionException;

class MiddlewareResolver implements MiddlewareResolverInterface
{
    /**
     * MiddlewareResolver constructor.
     */
    public function __construct(protected Container $container) {}

    /**
     * @throws InjectionException
     * @throws ReflectionException
     * @throws InvalidArgumentException
     */
    #[Override]
    public function __invoke(mixed $entry): MiddlewareInterface
    {
        if ($entry instanceof MiddlewareInterface) {
            return $entry;
        }

        if (\is_string($entry)) {
            $resolved = $this->container->make($entry);
            if ($resolved instanceof MiddlewareInterface) {
                return $resolved;
            }
        }

        throw new InvalidArgumentException(
            \sprintf('Unable to resolve middleware entry to an instance of %s.', MiddlewareInterface::class)
        );
    }
}
