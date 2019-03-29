<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Container\Definition;
use Altair\Sanitation\Contracts\FiltersRunnerInterface;
use Altair\Sanitation\Contracts\ResolverInterface;
use Altair\Sanitation\FiltersRunner;
use Altair\Sanitation\Resolver\FilterResolver;

class SanitationConfiguration implements ConfigurationInterface
{
    /**
     * @param Container $container
     */
    public function apply(Container $container)
    {
        $container
            ->alias(ResolverInterface::class, FilterResolver::class)
            ->alias(FiltersRunnerInterface::class, FiltersRunner::class)
            ->define(FilterResolver::class, new Definition([':container' => $container]));
    }
}
