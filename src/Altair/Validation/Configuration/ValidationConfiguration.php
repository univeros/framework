<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Validation\Contracts\ResolverInterface;
use Altair\Validation\Contracts\RulesRunnerInterface;
use Altair\Validation\Resolver\RuleResolver;
use Altair\Validation\RulesRunner;
use Override;

class ValidationConfiguration implements ConfigurationInterface
{
    #[Override]
    public function apply(Container $container): void
    {
        $container->alias(ResolverInterface::class, RuleResolver::class);
        $container->alias(RulesRunnerInterface::class, RulesRunner::class);
        $container->bind(RuleResolver::class)->withParameters(['container' => $container]);
    }
}
