<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Resolver;

use Altair\Container\Container;
use Altair\Container\Definition;
use Altair\Container\Exception\InjectionException;
use Altair\Validation\Contracts\ResolverInterface;
use Altair\Validation\Contracts\RuleInterface;
use Override;
use ReflectionException;

class RuleResolver implements ResolverInterface
{
    /**
     * RuleResolver constructor.
     */
    public function __construct(protected Container $container) {}

    /**
     * @param mixed $entry
     * @throws InjectionException
     * @throws ReflectionException
     */
    #[Override]
    public function __invoke($entry): RuleInterface
    {
        if (\is_object($entry)) { // string
            return $entry;
        }

        $arguments = [];
        if (\is_array($entry)) { // ['class' => RuleB::class, ':argument1' => 'value1', ':argument2' => 'value2']
            $arguments = \array_slice($entry, 1);
            $entry = $entry['class']; // force error if key is not configured
        } // else is a string

        return $this->container->make($entry, new Definition($arguments));
    }
}
