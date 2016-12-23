<?php
namespace Altair\Http\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Http\Base\Payload;
use Altair\Http\Contracts\PayloadInterface;

class PayloadConfiguration implements ConfigurationInterface
{
    /**
     * @inheritdoc
     */
    public function apply(Container $container)
    {
        $container->alias(
            PayloadInterface::class,
            Payload::class
        );
    }
}
