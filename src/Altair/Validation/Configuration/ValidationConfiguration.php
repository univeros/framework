<?php
namespace Altair\Validation\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Container\Definition;
use Altair\Validation\Contracts\ResolverInterface;
use Altair\Validation\Resolver\RuleResolver;

class ValidationConfiguration implements ConfigurationInterface
{
    /**
     * @param Container $container
     */
    public function apply(Container $container)
    {
        $container
            ->alias(ResolverInterface::class, RuleResolver::class)
            ->define(RuleResolver::class, new Definition([':container' => $container]));
    }
}
