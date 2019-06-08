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
use Altair\Validation\Contracts\ResolverInterface;
use Altair\Validation\Contracts\RulesRunnerInterface;
use Altair\Validation\Resolver\RuleResolver;
use Altair\Validation\RulesRunner;

class ValidationConfiguration implements ConfigurationInterface
{
    /**
     * @param Container $container
     */
    public function apply(Container $container): void
    {
        $container
            ->alias(ResolverInterface::class, RuleResolver::class)
            ->alias(RulesRunnerInterface::class, RulesRunner::class)
            ->define(RuleResolver::class, new Definition([':container' => $container]));
    }
}
