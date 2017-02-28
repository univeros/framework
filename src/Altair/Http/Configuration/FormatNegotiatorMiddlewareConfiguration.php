<?php
namespace Altair\Http\Configuration;


use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Http\Contracts\FormatNegotiatorInterface;
use Altair\Http\Support\FormatNegotiator;

class FormatNegotiatorMiddlewareConfiguration implements ConfigurationInterface
{
    public function apply(Container $container)
    {
        $container
            ->alias(FormatNegotiatorInterface::class, FormatNegotiator::class);
    }
}
