<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Resolver;

use Altair\Container\Container;
use Altair\Container\Exception\ContainerException;
use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Contracts\ResolverInterface;
use Altair\Sanitation\Exception\InvalidArgumentException;
use Override;
use ReflectionException;

class FilterResolver implements ResolverInterface
{
    /**
     * RuleResolver constructor.
     */
    public function __construct(protected Container $container) {}

    /**
     * @throws ContainerException
     * @throws ReflectionException
     */
    #[Override]
    public function __invoke(mixed $entry): FilterInterface
    {
        if ($entry instanceof FilterInterface) {
            return $entry;
        }

        $arguments = [];
        if (\is_array($entry)) { // ['class' => FilterB::class, ':argument1' => 'value1', ':argument2' => 'value2']
            $arguments = \array_slice($entry, 1);
            $entry = $entry['class']; // force error if key is not configured
        } // else is a string

        if (!\is_string($entry) || !class_exists($entry)) {
            throw new InvalidArgumentException(
                \sprintf('A filter entry must resolve to a class-string or %s instance.', FilterInterface::class)
            );
        }

        $parameters = [];
        foreach ($arguments as $key => $value) {
            $parameters[ltrim((string) $key, ':')] = $value;
        }

        $filter = $this->container->make($entry, $parameters);

        if (!$filter instanceof FilterInterface) {
            throw new InvalidArgumentException(
                \sprintf('"%s" does not implement %s.', $entry, FilterInterface::class)
            );
        }

        return $filter;
    }
}
