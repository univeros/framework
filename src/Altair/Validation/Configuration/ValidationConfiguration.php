<?php
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
    public function apply(Container $container)
    {
        $container
            ->alias(ResolverInterface::class, RuleResolver::class)
            ->alias(RulesRunnerInterface::class, RulesRunner::class)
            ->define(RuleResolver::class, new Definition([':container' => $container]));
    }
}
