<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Configuration\Contracts;

use Altair\Container\Container;
use Altair\Container\Exception\InjectionException;
use Altair\Container\Exception\InvalidArgumentException;

interface ConfigurationInterface
{
    /**
     * Applies a configuration set to a dependency injector.
     *
     * @param Container $container
     *
     * @throws InvalidArgumentException
     * @throws InjectionException
     */
    public function apply(Container $container): void;
}
